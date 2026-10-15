<?php

namespace Tests\Feature\Modules;

use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Models\Organization;
use App\Models\RewardCatalogueItem;
use App\Models\RewardRedemption;
use App\Models\RewardTransaction;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserPreference;
use App\Notifications\QueueUpdate;
use App\Services\Queue\QueueManager;
use App\Services\Rewards\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $name = 'Org'): Organization
    {
        return Organization::create(['name' => $name, 'slug' => Str::slug($name).'-'.uniqid(), 'type' => 'government', 'settings' => ['allow_queue_exchange' => true]]);
    }

    private function location(Organization $org): Location
    {
        $loc = new Location;
        $loc->forceFill(['name' => 'Office '.uniqid(), 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3, 'organization_id' => $org->id])->save();

        return $loc;
    }

    private function service(Location $loc): Service
    {
        $s = $loc->services()->create(['name' => 'Licence', 'prefix' => 'D', 'counter' => 1, 'avg_minutes' => 4, 'current_number' => 0, 'last_number' => 0, 'documents' => ['ID']]);
        Counter::create(['location_id' => $loc->id, 'service_id' => $s->id, 'number' => 1, 'label' => 'Counter 1']);

        return $s;
    }

    private function orgAdmin(Organization $org): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'staff'])->save();
        DB::table('organization_user')->insert(['organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'org_admin', 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function serviceForm(array $o = []): array
    {
        return array_merge(['name' => 'Renewal', 'prefix' => 'r', 'counter' => 2, 'avg_minutes' => 6, 'documents_text' => "Old licence\nCitizenship copy\n\nOld licence", 'is_active' => '1', 'is_paused' => '0', 'allow_exchange' => '1', 'requires_all_documents' => '0'], $o);
    }

    // ---------------- Organization scoping ----------------

    public function test_org_admin_manages_only_own_organization(): void
    {
        $mine = $this->org('Mine');
        $other = $this->org('Other');
        $myLoc = $this->location($mine);
        $otherLoc = $this->location($other);
        $mySvc = $this->service($myLoc);
        $otherSvc = $this->service($otherLoc);
        $admin = $this->orgAdmin($mine);

        $this->actingAs($admin);
        $this->get(route('platform.manage.index'))->assertOk();
        $this->get(route('platform.manage.locations'))->assertOk()->assertSee($myLoc->name)->assertDontSee($otherLoc->name);
        $this->get(route('platform.manage.organizations'))->assertOk()->assertSee('Mine')->assertDontSee('Other');

        // Own org: allowed
        $this->get(route('platform.manage.locations.edit', $myLoc))->assertOk();
        $this->get(route('platform.manage.services.edit', $mySvc))->assertOk();
        $this->post(route('platform.manage.services.update', $mySvc), $this->serviceForm())->assertRedirect();
        $mySvc->refresh();
        $this->assertSame('R', $mySvc->prefix);
        $this->assertSame(['Old licence', 'Citizenship copy'], $mySvc->documents);

        // Other org: 403 everywhere
        $this->get(route('platform.manage.organizations.show', $other))->assertForbidden();
        $this->post(route('platform.manage.organizations.update', $other), ['name' => 'Hacked', 'type' => 'bank'])->assertForbidden();
        $this->get(route('platform.manage.locations.edit', $otherLoc))->assertForbidden();
        $this->post(route('platform.manage.locations.update', $otherLoc), ['name' => 'Hacked', 'area' => 'x', 'type' => 'x', 'icon' => 'bi-x', 'lat' => 27.7, 'lng' => 85.3, 'organization_id' => $other->id])->assertForbidden();
        $this->get(route('platform.manage.services.edit', $otherSvc))->assertForbidden();
        $this->post(route('platform.manage.services.update', $otherSvc), $this->serviceForm(['name' => 'Hacked']))->assertForbidden();
        $this->post(route('platform.manage.counters.toggle', $otherLoc->counters()->first()))->assertForbidden();
        $this->post(route('platform.manage.services.store', $otherLoc), $this->serviceForm())->assertForbidden();

        // Cannot move own location into another org, nor create a location in another org
        $this->post(route('platform.manage.locations.update', $myLoc), ['name' => 'Moved', 'area' => 'x', 'type' => 'x', 'icon' => 'bi-x', 'lat' => 27.7, 'lng' => 85.3, 'organization_id' => $other->id])->assertForbidden();
        $this->post(route('platform.manage.locations.store'), ['name' => 'Sneaky', 'area' => 'x', 'type' => 'x', 'icon' => 'bi-x', 'lat' => 27.7, 'lng' => 85.3, 'organization_id' => $other->id])->assertForbidden();

        // Staff assignment / places in another org
        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'staff'])->save();
        $this->post(route('platform.manage.staff.assign'), ['email' => $staff->email, 'location_id' => $otherLoc->id])->assertForbidden();
        $foreign = StaffAssignment::create(['user_id' => $staff->id, 'location_id' => $otherLoc->id]);
        $this->post(route('platform.manage.staff.remove', $foreign))->assertForbidden();
        $place = new NearbyPlace;
        $place->forceFill(['location_id' => $otherLoc->id, 'name' => 'Cafe', 'category' => 'Cafe', 'emoji' => 'x', 'tip' => 't', 'lat' => 27.7, 'lng' => 85.3, 'walk_minutes' => 2, 'visit_minutes' => 5, 'rating' => 4])->save();
        $this->get(route('platform.manage.places.edit', $place))->assertForbidden();
        $this->post(route('platform.manage.places.delete', $place))->assertForbidden();

        // Platform-admin-only pages
        $this->get(route('platform.manage.users'))->assertForbidden();
        $this->get(route('platform.manage.audit'))->assertForbidden();
        $this->get(route('platform.analytics'))->assertForbidden();
        $this->post(route('platform.manage.organizations.members.add', $mine), ['email' => $staff->email])->assertForbidden();

        $this->assertSame('Licence', $otherSvc->fresh()->name);
        $this->assertSame($mine->id, (int) $myLoc->fresh()->organization_id);
        $this->assertTrue(StaffAssignment::whereKey($foreign->id)->exists());
    }

    public function test_org_admin_can_assign_existing_staff_but_never_elevate(): void
    {
        $org = $this->org('Mine');
        $loc = $this->location($org);
        $admin = $this->orgAdmin($org);
        $plain = User::factory()->create();

        $this->actingAs($admin)->post(route('platform.manage.staff.assign'), ['email' => $plain->email, 'location_id' => $loc->id])->assertSessionHasErrors('email');
        $this->assertSame('user', $plain->fresh()->role);

        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'staff'])->save();
        $this->post(route('platform.manage.staff.assign'), ['email' => $staff->email, 'location_id' => $loc->id])->assertRedirect();
        $this->assertTrue($staff->fresh()->canOperateLocation($loc->id));
        $a = StaffAssignment::where('user_id', $staff->id)->firstOrFail();
        $this->post(route('platform.manage.staff.approve', $a))->assertRedirect();
        $this->assertFalse($a->fresh()->is_approved);
        $this->assertTrue(AuditLog::where('action', 'staff_suspended')->exists());
    }

    public function test_platform_admin_has_full_access(): void
    {
        $orgA = $this->org('Alpha');
        $orgB = $this->org('Beta');
        $locB = $this->location($orgB);
        $svc = $this->service($locB);
        $admin = $this->admin();
        $this->actingAs($admin);

        foreach (['platform.manage.index', 'platform.manage.organizations', 'platform.manage.locations', 'platform.manage.staff', 'platform.manage.places', 'platform.manage.users', 'platform.manage.audit', 'platform.manage.integrations', 'platform.analytics', 'platform.manage.locations.create', 'platform.manage.places.create'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('platform.manage.organizations.show', $orgB))->assertOk();
        $this->get(route('platform.manage.services.edit', $svc))->assertOk();

        $this->post(route('platform.manage.organizations.store'), ['name' => 'Gamma Hospital', 'type' => 'hospital', 'allow_queue_exchange' => '0'])->assertRedirect();
        $gamma = Organization::where('name', 'Gamma Hospital')->firstOrFail();
        $this->assertFalse((bool) $gamma->setting('allow_queue_exchange'));

        $this->post(route('platform.manage.locations.store'), ['name' => 'New Branch', 'area' => 'Patan', 'type' => 'Hospital', 'icon' => 'bi-hospital-fill', 'lat' => 27.67, 'lng' => 85.32, 'organization_id' => $gamma->id])->assertRedirect();
        $loc = Location::where('name', 'New Branch')->firstOrFail();
        $this->post(route('platform.manage.services.store', $loc), $this->serviceForm(['prefix' => 'h']))->assertRedirect();
        $newSvc = Service::where('location_id', $loc->id)->firstOrFail();
        $this->assertSame([0, 0, 'H'], [$newSvc->current_number, $newSvc->last_number, $newSvc->prefix]);

        $this->post(route('platform.manage.counters.store', $loc), ['number' => 3, 'label' => '', 'service_id' => $newSvc->id])->assertRedirect();
        $c = Counter::where('location_id', $loc->id)->firstOrFail();
        $this->post(route('platform.manage.counters.toggle', $c))->assertRedirect();
        $this->assertFalse($c->fresh()->is_open);
        // Counter linked to a service of another location is rejected
        $this->post(route('platform.manage.counters.store', $loc), ['number' => 4, 'service_id' => $svc->id])->assertSessionHasErrors('service_id');

        // Org admin membership + scoping to the new org
        $u = User::factory()->create();
        $this->post(route('platform.manage.organizations.members.add', $gamma), ['email' => $u->email])->assertRedirect();
        $this->assertTrue(app(\App\Services\Platform\OrgAccess::class)->canManageLocation($u, $loc->id));

        // Cannot delete an organization that still has locations
        $this->post(route('platform.manage.organizations.delete', $gamma))->assertStatus(422);
        $this->post(route('platform.manage.organizations.delete', $orgA))->assertRedirect();
        $this->assertNull(Organization::find($orgA->id));
        $this->assertTrue(AuditLog::where('action', 'service_created')->exists());
    }

    public function test_admin_role_changes_are_audited_and_cannot_demote_self(): void
    {
        $admin = $this->admin();
        $u = User::factory()->create();
        $this->actingAs($admin)->post(route('platform.manage.users.role', $u), ['role' => 'staff'])->assertRedirect();
        $this->assertSame('staff', $u->fresh()->role);
        $this->assertTrue(AuditLog::where('action', 'role_changed')->where('subject_id', $u->id)->exists());

        $this->post(route('platform.manage.users.role', $admin), ['role' => 'user'])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
        $this->get(route('platform.manage.users', ['q' => $u->email]))->assertOk()->assertSee($u->email);
    }

    public function test_normal_user_cannot_reach_admin_or_org_pages(): void
    {
        $org = $this->org();
        $loc = $this->location($org);
        $svc = $this->service($loc);
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([route('platform.manage.index'), route('platform.manage.organizations'), route('platform.manage.organizations.show', $org), route('platform.manage.locations'), route('platform.manage.locations.edit', $loc), route('platform.manage.services.edit', $svc), route('platform.manage.staff'), route('platform.manage.places'), route('platform.manage.users'), route('platform.manage.audit'), route('platform.manage.integrations'), route('platform.analytics')] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('platform.manage.users.role', $user), ['role' => 'admin'])->assertForbidden();
        $this->post(route('platform.manage.organizations.store'), ['name' => 'Mine now', 'type' => 'private'])->assertForbidden();
        $this->post(route('platform.manage.services.update', $svc), $this->serviceForm())->assertForbidden();
        $this->assertSame('user', $user->fresh()->role);

        auth()->logout();
        $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
        $this->get(route('platform.manage.index'))->assertRedirect(route('login'));
    }

    // ---------------- Profile & privacy ----------------

    public function test_profile_update_cannot_change_role_or_email(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $this->actingAs($user)->post(route('platform.settings.profile'), [
            'name' => 'Sita Sharma', 'phone' => '+977 9800000000', 'role' => 'admin', 'email' => 'boss@example.com', 'email_verified_at' => now(),
        ])->assertRedirect(route('platform.settings'));

        $user->refresh();
        $this->assertSame('Sita Sharma', $user->name);
        $this->assertSame('+977 9800000000', $user->phone);
        $this->assertSame('user', $user->role);
        $this->assertSame('me@example.com', $user->email);

        $this->post(route('platform.settings.profile'), ['name' => '', 'phone' => 'call me'])->assertSessionHasErrors(['name', 'phone']);
        $this->get(route('platform.settings'))->assertOk()->assertSee('Settings');
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPass123']);
        $this->actingAs($user)->post(route('platform.settings.password'), ['current_password' => 'wrong', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->assertTrue(password_verify('OldPass123', $user->fresh()->password));

        $this->post(route('platform.settings.password'), ['current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertRedirect();
        $this->assertTrue(password_verify('NewPass456', $user->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'password_changed')->exists());
    }

    public function test_data_export_contains_only_own_data(): void
    {
        $org = $this->org();
        $svc = $this->service($this->location($org));
        $me = User::factory()->create(['name' => 'Me Person', 'email' => 'me@example.com']);
        $other = User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com']);
        $qm = app(QueueManager::class);
        $mine = $qm->join($svc, $me, ['ID' => 'available']);
        $theirs = $qm->join($svc, $other, ['ID' => 'available']);
        $me->notify(new QueueUpdate($mine, 'turn_next', 'Mine notification'));
        $other->notify(new QueueUpdate($theirs, 'turn_next', 'Secret other notification'));
        app(RewardService::class)->award($me, 10, 'game', 'My points', 'k-me');
        app(RewardService::class)->award($other, 20, 'game', 'Other points', 'k-other');

        $res = $this->actingAs($me)->get(route('platform.settings.export'))->assertOk();
        $this->assertStringContainsString('attachment;', $res->headers->get('Content-Disposition'));
        $json = json_decode($res->getContent(), true);
        $this->assertSame('me@example.com', $json['account']['email']);
        $this->assertCount(1, $json['tickets']);
        $this->assertSame($mine->token(), $json['tickets'][0]['token']);
        $this->assertCount(1, $json['reward_transactions']);
        $raw = $res->getContent();
        foreach (['other@example.com', 'Other Person', 'Secret other notification', 'Other points'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw);
        }
    }

    public function test_preferences_persist_and_unconfigured_channels_stay_off(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('platform.settings.preferences'), [
            'browser_push' => '1', 'email' => '1', 'marketing_opt_in' => '1', 'personalised_ads' => '0', 'location_suggestions' => '0',
        ])->assertOk()->assertJsonPath('data.channels.browser_push', true)->assertJsonPath('data.channels.email', false);

        $p = UserPreference::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($p->channels()['browser_push']);
        $this->assertFalse($p->channels()['email']);
        $this->assertFalse($p->channels()['sms']);
        $this->assertTrue($p->channels()['database']);
        $this->assertTrue($p->marketing_opt_in);
        $this->assertFalse($p->personalised_ads);
        $this->assertFalse($p->location_suggestions);

        config(['mail.default' => 'smtp']);
        $this->post(route('platform.settings.preferences'), ['email' => '1', 'location_suggestions' => '1'])->assertRedirect();
        $p->refresh();
        $this->assertTrue($p->channels()['email']);
        $this->assertTrue($p->location_suggestions);
        $this->assertFalse($p->marketing_opt_in); // unchecked → off
    }

    public function test_delete_account_anonymises_but_keeps_queue_records(): void
    {
        $svc = $this->service($this->location($this->org()));
        $user = User::factory()->create(['email' => 'gone@example.com', 'password' => 'Secret123']);
        $ticket = app(QueueManager::class)->join($svc, $user, ['ID' => 'available']);
        $user->notify(new QueueUpdate($ticket, 'turn_next', 'Hello'));

        $this->actingAs($user)->post(route('platform.settings.delete'), ['password' => 'wrong', 'confirm' => 'DELETE'])->assertSessionHasErrorsIn('delete', 'password');
        $this->post(route('platform.settings.delete'), ['password' => 'Secret123', 'confirm' => 'delete'])->assertSessionHasErrorsIn('delete', 'confirm');
        $this->assertSame('gone@example.com', $user->fresh()->email);

        $this->post(route('platform.settings.delete'), ['password' => 'Secret123', 'confirm' => 'DELETE'])->assertRedirect(route('home'));
        $this->assertGuest();
        $u = $user->fresh();
        $this->assertSame('Deleted user', $u->name);
        $this->assertStringEndsWith('@deleted.invalid', $u->email);
        $this->assertNull($u->phone);
        $this->assertSame(0, $u->notifications()->count());
        $this->assertTrue(Ticket::whereKey($ticket->id)->exists());
        $this->assertSame('Deleted user', $ticket->fresh()->name);
    }

    // ---------------- Notifications ----------------

    public function test_notifications_mark_read_is_scoped_to_owner(): void
    {
        $svc = $this->service($this->location($this->org()));
        $me = User::factory()->create();
        $other = User::factory()->create();
        $t1 = app(QueueManager::class)->join($svc, $me, ['ID' => 'available']);
        $t2 = app(QueueManager::class)->join($svc, $other, ['ID' => 'available']);
        $me->notify(new QueueUpdate($t1, 'turn_next', 'Mine'));
        $other->notify(new QueueUpdate($t2, 'turn_called', 'Theirs'));
        $mineId = $me->notifications()->first()->id;
        $theirId = $other->notifications()->first()->id;

        $this->actingAs($me);
        $this->get(route('platform.notifications'))->assertOk()->assertSee('Mine')->assertDontSee('Theirs');
        $this->getJson(route('platform.notifications.count'))->assertOk()->assertJsonPath('data.unread', 1);

        // IDOR: another user's notification id → 404, stays unread
        $this->post(route('platform.notifications.read', $theirId))->assertNotFound();
        $this->assertNull($other->notifications()->first()->read_at);

        $this->postJson(route('platform.notifications.read', $mineId))->assertOk()->assertJsonPath('data.unread', 0);
        $this->assertNotNull($me->notifications()->first()->read_at);

        // read-all touches only my notifications; open redirects to my ticket
        $me->notify(new QueueUpdate($t1, 'approaching', 'Again'));
        $this->post(route('platform.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $me->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->post(route('platform.notifications.read', $mineId), ['open' => 1])->assertRedirect(route('ticket', $t1));
    }

    // ---------------- Rewards ----------------

    public function test_redeem_rejects_insufficient_balance_and_never_double_charges(): void
    {
        $user = User::factory()->create();
        $item = RewardCatalogueItem::create(['slug' => 'chiya', 'title' => 'Free chiya', 'partner' => 'Demo café', 'points_cost' => 150, 'stock' => 5]);
        $nonce = (string) Str::uuid();

        $this->actingAs($user)->post(route('platform.rewards.redeem', $item->id), ['nonce' => $nonce])->assertSessionHasErrors('redeem');
        $this->assertSame(0, RewardRedemption::count());

        app(RewardService::class)->award($user, 200, 'game', 'Won', 'k1');
        $this->post(route('platform.rewards.redeem', $item->id), ['nonce' => $nonce])->assertRedirect(route('platform.rewards'));
        // Same form submitted again (double click / replay): no second charge
        $this->post(route('platform.rewards.redeem', $item->id), ['nonce' => $nonce])->assertRedirect(route('platform.rewards'));
        $this->assertSame(1, RewardRedemption::count());
        $this->assertSame(50, app(RewardService::class)->balance($user));
        $this->assertSame(4, $item->fresh()->stock);
        $code = RewardRedemption::first()->code;
        $this->assertMatchesRegularExpression('/^PK-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);

        // A fresh nonce with 50 points left is rejected
        $this->post(route('platform.rewards.redeem', $item->id), ['nonce' => (string) Str::uuid()])->assertSessionHasErrors('redeem');
        $this->assertSame(1, RewardTransaction::where('points', '<', 0)->count());
        $this->post(route('platform.rewards.redeem', $item->id), ['nonce' => 'not-a-uuid'])->assertSessionHasErrors('nonce');

        $this->get(route('platform.rewards'))->assertOk()->assertSee($code)->assertSee('never change your place');
    }

    // ---------------- Analytics & dashboard ----------------

    public function test_analytics_renders_without_optional_modules(): void
    {
        $svc = $this->service($this->location($this->org()));
        app(QueueManager::class)->join($svc, User::factory()->create(), ['ID' => 'available']);

        Schema::disableForeignKeyConstraints();
        foreach (['competition_players', 'arcade_sessions', 'game_scores', 'ad_campaigns', 'operational_incidents', 'fraud_alerts', 'queue_exchange_requests'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->admin())->get(route('platform.analytics'))
            ->assertOk()->assertSee('Local demo data')->assertSee('Module not installed')->assertSee('Peak periods')->assertSee('Licence');
    }

    public function test_dashboard_shows_own_tokens_and_balance(): void
    {
        $svc = $this->service($this->location($this->org()));
        $me = User::factory()->create();
        $other = User::factory()->create();
        $mine = app(QueueManager::class)->join($svc, $me, ['ID' => 'available']);
        $theirs = app(QueueManager::class)->join($svc, $other, ['ID' => 'available']);
        app(RewardService::class)->award($me, 75, 'game', 'Won', 'k-dash');

        $this->actingAs($me)->get(route('platform.dashboard'))->assertOk()
            ->assertSee($mine->token())->assertDontSee($theirs->token())->assertSee('75');
    }
}
