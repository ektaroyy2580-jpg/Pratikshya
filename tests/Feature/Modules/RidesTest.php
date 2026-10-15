<?php

namespace Tests\Feature\Modules;

use App\Models\Counter;
use App\Models\Location;
use App\Models\RideRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\Queue\QueueManager;
use App\Services\Rides\FareEstimator;
use App\Services\Rides\Providers\RideProviderRegistry;
use App\Services\Rides\RidePlanner;
use App\Services\Rides\TravelEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RidesTest extends TestCase
{
    use RefreshDatabase;

    // Ratna Park → Tripureshwor-ish points (~1.6 km apart) and a farther pickup (Kalanki).
    private const RATNA = [27.7063, 85.3151];

    private const KALANKI = [27.6933, 85.2817];

    private function office(): Location
    {
        return Location::create(['name' => 'Passport Office', 'area' => 'Tripureshwor', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.6946, 'lng' => 85.3135]);
    }

    private function service(?Location $loc = null): Service
    {
        $loc ??= $this->office();
        $s = $loc->services()->create(['name' => 'Passport Renewal', 'prefix' => 'P', 'counter' => 1, 'avg_minutes' => 5, 'current_number' => 10, 'last_number' => 30, 'documents' => []]);
        Counter::create(['location_id' => $loc->id, 'service_id' => $s->id, 'number' => 1, 'label' => 'Counter 1']);

        return $s;
    }

    private function payload(Location $loc, array $over = []): array
    {
        return array_merge([
            'pickup_lat' => self::KALANKI[0], 'pickup_lng' => self::KALANKI[1], 'pickup_label' => 'Kalanki',
            'destination_lat' => $loc->lat, 'destination_lng' => $loc->lng, 'destination_label' => $loc->name,
            'location_id' => $loc->id,
            'provider' => 'pathao', 'vehicle_type' => 'bike',
        ], $over);
    }

    private function ride(User $user, Location $loc, array $over = []): RideRequest
    {
        $id = $this->actingAs($user)->postJson(route('rides.store'), $this->payload($loc, $over))->assertCreated()->json('data.id');

        return RideRequest::findOrFail($id);
    }

    // ---------------- Estimation arithmetic ----------------

    public function test_fare_estimate_arithmetic_and_range_rounding(): void
    {
        config(['rides.range' => ['low' => 0.9, 'high' => 1.2]]);
        $model = ['base' => 50, 'per_km' => 20, 'per_min' => 2, 'minimum' => 80];
        // 50 + 20×5.2 + 2×17 = 188 NPR → low 169.2 → 165 ; high 225.6 → 230
        $f = app(FareEstimator::class)->estimate($model, 5.2, 17);
        $this->assertSame(['low' => 165, 'high' => 230, 'point' => 188], $f);

        // Short trip falls back to the minimum and the range never dips below it.
        $short = app(FareEstimator::class)->estimate($model, 0.3, 1);
        $this->assertSame(80, $short['point']);
        $this->assertSame(80, $short['low']);
        $this->assertSame(100, $short['high']); // 96 → rounded up to 100
        $this->assertSame(0, $short['high'] % 5);
    }

    public function test_every_provider_quote_is_an_estimate_with_a_sane_range(): void
    {
        $plan = app(RidePlanner::class)->plan(self::KALANKI[0], self::KALANKI[1], 27.6946, 85.3135);
        $this->assertNotEmpty($plan['quotes']);
        $this->assertEqualsCanonicalizing(['indrive', 'yango', 'pathao'], array_values(array_unique(array_column($plan['quotes'], 'provider'))));
        foreach ($plan['quotes'] as $q) {
            $this->assertSame('estimate', $q['source']);
            $this->assertFalse($q['supports_live_booking']);
            $this->assertGreaterThan(0, $q['fare_low']);
            $this->assertGreaterThanOrEqual($q['fare_low'], $q['fare_high']);
        }
        // Comfort is pricier than an Economy car on the same trip.
        $yango = collect($plan['quotes'])->where('provider', 'yango')->keyBy('vehicle_type');
        $this->assertGreaterThan($yango['car']['fare_low'], $yango['comfort']['fare_low']);
        $this->assertSame('estimate', $plan['routing']);
    }

    public function test_mode_time_calculation_uses_road_factor_and_speeds(): void
    {
        $t = app(TravelEstimator::class);
        // 3 km straight → 3.9 km road
        $modes = $t->modes(3.9);
        $this->assertSame(52, $modes['walking']['minutes']);   // 3.9/4.5×60 = 52
        $this->assertSame(11, $modes['bike']['minutes']);      // 10.6 → 11
        $this->assertSame(13, $modes['car']['minutes']);       // 13.0
        $this->assertSame(30, $modes['transit']['minutes']);   // 19.5 → 20 + 10 wait
        $this->assertTrue($modes['car']['approximate']);

        $route = $t->route(27.70, 85.30, 27.70, 85.33);
        $this->assertEqualsWithDelta($route['straight_km'] * 1.3, $route['road_km'], 0.02);
        $this->assertNull($route['geometry']);
    }

    // ---------------- Quote endpoint & validation ----------------

    public function test_quote_endpoint_returns_message_data_json_and_works_without_ticket(): void
    {
        $loc = $this->office();
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson(route('rides.quote'), $this->payload($loc))->assertOk();
        $res->assertJsonStructure(['message', 'data' => ['straight_km', 'road_km', 'modes', 'quotes', 'recommend', 'leave_by']]);
        $this->assertNull($res->json('data.leave_by'));
        $this->assertSame('car', $res->json('data.recommend.mode')); // Kalanki is far from Tripureshwor

        $this->actingAs($user)->get(route('rides.plan', ['location' => $loc->id]))->assertOk()->assertSee('Estimate')->assertSee($loc->name);
        $this->actingAs($user)->get(route('rides.plan'))->assertOk(); // no destination, no ticket
    }

    public function test_coordinates_are_validated(): void
    {
        $loc = $this->office();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('rides.quote'), $this->payload($loc, ['pickup_lat' => 'abc']))->assertStatus(422)->assertJsonValidationErrors('pickup_lat');
        $this->actingAs($user)->postJson(route('rides.quote'), $this->payload($loc, ['pickup_lat' => 51.5, 'pickup_lng' => -0.12]))->assertStatus(422)->assertJsonValidationErrors(['pickup_lat', 'pickup_lng']);
        $this->actingAs($user)->postJson(route('rides.quote'), $this->payload($loc, ['pickup_lat' => $loc->lat, 'pickup_lng' => $loc->lng]))->assertStatus(422)->assertJsonValidationErrors('pickup_lat');
        $this->actingAs($user)->postJson(route('rides.quote'), $this->payload($loc, ['location_id' => 99999]))->assertStatus(422)->assertJsonValidationErrors('location_id');
        $this->actingAs($user)->postJson(route('rides.store'), $this->payload($loc, ['provider' => 'uber']))->assertStatus(422)->assertJsonValidationErrors('provider');
        $this->actingAs($user)->postJson(route('rides.store'), $this->payload($loc, ['provider' => 'indrive', 'vehicle_type' => 'comfort']))->assertStatus(422);
        $this->assertSame(0, RideRequest::count());

        $this->app['auth']->forgetGuards();
        $this->postJson(route('rides.quote'), $this->payload($loc))->assertUnauthorized();
        $this->get(route('rides.plan'))->assertRedirect(route('login'));
    }

    public function test_store_recomputes_fare_server_side_and_ignores_client_values(): void
    {
        $loc = $this->office();
        $user = User::factory()->create();
        $ride = $this->ride($user, $loc, ['fare_low' => 1, 'fare_high' => 2, 'status' => 'demo_completed', 'is_demo' => 1, 'user_id' => 999]);

        $this->assertSame($user->id, $ride->user_id);
        $this->assertSame('quoted', $ride->status);
        $this->assertFalse($ride->is_demo);
        $this->assertSame('estimate', $ride->fare_source);
        $this->assertGreaterThanOrEqual(75, (float) $ride->fare_low); // Pathao bike minimum
        $this->assertNull($ride->ticket_id); // works without a ticket
    }

    // ---------------- Hand-off (never claims a booking) ----------------

    public function test_handoff_records_intent_and_never_claims_booking(): void
    {
        $loc = $this->office();
        $user = User::factory()->create();
        $ride = $this->ride($user, $loc);

        $this->actingAs($user)->post(route('rides.handoff', $ride))->assertRedirect('https://pathao.com/np/');
        $ride->refresh();
        $this->assertSame('handed_off', $ride->status);
        $this->assertFalse($ride->is_demo);
        $this->assertNull($ride->external_reference);
        $this->assertSame('estimate', $ride->fare_source);

        $json = $this->actingAs($user)->getJson(route('rides.state', $ride))->assertOk();
        $this->assertSame('handed_off', $json->json('data.status'));
        $this->assertFalse($json->json('data.live'));
        $this->assertStringContainsString('confirmed in the Pathao app', $json->json('data.detail'));

        $page = $this->actingAs($user)->get(route('rides.show', $ride))->assertOk();
        $page->assertSee('Fare and booking are confirmed in the Pathao app');
        $page->assertDontSee('Ride booked');
        $page->assertDontSee('Booking confirmed');
    }

    public function test_providers_never_support_live_booking_and_book_throws(): void
    {
        foreach (app(RideProviderRegistry::class)->all() as $p) {
            $this->assertFalse($p->supportsLiveBooking());
            $this->assertNull(config("rides.providers.{$p->key()}.deep_link"));
        }
        $this->expectException(\App\Services\Rides\Providers\RideProviderException::class);
        app(RideProviderRegistry::class)->get('yango')->book(new RideRequest);
    }

    // ---------------- Demo booking ----------------

    public function test_demo_booking_is_labelled_progresses_and_never_confirmed(): void
    {
        $loc = $this->office();
        $user = User::factory()->create();
        $ride = $this->ride($user, $loc, ['provider' => 'yango', 'vehicle_type' => 'car']);

        $this->actingAs($user)->post(route('rides.demo', $ride))->assertRedirect(route('rides.show', $ride));
        $ride->refresh();
        $this->assertTrue($ride->is_demo);
        $this->assertSame('demo_requested', $ride->status);
        $this->assertStringStartsWith('DEMO-', $ride->external_reference);

        $this->actingAs($user)->get(route('rides.show', $ride))->assertOk()->assertSee('DEMO — no real driver/vehicle');

        $seen = [];
        foreach ([0, 12, 30, 70, 200] as $sec) {
            $this->travelTo($ride->demo_started_at->copy()->addSeconds($sec));
            $res = $this->actingAs($user)->getJson(route('rides.state', $ride))->assertOk();
            $this->assertSame('DEMO — no real driver/vehicle', $res->json('message'));
            $this->assertTrue($res->json('data.demo'));
            $this->assertFalse($res->json('data.live'));
            $seen[] = $res->json('data.status');
            $this->assertNotSame('confirmed', $ride->fresh()->status);
        }
        $this->assertSame(['demo_requested', 'demo_driver_assigned', 'demo_arriving', 'demo_on_trip', 'demo_completed'], $seen);
        $this->assertSame('demo_completed', $ride->fresh()->status);
        $this->assertTrue(collect(RideRequest::pluck('status'))->every(fn ($s) => in_array($s, RideRequest::STATUSES, true)));

        // A finished demo can't be cancelled or re-run.
        $this->actingAs($user)->postJson(route('rides.cancel', $ride))->assertStatus(422);
        $this->actingAs($user)->postJson(route('rides.demo', $ride))->assertStatus(422);
    }

    public function test_demo_ride_can_be_cancelled_by_owner(): void
    {
        $loc = $this->office();
        $user = User::factory()->create();
        $ride = $this->ride($user, $loc);
        $this->actingAs($user)->postJson(route('rides.demo', $ride))->assertOk()->assertJsonPath('data.is_demo', true);

        $this->actingAs($user)->postJson(route('rides.cancel', $ride))->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('cancelled', $ride->fresh()->status);
        $this->assertNotNull($ride->fresh()->cancelled_at);
    }

    // ---------------- Authorization / IDOR ----------------

    public function test_users_cannot_view_or_change_other_peoples_rides(): void
    {
        $loc = $this->office();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $ride = $this->ride($owner, $loc);

        foreach ([$other, $admin] as $intruder) {
            $this->actingAs($intruder)->get(route('rides.show', $ride))->assertForbidden();
            $this->actingAs($intruder)->getJson(route('rides.state', $ride))->assertForbidden();
            $this->actingAs($intruder)->postJson(route('rides.cancel', $ride))->assertForbidden();
            $this->actingAs($intruder)->postJson(route('rides.demo', $ride))->assertForbidden();
            $this->actingAs($intruder)->post(route('rides.handoff', $ride))->assertForbidden();
            $this->actingAs($intruder)->postJson(route('rides.carpool', $ride), ['consent_share' => true, 'acknowledge' => true])->assertForbidden();
        }
        $this->assertSame('quoted', $ride->fresh()->status);
        $this->assertFalse($ride->fresh()->consent_share);
        $this->actingAs($other)->get(route('rides.plan'))->assertOk()->assertDontSee(route('rides.show', $ride));
    }

    // ---------------- Carpool consent & privacy ----------------

    public function test_carpool_is_off_by_default_and_requires_explicit_consent(): void
    {
        $loc = $this->office();
        $user = User::factory()->create();
        $ride = $this->ride($user, $loc);
        $this->assertFalse($ride->consent_share);
        $this->assertNull(app(RidePlanner::class)->carpoolMatches($ride));

        // Turning on without acknowledging what is shared is rejected.
        $this->actingAs($user)->postJson(route('rides.carpool', $ride), ['consent_share' => true])->assertStatus(422)->assertJsonValidationErrors('acknowledge');
        $this->assertFalse($ride->fresh()->consent_share);

        $this->actingAs($user)->postJson(route('rides.carpool', $ride), ['consent_share' => true, 'acknowledge' => true])->assertOk()->assertJsonPath('data.consent_share', true);
        $this->assertTrue($ride->fresh()->consent_share);

        $this->actingAs($user)->postJson(route('rides.carpool', $ride), ['consent_share' => false])->assertOk();
        $this->assertFalse($ride->fresh()->consent_share);
    }

    public function test_carpool_counts_only_opted_in_others_and_hides_identities(): void
    {
        $loc = $this->office();
        $me = User::factory()->create(['name' => 'Me Person']);
        $optedNear = User::factory()->create(['name' => 'Sita Secret', 'email' => 'sita.secret@example.test']);
        $optedFar = User::factory()->create(['name' => 'Far Friend']);
        $notOpted = User::factory()->create(['name' => 'Ram Private']);

        $mine = $this->ride($me, $loc, ['consent_share' => true]);
        $this->ride($optedNear, $loc, ['consent_share' => true, 'pickup_lat' => self::KALANKI[0] + 0.004, 'pickup_lng' => self::KALANKI[1] + 0.004]);
        $this->ride($optedFar, $loc, ['consent_share' => true, 'pickup_lat' => 27.7347, 'pickup_lng' => 85.3460]);
        $this->ride($notOpted, $loc, ['consent_share' => false, 'pickup_lat' => self::KALANKI[0], 'pickup_lng' => self::KALANKI[1]]);

        $res = $this->actingAs($me)->getJson(route('rides.state', $mine))->assertOk();
        $this->assertSame(['same_destination' => 2, 'nearby_pickup' => 1], $res->json('data.carpool'));
        $body = $res->getContent();
        foreach (['Sita Secret', 'sita.secret', 'Far Friend', 'Ram Private'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
        $page = $this->actingAs($me)->get(route('rides.show', $mine))->assertOk();
        $page->assertDontSee('Sita Secret')->assertDontSee('Ram Private');

        // Someone who did not opt in sees no matches at all.
        $theirs = RideRequest::where('user_id', $notOpted->id)->first();
        $this->assertNull($this->actingAs($notOpted)->getJson(route('rides.state', $theirs))->json('data.carpool'));
    }

    // ---------------- Queue awareness (read-only) ----------------

    public function test_leave_by_uses_active_ticket_and_never_changes_queue(): void
    {
        $s = $this->service();
        $user = User::factory()->create();
        $ticket = app(QueueManager::class)->join($s, $user);
        $before = [$ticket->number, $ticket->status, $s->fresh()->current_number, $s->fresh()->last_number];

        $res = $this->actingAs($user)->postJson(route('rides.quote'), $this->payload($s->location))->assertOk();
        $car = $res->json('data.leave_by.car');
        $this->assertNotNull($car);
        $state = $ticket->fresh()->stateArray();
        $carMinutes = collect($res->json('data.modes'))->firstWhere('key', 'car')['minutes'];
        $this->assertSame($state['wait_low'], $car['turn_low']);
        $this->assertSame(max(0, $state['wait_low'] - $carMinutes - 10), $car['leave_in']);

        $ride = $this->ride($user, $s->location);
        $this->assertSame($ticket->id, $ride->ticket_id);
        $this->actingAs($user)->post(route('rides.demo', $ride));

        $t = $ticket->fresh();
        $this->assertSame($before, [$t->number, $t->status, $s->fresh()->current_number, $s->fresh()->last_number]);

        // Destination prefilled from the active ticket's office.
        $this->actingAs($user)->get(route('rides.plan'))->assertOk()->assertSee('Passport Office')->assertSee('Leave by');
    }

    public function test_ticket_of_another_user_is_never_linked(): void
    {
        $s = $this->service();
        $other = User::factory()->create();
        app(QueueManager::class)->join($s, $other);
        $me = User::factory()->create();

        $ride = $this->ride($me, $s->location);
        $this->assertNull($ride->ticket_id);
    }

    public function test_widgets_render_safely_with_and_without_data(): void
    {
        $s = $this->service();
        $user = User::factory()->create();
        $this->actingAs($user);

        $html = view('rides.partials.service-widget', ['service' => $s])->render();
        $this->assertStringContainsString(route('rides.plan', ['service' => $s->id]), $html);
        $this->assertSame('', trim(view('rides.partials.service-widget', ['service' => null])->render()));

        $ticket = app(QueueManager::class)->join($s, $user);
        $this->assertStringContainsString('Getting there', view('rides.partials.ticket-widget', ['ticket' => $ticket])->render());
        $this->assertSame('', trim(view('rides.partials.ticket-widget', ['ticket' => null])->render()));
    }
}
