<?php

namespace Tests\Feature\Modules;

use App\Models\Counter;
use App\Models\ExploreDismissal;
use App\Models\ExplorePreference;
use App\Models\HospitalProfile;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Models\Service;
use App\Models\User;
use App\Services\Explore\RecommendationService;
use App\Services\Explore\TravelEstimator;
use App\Services\Queue\QueueManager;
use Database\Seeders\Modules\ExploreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExploreTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 27.7;

    private const LNG = 85.3;

    private function location(string $type = 'Government'): Location
    {
        return Location::create(['name' => 'Test Office '.uniqid(), 'area' => 'Thamel, Kathmandu', 'type' => $type, 'icon' => 'bi-building', 'lat' => self::LAT, 'lng' => self::LNG]);
    }

    /** Service where a new joiner has 8 people ahead × 4 min = 32 min (low end 19 with no history). */
    private function service(Location $loc): Service
    {
        $s = $loc->services()->create([
            'name' => 'Licence Renewal', 'prefix' => 'D', 'counter' => 2, 'avg_minutes' => 4,
            'current_number' => 15, 'last_number' => 23, 'documents' => [],
        ]);
        Counter::create(['location_id' => $loc->id, 'service_id' => $s->id, 'number' => 2, 'label' => 'Counter 2']);

        return $s;
    }

    /** Place roughly $meters north of the location. */
    private function place(Location $loc, string $name, int $meters, int $visit, array $extra = []): NearbyPlace
    {
        return NearbyPlace::create(array_merge([
            'location_id' => $loc->id, 'name' => $name, 'category' => 'Cafe', 'emoji' => '☕', 'tip' => 'Demo',
            'lat' => self::LAT + $meters / 111195, 'lng' => self::LNG,
            'walk_minutes' => 1, 'visit_minutes' => $visit, 'rating' => 4.5,
        ], $extra));
    }

    // ---------------- Travel estimate math ----------------

    public function test_travel_estimate_uses_haversine_road_factor_and_walking_speed(): void
    {
        $t = app(TravelEstimator::class);

        // 1° of latitude ≈ 111.195 km on a 6371 km sphere.
        $this->assertEqualsWithDelta(111195, $t->distanceMeters(27.0, 85.0, 28.0, 85.0), 5);
        $this->assertSame(0.0, $t->distanceMeters(27.7, 85.3, 27.7, 85.3));

        // 1000 m straight → 1300 m street → 1.3 km / 4.5 km/h = 17.33 min → 18 (rounded up).
        $est = $t->straight(27.7, 85.3, 27.7 + 1000 / 111195, 85.3);
        $this->assertEqualsWithDelta(1000, $est['straight_m'], 2);
        $this->assertEqualsWithDelta(1300, $est['distance_m'], 3);
        $this->assertSame(18, $est['walk_minutes']);
        $this->assertSame('approximate (straight-line based)', $est['label']);
        $this->assertNull($est['geometry'], 'No fake route geometry without a routing provider');

        // Very short trips are at least one minute.
        $this->assertSame(1, $t->walkMinutes(10));
    }

    public function test_osrm_provider_is_used_only_when_configured_and_falls_back_safely(): void
    {
        $t = app(TravelEstimator::class);
        $this->assertSame('none', $t->provider());
        Http::fake(['osrm.local/*' => Http::sequence()
            ->push(['routes' => [['distance' => 900, 'geometry' => ['coordinates' => [[85.3, 27.7], [85.301, 27.705]]]]]])
            ->push('down', 500)]);
        $t->estimate(27.7, 85.3, 27.71, 85.3);
        Http::assertNothingSent();

        config(['explore.routing_provider' => 'osrm', 'explore.osrm_url' => 'http://osrm.local']);
        $routed = $t->estimate(27.7, 85.3, 27.705, 85.301);
        $this->assertSame('osrm', $routed['method']);
        $this->assertSame([[27.7, 85.3], [27.705, 85.301]], $routed['geometry']);
        $this->assertSame(12, $routed['walk_minutes']); // 0.9 km at 4.5 km/h

        $fallback = $t->estimate(27.7, 85.3, 27.705, 85.301);
        $this->assertSame('straight_line', $fallback['method']);
        $this->assertNull($fallback['geometry']);
    }

    // ---------------- Recommendations ----------------

    public function test_recommendations_fit_the_wait_with_safety_buffer_and_explain_why(): void
    {
        $loc = $this->location();
        $user = User::factory()->create();
        $near = $this->place($loc, 'Near Cafe', 200, 4);      // 260 m street → 4 min each way → 12 min total
        $long = $this->place($loc, 'Long Lunch', 200, 30);    // 4 + 30 + 4 = 38 min
        $edge = $this->place($loc, 'Edge Case', 200, 10);     // 4 + 10 + 4 = 18 → spare 2 < buffer 8

        $rec = app(RecommendationService::class)->recommend($user, $loc, 20);

        $ids = collect($rec['items'])->pluck('id')->all();
        $this->assertSame([$near->id], $ids);
        $this->assertNotContains($long->id, $ids);
        $this->assertNotContains($edge->id, $ids, 'Trips that eat into the safety buffer are excluded');

        $item = $rec['items'][0];
        $this->assertSame(12, $item['total']);
        $this->assertSame(8, $item['spare']);
        $this->assertStringContainsString('12 min total: 4 min walk each way + 4 min visit, leaves 8 min spare before your estimated turn', $item['explanation']);
        $this->assertStringContainsString('Opening hours not verified', $item['explanation']);
        $this->assertStringContainsString('approximately 20 minutes', $rec['message']);
    }

    public function test_remaining_wait_uses_the_conservative_low_estimate_from_the_ticket(): void
    {
        $loc = $this->location();
        $s = $this->service($loc);
        $user = User::factory()->create();
        $ticket = app(QueueManager::class)->join($s, $user, []);
        $svc = app(RecommendationService::class);

        $state = $ticket->fresh()->stateArray();
        $rem = $svc->remainingFor($ticket->fresh());
        $this->assertSame($state['wait_low'], $rem['minutes']);
        $this->assertLessThan($state['wait_minutes'], $rem['minutes']);

        // Called tickets get no suggestions.
        $ticket->forceFill(['status' => 'called'])->save();
        $this->assertSame(0, $svc->remainingFor($ticket->fresh())['minutes']);
        $this->assertSame([], $svc->recommend($user, $loc, 0)['items']);
    }

    public function test_dismissed_places_are_excluded_until_expiry_and_opt_out_is_respected(): void
    {
        $loc = $this->location();
        $user = User::factory()->create();
        $a = $this->place($loc, 'Cafe A', 150, 5);
        $b = $this->place($loc, 'Cafe B', 160, 5);
        $svc = app(RecommendationService::class);

        $this->actingAs($user)->postJson(route('explore.dismiss', $a))->assertOk()->assertJsonPath('data.place_id', $a->id);
        $ids = collect($svc->recommend($user, $loc, 60)['items'])->pluck('id');
        $this->assertFalse($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));

        // Expired dismissal no longer hides the place.
        ExploreDismissal::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);
        $this->assertTrue(collect($svc->recommend($user, $loc, 60)['items'])->pluck('id')->contains($a->id));

        // Opting out removes every suggestion (page and API).
        $this->actingAs($user)->post(route('explore.preferences.update'), ['suggestions_enabled' => '0'])->assertRedirect();
        $this->assertFalse(ExplorePreference::for($user)->suggestions_enabled);
        $rec = $svc->recommend($user, $loc, 60);
        $this->assertFalse($rec['enabled']);
        $this->assertSame([], $rec['items']);
        $this->actingAs($user)->getJson(route('explore.recommendations', ['location' => $loc->id, 'minutes' => 60]))
            ->assertOk()->assertJsonPath('data.enabled', false)->assertJsonCount(0, 'data.items');
    }

    public function test_category_preferences_filter_recommendations(): void
    {
        $loc = $this->location();
        $user = User::factory()->create();
        $cafe = $this->place($loc, 'Cafe', 150, 5);
        $park = $this->place($loc, 'Park', 150, 5, ['category' => 'Park']);

        $this->actingAs($user)->post(route('explore.preferences.update'), ['suggestions_enabled' => '1', 'categories' => ['park']])->assertRedirect();

        $ids = collect(app(RecommendationService::class)->recommend($user, $loc, 60)['items'])->pluck('id')->all();
        $this->assertSame([$park->id], $ids);
        $this->assertNotContains($cafe->id, $ids);

        $this->actingAs($user)->post(route('explore.preferences.update'), ['suggestions_enabled' => '1', 'categories' => ['casino']])
            ->assertSessionHasErrors('categories.0');
    }

    public function test_sponsored_places_never_appear_in_neutral_recommendations_and_partners_get_no_boost(): void
    {
        $loc = $this->location();
        $user = User::factory()->create();
        $sponsored = $this->place($loc, 'Paid Cafe', 50, 3, ['is_sponsored' => true, 'rating' => 5.0]);
        $partner = $this->place($loc, 'Partner Cafe', 400, 5, ['is_partner' => true, 'rating' => 4.0]);
        $neutral = $this->place($loc, 'Neutral Cafe', 100, 5, ['rating' => 4.6]);

        $rec = app(RecommendationService::class)->recommend($user, $loc, 60);
        $ids = collect($rec['items'])->pluck('id')->all();

        $this->assertNotContains($sponsored->id, $ids);
        $this->assertSame([$neutral->id, $partner->id], $ids, 'Closer, better-rated neutral place ranks above the partner');
        $this->assertTrue(collect($rec['items'])->every(fn ($i) => $i['sponsored'] === false));

        $json = $this->actingAs($user)->getJson(route('explore.recommendations', ['location' => $loc->id, 'minutes' => 60]))->assertOk();
        $this->assertNotContains($sponsored->id, collect($json->json('data.items'))->pluck('id')->all());
    }

    public function test_verified_closed_places_are_excluded_but_unverified_hours_are_never_shown_as_fact(): void
    {
        $loc = $this->location();
        $user = User::factory()->create();
        $svc = app(RecommendationService::class);
        $now = now(RecommendationService::TZ);
        $closedWindow = $now->copy()->addHours(2)->format('H:i').'-'.$now->copy()->addHours(3)->format('H:i');
        $closed = $this->place($loc, 'Closed Cafe', 150, 5, ['opening_hours' => $closedWindow, 'hours_verified' => true]);
        $unverified = $this->place($loc, 'Unverified Cafe', 150, 5, ['opening_hours' => $closedWindow, 'hours_verified' => false]);

        $ids = collect($svc->recommend($user, $loc, 60)['items'])->pluck('id');
        $this->assertFalse($ids->contains($closed->id));
        $this->assertTrue($ids->contains($unverified->id));
        $this->assertSame('Hours not verified', $svc->hours($unverified)['text']);
    }

    // ---------------- Pages & API ----------------

    public function test_explore_page_shows_time_banner_for_queued_user(): void
    {
        $loc = $this->location();
        $s = $this->service($loc);
        $user = User::factory()->create();
        $ticket = app(QueueManager::class)->join($s, $user, []);
        $this->place($loc, 'Banner Cafe', 150, 5);
        $low = $ticket->fresh()->stateArray()['wait_low'];

        $this->actingAs($user)->get(route('explore.index'))
            ->assertOk()
            ->assertSee('You have approximately')
            ->assertSee($low.' minutes')
            ->assertSee('Banner Cafe')
            ->assertSee('Not sponsored');
    }

    public function test_place_page_and_travel_api_label_estimates_honestly(): void
    {
        $loc = $this->location();
        $user = User::factory()->create();
        $p = $this->place($loc, 'Detail Cafe', 300, 5);

        $this->actingAs($user)->get(route('explore.place', $p))
            ->assertOk()->assertSee('Detail Cafe')->assertSee('Hours not verified')->assertSee('direct line, not a route');

        $this->actingAs($user)->getJson(route('explore.travel', $p))
            ->assertOk()
            ->assertJsonPath('data.method', 'straight_line')
            ->assertJsonPath('data.geometry', null)
            ->assertJsonPath('data.line_label', 'direct line, not a route');
    }

    public function test_hospital_page_shows_emergency_info_and_no_prescription_content(): void
    {
        $loc = $this->location('Hospital');
        $this->service($loc);
        $other = $this->location('Bank');
        (new ExploreSeeder)->run();
        $user = User::factory()->create();

        $this->assertNotNull(HospitalProfile::where('location_id', $loc->id)->first());
        $this->assertNull(HospitalProfile::where('location_id', $other->id)->first());

        $res = $this->actingAs($user)->get(route('explore.hospital', $loc))->assertOk();
        $res->assertSee('tel:102', false)->assertSee('tel:100', false)->assertSee('tel:101', false)
            ->assertSee('verify locally')
            ->assertSee('Go to the hospital Emergency desk now — do not wait in a queue for emergencies')
            ->assertSee('Demo data — confirm with hospital')
            ->assertSee('Laboratory services')
            ->assertSee('Nearby pharmacies')
            ->assertSee('Nearby transport');
        foreach (['dosage', 'prescribe', 'diagnosis:', 'take this medicine', 'tablets', ' mg '] as $bad) {
            $res->assertDontSee($bad);
        }

        // Non-hospital locations have no hospital page.
        $this->actingAs($user)->get(route('explore.hospital', $other))->assertNotFound();
    }

    public function test_seeder_covers_every_category_for_every_location_and_is_idempotent(): void
    {
        $locs = [$this->location(), $this->location('Hospital')];
        (new ExploreSeeder)->run();
        $count = NearbyPlace::count();
        (new ExploreSeeder)->run();
        $this->assertSame($count, NearbyPlace::count());

        $needed = ['cafe', 'restaurant', 'bookstore', 'shopping', 'park', 'pharmacy', 'atm', 'hospital', 'convenience', 'transport'];
        foreach ($locs as $loc) {
            $keys = NearbyPlace::where('location_id', $loc->id)->get()->map(fn ($p) => \App\Services\Explore\PlaceCategories::keyOf($p))->unique()->all();
            foreach ($needed as $k) {
                $this->assertContains($k, $keys, "Location {$loc->id} is missing {$k}");
            }
        }
        $this->assertSame(0, NearbyPlace::where('hours_verified', true)->whereNull('opening_hours')->count());
    }

    // ---------------- Security ----------------

    public function test_guests_are_redirected_to_login(): void
    {
        $loc = $this->location('Hospital');
        $p = $this->place($loc, 'Guest Cafe', 100, 5);

        $this->get(route('explore.index'))->assertRedirect(route('login'));
        $this->get(route('explore.place', $p))->assertRedirect(route('login'));
        $this->get(route('explore.preferences'))->assertRedirect(route('login'));
        $this->get(route('explore.hospital', $loc))->assertRedirect(route('login'));
        $this->getJson(route('explore.recommendations'))->assertUnauthorized();
        $this->post(route('explore.dismiss', $p))->assertRedirect(route('login'));
        $this->post(route('explore.preferences.update'), ['suggestions_enabled' => '0'])->assertRedirect(route('login'));
    }

    public function test_preferences_and_dismissals_cannot_be_read_or_changed_across_users(): void
    {
        $loc = $this->location();
        $p = $this->place($loc, 'Private Cafe', 100, 5);
        $alice = User::factory()->create();
        $mallory = User::factory()->create();

        $this->actingAs($alice)->postJson(route('explore.dismiss', $p))->assertOk();
        $dismissal = ExploreDismissal::where('user_id', $alice->id)->firstOrFail();

        // Mallory cannot restore (delete) Alice's dismissal.
        $this->actingAs($mallory)->deleteJson(route('explore.undismiss', $dismissal->id))->assertNotFound();
        $this->assertDatabaseHas('explore_dismissals', ['id' => $dismissal->id]);

        // A smuggled user_id is ignored: Mallory only ever changes her own preferences.
        $this->actingAs($mallory)->post(route('explore.preferences.update'), ['suggestions_enabled' => '0', 'user_id' => $alice->id]);
        $this->assertTrue(ExplorePreference::for($alice)->suggestions_enabled);
        $this->assertFalse(ExplorePreference::for($mallory)->suggestions_enabled);

        // Mallory's preference page doesn't show Alice's dismissals.
        $this->actingAs($mallory)->get(route('explore.preferences'))->assertOk()->assertDontSee('Private Cafe');
        $this->actingAs($alice)->get(route('explore.preferences'))->assertOk()->assertSee('Private Cafe');

        // Someone else's ticket id can't be used for recommendations context.
        $s = $this->service($loc);
        $aliceTicket = app(QueueManager::class)->join($s, $alice, []);
        $this->actingAs($mallory)->getJson(route('explore.recommendations', ['ticket' => $aliceTicket->id]))
            ->assertOk()->assertJsonPath('data.status', 'none');

        // Alice can restore her own.
        $this->actingAs($alice)->deleteJson(route('explore.undismiss', $dismissal->id))->assertOk();
        $this->assertDatabaseMissing('explore_dismissals', ['id' => $dismissal->id]);
    }

    public function test_widgets_render_safely(): void
    {
        $loc = $this->location('Hospital');
        $s = $this->service($loc);
        $user = User::factory()->create();
        $ticket = app(QueueManager::class)->join($s, $user, []);
        $this->place($loc, 'Widget Cafe', 120, 3);
        $this->actingAs($user);

        $html = view('explore.partials.ticket-widget', ['ticket' => $ticket->fresh()])->render();
        $this->assertStringContainsString('While you wait', $html);

        $banner = view('explore.partials.hospital-banner', ['ticket' => $ticket->fresh()])->render();
        $this->assertStringContainsString('tel:102', $banner);

        $this->assertSame('', trim(view('explore.partials.ticket-widget', ['ticket' => null])->render()));
        $this->assertSame('', trim(view('explore.partials.hospital-banner', ['location' => $this->location('Bank')])->render()));
    }
}
