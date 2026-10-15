<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Location;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Queue\QueueManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function location(): Location
    {
        return Location::create(['name' => 'Office '.uniqid(), 'area' => 'KTM', 'type' => 'Bank', 'icon' => 'bi-bank', 'lat' => 27.7, 'lng' => 85.3]);
    }

    private function serviceAt(Location $loc)
    {
        $s = $loc->services()->create(['name' => 'Deposit', 'prefix' => 'T', 'counter' => 1, 'avg_minutes' => 3, 'current_number' => 0, 'last_number' => 0, 'documents' => []]);
        Counter::create(['location_id' => $loc->id, 'service_id' => $s->id, 'number' => 1]);

        return $s;
    }

    private function staff(?Location $loc = null, string $role = 'staff'): User
    {
        $u = User::factory()->create(['password' => 'Secret@123']);
        $u->forceFill(['role' => $role])->save();
        if ($loc) {
            StaffAssignment::create(['user_id' => $u->id, 'location_id' => $loc->id]);
        }

        return $u;
    }

    // ---------------- Registration & login ----------------

    public function test_auth_pages_render(): void
    {
        foreach (['login', 'register.show', 'password.request', 'staff.login'] as $r) {
            $this->get(route($r))->assertOk();
        }
    }

    public function test_registration_validates_every_field(): void
    {
        $this->post(route('register'), ['name' => '', 'email' => 'nope', 'phone' => '12345', 'password' => 'short', 'password_confirmation' => 'x'])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'password', 'terms']);
        $this->assertSame(0, User::count());
    }

    public function test_registration_cannot_grant_elevated_role(): void
    {
        $this->post(route('register'), [
            'name' => 'Ram Sharma', 'email' => 'Ram@Example.com', 'phone' => '9812345678',
            'password' => 'Strong123', 'password_confirmation' => 'Strong123', 'terms' => '1', 'role' => 'admin',
        ])->assertRedirect(route('profile'));

        $u = User::firstOrFail();
        $this->assertSame('user', $u->role);
        $this->assertSame('ram@example.com', $u->email);
        $this->assertAuthenticatedAs($u);
    }

    public function test_login_with_wrong_password_fails_and_is_rate_limited(): void
    {
        $u = User::factory()->create(['password' => 'Right@123']);
        foreach (range(1, 5) as $_) {
            $this->post(route('login.post'), ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.post'), ['email' => $u->email, 'password' => 'Right@123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_succeeds_and_regenerates_session(): void
    {
        $u = User::factory()->create(['password' => 'Right@123']);
        $this->post(route('login.post'), ['email' => $u->email, 'password' => 'Right@123'])->assertRedirect(route('profile'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_password_reset_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $known = $this->post(route('password.email'), ['email' => $u->email])->assertSessionHas('status')->getSession()->get('status');
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com'])->getSession()->get('status');
        $this->assertSame($known, $unknown);
        Notification::assertSentTo($u, ResetPassword::class);
    }

    public function test_password_reset_with_valid_token(): void
    {
        $u = User::factory()->create();
        $token = Password::createToken($u);
        $this->post(route('password.update'), ['token' => $token, 'email' => $u->email, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect(route('login'));
        $this->post(route('login.post'), ['email' => $u->email, 'password' => 'NewPass123'])->assertRedirect(route('profile'));
    }

    public function test_password_reset_with_bad_token_fails(): void
    {
        $u = User::factory()->create();
        $this->post(route('password.update'), ['token' => 'bogus', 'email' => $u->email, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasErrors('email');
    }

    // ---------------- Staff login ----------------

    public function test_staff_login_requires_role_and_assignment(): void
    {
        $normal = User::factory()->create(['password' => 'Secret@123']);
        $this->post(route('staff.login.post'), ['email' => $normal->email, 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $unassigned = $this->staff();
        $this->post(route('staff.login.post'), ['email' => $unassigned->email, 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $assigned = $this->staff($this->location());
        $this->post(route('staff.login.post'), ['email' => $assigned->email, 'password' => 'Secret@123'])->assertRedirect(route('staff'));
        $this->assertAuthenticatedAs($assigned);
    }

    // ---------------- Access control ----------------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('find'))->assertRedirect(route('login'));
        $this->get(route('staff'))->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_or_poll_another_users_ticket(): void
    {
        $s = $this->serviceAt($this->location());
        $owner = User::factory()->create();
        $t = app(QueueManager::class)->join($s, $owner);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('ticket', $t))->assertForbidden();
        $this->actingAs($intruder)->getJson(route('ticket.state', $t))->assertForbidden();
        $this->actingAs($intruder)->post(route('ticket.leave', $t))->assertForbidden();
        $this->actingAs($intruder)->postJson(route('ticket.points', $t), ['points' => 100])->assertForbidden();
        $this->assertSame('waiting', $t->fresh()->status);

        $this->actingAs($owner)->get(route('ticket', $t))->assertOk();
    }

    public function test_normal_user_cannot_use_staff_or_admin_endpoints(): void
    {
        $s = $this->serviceAt($this->location());
        $t = app(QueueManager::class)->join($s, User::factory()->create());
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('staff'))->assertForbidden();
        $this->actingAs($user)->post(route('staff.next', $s))->assertForbidden();
        $this->actingAs($user)->post(route('staff.complete', $t))->assertForbidden();
        $this->actingAs($user)->post(route('staff.verify', $t), ['result' => 'verified'])->assertForbidden();
        $this->actingAs($user)->get(route('admin'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.role', $user), ['role' => 'admin'])->assertForbidden();
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame(0, $s->fresh()->current_number);
    }

    public function test_staff_cannot_manage_unassigned_location(): void
    {
        $mine = $this->location();
        $theirs = $this->location();
        $staff = $this->staff($mine);
        $this->serviceAt($mine);
        $foreignService = $this->serviceAt($theirs);
        $t = app(QueueManager::class)->join($foreignService, User::factory()->create());

        $this->actingAs($staff)->post(route('staff.next', $foreignService))->assertForbidden();
        $this->actingAs($staff)->post(route('staff.cancel', $t))->assertForbidden();
        $this->actingAs($staff)->get(route('ticket', $t))->assertForbidden();
        $this->actingAs($staff)->get(route('staff', ['service' => $foreignService->id]))->assertOk()
            ->assertDontSee($foreignService->location->name);
        $this->assertSame('waiting', $t->fresh()->status);
    }

    public function test_staff_cannot_promote_themselves_and_admin_can_assign(): void
    {
        $loc = $this->location();
        $staff = $this->staff($loc);
        $this->actingAs($staff)->post(route('admin.role', $staff), ['role' => 'admin'])->assertForbidden();

        $admin = $this->staff(null, 'admin');
        $target = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.role', $target), ['role' => 'staff'])->assertSessionHas('ok');
        $this->actingAs($admin)->post(route('admin.assign', $target), ['location_id' => $loc->id])->assertSessionHas('ok');
        $this->assertTrue($target->fresh()->canOperateLocation($loc->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role_changed', 'subject_id' => $target->id]);
        $this->actingAs($admin)->get(route('admin'))->assertOk();
    }

    public function test_game_points_are_capped_and_never_change_queue_position(): void
    {
        $s = $this->serviceAt($this->location());
        $m = app(QueueManager::class);
        $first = $m->join($s, User::factory()->create());
        $owner = User::factory()->create();
        $second = $m->join($s->fresh(), $owner);

        $this->actingAs($owner)->postJson(route('ticket.points', $second), ['points' => 999999])->assertOk();
        $this->assertSame(100, $second->fresh()->game_points);
        $this->assertSame(2, $second->fresh()->number);
        $this->assertSame(1, $second->fresh()->ahead());
    }

    public function test_rejected_verification_requires_reason(): void
    {
        $loc = $this->location();
        $t = app(QueueManager::class)->join($this->serviceAt($loc), User::factory()->create());
        $this->actingAs($this->staff($loc))->post(route('staff.verify', $t), ['result' => 'rejected'])->assertSessionHasErrors('reason');
        $this->assertSame(0, \App\Models\DocumentVerification::count());
    }
}
