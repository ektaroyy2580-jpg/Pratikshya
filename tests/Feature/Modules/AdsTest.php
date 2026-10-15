<?php

namespace Tests\Feature\Modules;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\Advertiser;
use App\Models\AdvertiserUser;
use App\Models\AdRewardClaim;
use App\Models\Location;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\Ads\AdServer;
use App\Services\Ads\AdSpend;
use App\Services\Ads\RewardedAdService;
use App\Services\Rewards\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------- helpers ----------------

    private function advertiser(string $status = 'approved', string $category = 'cafe', ?string $name = null): Advertiser
    {
        $a = new Advertiser(['name' => $name ?? 'Biz '.uniqid(), 'category' => $category, 'contact_email' => 'a@b.np']);
        $a->status = $status;
        $a->save();

        return $a;
    }

    private function member(Advertiser $a, string $role = 'owner'): User
    {
        $u = User::factory()->create();
        AdvertiserUser::create(['user_id' => $u->id, 'advertiser_id' => $a->id, 'role' => $role]);

        return $u;
    }

    private function campaign(Advertiser $a, array $attrs = [], string $status = 'active'): AdCampaign
    {
        $c = new AdCampaign(array_merge([
            'name' => 'Camp '.uniqid(), 'headline' => 'Fresh coffee nearby', 'body' => 'Ten percent off.',
            'cta_label' => 'Visit', 'cta_url' => '/find', 'creative_theme' => 'blue', 'placements' => ['explore', 'game_hub', 'post_game_reward'],
            'starts_on' => now()->subDay()->toDateString(), 'ends_on' => now()->addDays(10)->toDateString(),
            'budget_total' => 1000, 'daily_budget' => 0, 'cpm_rate' => 100, 'cpc_rate' => 5, 'allows_rewarded' => true,
        ], $attrs));
        $c->advertiser_id = $a->id;
        $c->status = $status;
        $c->is_healthcare = ! empty($attrs['healthcare_disclosure']);
        $c->approved_at = $status === 'active' ? now() : null;
        $c->save();

        return $c;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function formPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Spring promo', 'headline' => 'Hot momo near the office', 'body' => 'Ready in 10 minutes.',
            'cta_label' => 'See menu', 'cta_url' => '/find', 'creative_theme' => 'teal', 'placements' => ['explore', 'nearby_places'],
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(20)->toDateString(),
            'budget_total' => '5000.00', 'daily_budget' => '500', 'cpm_rate' => '120', 'cpc_rate' => '8', 'action' => 'draft',
        ], $overrides);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    // ---------------- portal authorization ----------------

    public function test_non_advertiser_cannot_access_portal_and_guest_must_log_in(): void
    {
        $this->get(route('ads.portal'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('ads.portal'))->assertForbidden();
        $this->get(route('ads.campaigns.create'))->assertForbidden();
    }

    public function test_advertiser_sees_only_own_campaigns_and_cannot_touch_another_advertisers(): void
    {
        $a = $this->advertiser(name: 'Alpha Cafe');
        $b = $this->advertiser(name: 'Beta Books');
        $userA = $this->member($a);
        $mine = $this->campaign($a, ['name' => 'Alpha campaign']);
        $theirs = $this->campaign($b, ['name' => 'Beta campaign']);

        $this->actingAs($userA)->get(route('ads.portal'))->assertOk()->assertSee('Alpha campaign')->assertDontSee('Beta campaign');
        $this->get(route('ads.campaigns.show', $mine))->assertOk();
        $this->get(route('ads.campaigns.show', $theirs))->assertForbidden();
        $this->get(route('ads.campaigns.edit', $theirs))->assertForbidden();
        $this->put(route('ads.campaigns.update', $theirs), $this->formPayload(['headline' => 'Hijacked']))->assertForbidden();
        $this->post(route('ads.campaigns.submit', $theirs))->assertForbidden();
        $this->post(route('ads.campaigns.pause', $theirs))->assertForbidden();
        $this->assertSame('Fresh coffee nearby', $theirs->fresh()->headline);
        $this->assertSame('active', $theirs->fresh()->status);

        // Cannot create a campaign for someone else's business either.
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $b->id]))->assertSessionHasErrors('advertiser_id');
        $this->assertSame(0, AdCampaign::where('name', 'Spring promo')->count());
    }

    public function test_viewer_role_member_cannot_edit(): void
    {
        $a = $this->advertiser();
        $viewer = $this->member($a, 'viewer');
        $c = $this->campaign($a);
        $this->actingAs($viewer)->get(route('ads.campaigns.show', $c))->assertOk();
        $this->get(route('ads.campaigns.edit', $c))->assertForbidden();
    }

    public function test_campaign_is_created_as_draft_submitted_to_review_and_status_cannot_be_injected(): void
    {
        $a = $this->advertiser();
        $u = $this->member($a);

        $this->actingAs($u)->post(route('ads.campaigns.store'), $this->formPayload([
            'advertiser_id' => $a->id, 'action' => 'submit', 'status' => 'active', 'is_demo' => 1, 'approved_at' => now(),
        ]))->assertRedirect();

        $c = AdCampaign::where('name', 'Spring promo')->firstOrFail();
        $this->assertSame('pending_review', $c->status);
        $this->assertFalse($c->is_demo);
        $this->assertNull($c->approved_at);
        $this->assertSame(['explore', 'nearby_places'], $c->placements);
        $this->assertSame('5000.00', $c->budget_total);

        // Editing a live campaign takes it out of rotation.
        $c->forceFill(['status' => 'active', 'approved_at' => now()])->save();
        $this->put(route('ads.campaigns.update', $c), $this->formPayload(['headline' => 'Updated headline']))->assertRedirect();
        $this->assertSame('draft', $c->fresh()->status);
        $this->assertNull($c->fresh()->approved_at);
    }

    public function test_pending_advertiser_cannot_submit_and_only_admin_approves(): void
    {
        $pending = $this->advertiser('pending');
        $u = $this->member($pending);
        $draft = $this->campaign($pending, [], 'draft');
        $this->actingAs($u)->post(route('ads.campaigns.submit', $draft))->assertSessionHasErrors('campaign');
        $this->assertSame('draft', $draft->fresh()->status);

        $a = $this->advertiser();
        $owner = $this->member($a);
        $c = $this->campaign($a, [], 'pending_review');

        // Advertisers, regular users and staff cannot approve.
        $this->actingAs($owner)->post(route('ads.admin.campaigns.review', $c), ['decision' => 'approve'])->assertForbidden();
        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'staff'])->save();
        $this->actingAs($staff)->post(route('ads.admin.campaigns.review', $c), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($owner)->post(route('ads.admin.advertisers.status', $pending), ['status' => 'approved'])->assertForbidden();
        $this->assertSame('pending_review', $c->fresh()->status);

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('ads.admin.campaigns.review', $c), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->post(route('ads.admin.campaigns.review', $c), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('active', $c->fresh()->status);
        $this->assertNotNull($c->fresh()->approved_at);

        // A campaign from an unapproved advertiser cannot be approved.
        $pendingCampaign = $this->campaign($pending, [], 'pending_review');
        $this->post(route('ads.admin.campaigns.review', $pendingCampaign), ['decision' => 'approve'])->assertSessionHasErrors('campaign');
        $this->assertSame('pending_review', $pendingCampaign->fresh()->status);

        $this->post(route('ads.admin.advertisers.status', $pending), ['status' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $pending->fresh()->status);
        $this->get(route('ads.admin'))->assertOk()->assertSee('Ads never affect queue order, document checks, medical priority or staff decisions.');
    }

    public function test_user_can_apply_to_advertise_and_starts_pending(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('ads.apply.store'), [
            'name' => 'Patan Momo Corner', 'category' => 'restaurant', 'contact_name' => 'Hari', 'contact_email' => 'hari@momo.np', 'accept_policy' => '1',
            'status' => 'approved',
        ])->assertRedirect(route('ads.portal'));
        $a = Advertiser::where('name', 'Patan Momo Corner')->firstOrFail();
        $this->assertSame('pending', $a->status);
        $this->assertTrue($a->hasMember($u));
        $this->get(route('ads.portal'))->assertOk()->assertSee('awaiting admin approval');
    }

    // ---------------- creative validation ----------------

    public function test_cta_url_must_be_internal_or_https(): void
    {
        $a = $this->advertiser();
        $u = $this->member($a);
        foreach (['javascript:alert(1)', '//evil.example', 'http://insecure.example', 'https://user:pw@evil.example', '/\\evil.example', 'data:text/html,hi'] as $bad) {
            $this->actingAs($u)->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $a->id, 'cta_url' => $bad]))
                ->assertSessionHasErrors('cta_url');
        }
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $a->id, 'cta_url' => 'https://himalayanbrew.example/menu']))
            ->assertSessionHasNoErrors();
    }

    public function test_creative_upload_is_validated_and_stored_with_random_name(): void
    {
        Storage::fake('public');
        $a = $this->advertiser();
        $u = $this->member($a);
        $this->actingAs($u);

        // Too large
        $big = UploadedFile::fake()->create('big.png', 1500, 'image/png');
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $a->id, 'creative_image' => $big]))->assertSessionHasErrors('creative_image');
        // Wrong type
        $gif = UploadedFile::fake()->create('anim.gif', 10, 'image/gif');
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $a->id, 'creative_image' => $gif]))->assertSessionHasErrors('creative_image');
        // Fake image: right extension, not an image
        $fake = UploadedFile::fake()->createWithContent('evil.png', '<?php echo "hi"; ?>');
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $a->id, 'creative_image' => $fake]))->assertSessionHasErrors('creative_image');
        $this->assertSame(0, AdCampaign::count());

        // Real PNG
        $ok = UploadedFile::fake()->createWithContent('My Shop Logo.png', $this->png());
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $a->id, 'creative_image' => $ok]))->assertSessionHasNoErrors();
        $c = AdCampaign::firstOrFail();
        $this->assertMatchesRegularExpression('#^ads/[a-f0-9]{32}\.png$#', $c->creative_image);
        Storage::disk('public')->assertExists($c->creative_image);
    }

    public function test_healthcare_ads_require_disclosure_and_cannot_target_queue_pages(): void
    {
        $pharmacy = $this->advertiser('approved', 'pharmacy');
        $u = $this->member($pharmacy);
        $this->actingAs($u)->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $pharmacy->id]))
            ->assertSessionHasErrors('healthcare_disclosure');
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $pharmacy->id, 'healthcare_disclosure' => 'Not medical advice.', 'placements' => ['queue_page']]))
            ->assertSessionHasErrors('placements');
        $this->post(route('ads.campaigns.store'), $this->formPayload(['advertiser_id' => $pharmacy->id, 'healthcare_disclosure' => 'Not medical advice.']))
            ->assertSessionHasNoErrors();
        $this->assertTrue(AdCampaign::firstOrFail()->is_healthcare);
    }

    // ---------------- delivery ----------------

    public function test_slot_renders_clearly_labelled_sponsored_card_with_healthcare_disclosure(): void
    {
        $a = $this->advertiser('approved', 'pharmacy', 'Sajilo Test Pharmacy');
        $this->campaign($a, ['headline' => 'Open late tonight', 'healthcare_disclosure' => 'Not medical advice.', 'target_category' => 'pharmacy']);

        $html = view('ads.partials.slot', ['placement' => 'explore'])->render();
        $this->assertStringContainsString('Sponsored', $html);
        $this->assertStringContainsString('Open late tonight', $html);
        $this->assertStringContainsString('Healthcare ad.', $html);
        $this->assertStringContainsString('Not medical advice.', $html);
        $this->assertStringContainsString('rel="sponsored nofollow noopener"', $html);
        $this->assertStringContainsString('data-ad-token=', $html);

        // Nothing is rendered on critical queue moments or for other placements.
        $this->assertSame('', trim(view('ads.partials.slot', ['placement' => 'explore', 'context' => ['ticket' => (object) ['status' => 'called']]])->render()));
        $this->assertSame('', trim(view('ads.partials.slot', ['placement' => 'queue_page'])->render()));
        $this->assertSame('', trim(view('ads.partials.slot', ['placement' => 'explore', 'context' => ['suppress' => true]])->render()));
        config(['ads.enabled' => false]);
        $this->assertSame('', trim(view('ads.partials.slot', ['placement' => 'explore'])->render()));
    }

    public function test_inactive_expired_future_over_budget_and_untargeted_campaigns_are_not_served(): void
    {
        $server = app(AdServer::class);
        $a = $this->advertiser();
        $loc = Location::create(['name' => 'Ward Office', 'area' => 'Patan', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.6, 'lng' => 85.3]);

        $this->campaign($a, [], 'paused');
        $this->campaign($a, [], 'pending_review');
        $this->campaign($a, [], 'rejected');
        $this->campaign($a, ['starts_on' => now()->subDays(20)->toDateString(), 'ends_on' => now()->subDay()->toDateString()]);
        $this->campaign($a, ['starts_on' => now()->addDay()->toDateString(), 'ends_on' => now()->addDays(5)->toDateString()]);
        $this->campaign($this->advertiser('suspended'));
        $this->campaign($a, ['placements' => ['game_hub']]);
        $this->campaign($a, ['target_location_id' => $loc->id]);
        $this->campaign($a, ['budget_total' => 0]);
        $spent = $this->campaign($a, ['budget_total' => 100, 'cpc_rate' => 10]);
        for ($i = 0; $i < 10; $i++) {
            AdEvent::create(['campaign_id' => $spent->id, 'placement' => 'explore', 'type' => 'click', 'session_hash' => 'h'.$i]);
        }
        $daily = $this->campaign($a, ['budget_total' => 1000, 'daily_budget' => 20, 'cpc_rate' => 10]);
        AdEvent::create(['campaign_id' => $daily->id, 'placement' => 'explore', 'type' => 'click', 'session_hash' => 'x1']);
        AdEvent::create(['campaign_id' => $daily->id, 'placement' => 'explore', 'type' => 'click', 'session_hash' => 'x2']);

        $this->assertTrue(app(AdSpend::class)->overBudget($spent));
        $this->assertSame(10000, app(AdSpend::class)->spentPaisa($spent));
        $this->assertNull($server->pick('explore'));
        $this->assertCount(0, $server->eligible('explore'));

        // The location-targeted one is served only in its location's context; a valid campaign is served.
        $this->assertSame($loc->id, $server->pick('explore', ['location_id' => $loc->id])->target_location_id);
        $ok = $this->campaign($a, ['target_category' => 'cafe']);
        $this->assertSame($ok->id, $server->pick('explore', ['category' => 'Cafe'])->id);
        $this->assertNull($server->pick('explore', ['category' => 'bookstore']));
    }

    public function test_frequency_cap_stops_serving_to_same_viewer(): void
    {
        config(['ads.delivery.frequency_cap_per_day' => 2]);
        $u = User::factory()->create();
        $this->actingAs($u);
        $server = app(AdServer::class);
        $c = $this->campaign($this->advertiser());
        $hash = $server->viewerHash($u);
        AdEvent::create(['campaign_id' => $c->id, 'placement' => 'explore', 'type' => 'impression', 'user_id' => $u->id, 'session_hash' => $hash]);
        $this->assertNotNull($server->pick('explore'));
        AdEvent::create(['campaign_id' => $c->id, 'placement' => 'game_hub', 'type' => 'impression', 'user_id' => $u->id, 'session_hash' => $hash]);
        $this->assertNull($server->pick('explore'));
    }

    public function test_impressions_are_deduplicated_per_viewer_campaign_and_placement_for_30_minutes(): void
    {
        $u = User::factory()->create();
        $c = $this->campaign($this->advertiser());
        $server = app(AdServer::class);

        $this->actingAs($u)->postJson(route('ads.impression'), ['t' => $server->slotToken($c, 'explore')])->assertOk()->assertJsonPath('data.counted', true);
        $this->postJson(route('ads.impression'), ['t' => $server->slotToken($c, 'explore')])->assertOk()->assertJsonPath('data.counted', false);
        $this->postJson(route('ads.impression'), ['t' => $server->slotToken($c, 'game_hub')])->assertJsonPath('data.counted', true);
        $this->assertSame(2, AdEvent::where('type', 'impression')->count());

        $this->travel(31)->minutes();
        $this->postJson(route('ads.impression'), ['t' => $server->slotToken($c, 'explore')])->assertJsonPath('data.counted', true);
        $this->assertSame(3, AdEvent::where('type', 'impression')->count());

        // Forged / tampered / expired tokens are ignored.
        $token = $server->slotToken($c, 'explore');
        $this->postJson(route('ads.impression'), ['t' => preg_replace('/^\d+/', '999', $token)])->assertStatus(422);
        $this->postJson(route('ads.impression'), ['t' => 'garbage'])->assertStatus(422);
        $this->travel(2)->hours();
        $this->postJson(route('ads.impression'), ['t' => $token])->assertStatus(422);
        $this->assertSame(3, AdEvent::where('type', 'impression')->count());
    }

    public function test_click_is_logged_then_redirects_safely(): void
    {
        $a = $this->advertiser();
        $internal = $this->campaign($a, ['cta_url' => '/find']);
        $external = $this->campaign($a, ['cta_url' => 'https://example.com/menu']);

        $this->get(URL::signedRoute('ads.click', ['campaign' => $internal->id, 'placement' => 'explore']))->assertRedirect(url('/find'));
        $this->assertSame(1, AdEvent::where('campaign_id', $internal->id)->where('type', 'click')->count());
        $this->get(URL::signedRoute('ads.click', ['campaign' => $external->id, 'placement' => 'explore']))->assertRedirect('https://example.com/menu');

        // Unsigned / tampered links are refused; no destination can be supplied by the request.
        $this->get(route('ads.click', ['campaign' => $internal->id, 'placement' => 'explore']))->assertForbidden();
        $this->get(URL::signedRoute('ads.click', ['campaign' => $internal->id, 'placement' => 'explore']).'&to=https://evil.example')->assertForbidden();

        // A destination corrupted in the DB is never followed.
        $internal->forceFill(['cta_url' => '//evil.example'])->saveQuietly();
        $this->get(URL::signedRoute('ads.click', ['campaign' => $internal->id, 'placement' => 'explore']))->assertRedirect(route('home'));
        // Inactive campaigns don't log clicks.
        $external->forceFill(['status' => 'paused'])->save();
        $this->get(URL::signedRoute('ads.click', ['campaign' => $external->id, 'placement' => 'game_hub']))->assertRedirect(route('home'));
        $this->assertSame(0, AdEvent::where('campaign_id', $external->id)->where('placement', 'game_hub')->count());
    }

    // ---------------- rewarded ads ----------------

    private function startClaim(User $u, string $reward = 'points'): int
    {
        return $this->actingAs($u)->postJson(route('ads.rewarded.start'), ['reward_type' => $reward, 'placement' => 'game_hub'])
            ->assertOk()->json('data.claim_id');
    }

    public function test_rewarded_completion_is_rejected_when_too_fast_and_granted_after_server_time(): void
    {
        $c = $this->campaign($this->advertiser());
        $u = User::factory()->create();
        $id = $this->startClaim($u);

        $this->postJson(route('ads.rewarded.complete', $id))->assertStatus(422);
        $this->travel(10)->seconds();
        $this->postJson(route('ads.rewarded.complete', $id))->assertStatus(422);
        // Re-starting does not reset the server timer (resumes the same claim).
        $this->assertSame($id, $this->startClaim($u));
        $this->assertSame(0, app(RewardService::class)->balance($u));

        $this->travel(6)->seconds();
        $this->postJson(route('ads.rewarded.complete', $id))->assertOk()->assertJsonPath('data.reward_type', 'points');
        $this->assertSame(10, app(RewardService::class)->balance($u));
        $this->assertDatabaseHas('reward_transactions', ['user_id' => $u->id, 'reference_key' => 'adreward:'.$id, 'source' => 'rewarded_ad']);
        $this->assertSame(1, AdEvent::where('campaign_id', $c->id)->where('type', 'reward_complete')->count());
    }

    public function test_rewarded_replay_and_other_users_claim_are_rejected_and_reward_granted_once(): void
    {
        $this->campaign($this->advertiser());
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $id = $this->startClaim($owner);
        $this->travel(20)->seconds();

        $this->actingAs($intruder)->postJson(route('ads.rewarded.complete', $id))->assertNotFound();
        $this->assertSame('started', AdRewardClaim::find($id)->status);

        $this->actingAs($owner)->postJson(route('ads.rewarded.complete', $id))->assertOk();
        $this->postJson(route('ads.rewarded.complete', $id))->assertStatus(409);
        $this->assertSame(1, RewardTransaction::where('reference_key', 'adreward:'.$id)->count());
        $this->assertSame(10, app(RewardService::class)->balance($owner));

        // Same sponsor cannot be claimed again the same day.
        $this->postJson(route('ads.rewarded.start'), ['reward_type' => 'points'])->assertStatus(404);
    }

    public function test_rewarded_daily_limit_expiry_and_non_point_rewards(): void
    {
        config(['ads.rewarded.daily_limit' => 1]);
        $a = $this->advertiser();
        $this->campaign($a);
        $this->campaign($a);
        $u = User::factory()->create();
        $svc = app(RewardedAdService::class);

        $id = $this->startClaim($u, 'extra_round');
        $this->travel(16)->seconds();
        $this->postJson(route('ads.rewarded.complete', $id))->assertOk();
        $this->assertSame(1, $svc->extraRoundsAvailable($u));
        $this->assertTrue($svc->consumeExtraRound($u));
        $this->assertFalse($svc->consumeExtraRound($u));
        $this->assertSame(0, app(RewardService::class)->balance($u)); // no points for an extra round

        $this->postJson(route('ads.rewarded.start'), ['reward_type' => 'cosmetic'])->assertStatus(429);

        // Expired claims cannot be completed.
        config(['ads.rewarded.daily_limit' => 5]);
        $other = User::factory()->create();
        $late = $this->startClaim($other, 'cosmetic');
        $this->travel(11)->minutes();
        $this->postJson(route('ads.rewarded.complete', $late))->assertStatus(410);
        $this->assertSame('expired', AdRewardClaim::find($late)->status);
        $this->assertCount(0, $svc->badges($other));
    }

    public function test_rewarded_ads_require_auth_and_an_opted_in_campaign(): void
    {
        $this->postJson(route('ads.rewarded.start'), ['reward_type' => 'points'])->assertUnauthorized();
        $this->campaign($this->advertiser(), ['allows_rewarded' => false]);
        $u = User::factory()->create();
        $this->actingAs($u)->postJson(route('ads.rewarded.start'), ['reward_type' => 'points'])->assertStatus(404);
        $this->postJson(route('ads.rewarded.start'), ['reward_type' => 'free_money'])->assertStatus(422);
        $this->get(route('ads.rewarded'))->assertOk()->assertSee('No sponsored video available');
    }

    public function test_provider_callback_rejects_everything_unless_configured(): void
    {
        $this->campaign($this->advertiser());
        $u = User::factory()->create();
        $id = $this->startClaim($u);
        $body = ['claim_id' => $id, 'user_id' => $u->id, 'transaction_id' => 'tx-1', 'timestamp' => now()->timestamp];

        $this->postJson(route('ads.provider.callback'), $body)->assertForbidden();
        $this->postJson(route('ads.provider.callback'), $body, ['X-Ad-Signature' => 'deadbeef'])->assertForbidden();
        $this->assertSame('started', AdRewardClaim::find($id)->status);

        config(['ads.provider' => 'network', 'ads.providers.network.callback_secret' => 's3cret']);
        $this->postJson(route('ads.provider.callback'), $body, ['X-Ad-Signature' => 'deadbeef'])->assertForbidden();
        $raw = json_encode($body);
        $sig = hash_hmac('sha256', $raw, 's3cret');
        $this->call('POST', route('ads.provider.callback'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_AD_SIGNATURE' => $sig], $raw)->assertOk();
        $this->assertSame('provider', AdRewardClaim::find($id)->verified_by);
        $this->assertSame(10, app(RewardService::class)->balance($u));
    }
}
