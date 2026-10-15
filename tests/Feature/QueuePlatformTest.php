<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Location;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Queue\QueueException;
use App\Services\Queue\QueueManager;
use App\Services\Queue\WaitTimeEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueuePlatformTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $overrides = [], ?Location $loc = null): Service
    {
        $loc ??= Location::create(['name' => 'Test Office '.uniqid(), 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
        $service = $loc->services()->create(array_merge([
            'name' => 'Licence Renewal', 'prefix' => 'D', 'counter' => 2, 'avg_minutes' => 4,
            'current_number' => 15, 'last_number' => 23, 'documents' => ['Old licence', 'Citizenship copy'],
        ], $overrides));
        Counter::create(['location_id' => $loc->id, 'service_id' => $service->id, 'number' => $service->counter, 'label' => 'Counter 2']);

        return $service;
    }

    private function staffFor(Location $loc): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'staff'])->save();
        StaffAssignment::create(['user_id' => $u->id, 'location_id' => $loc->id]);

        return $u;
    }

    private function allDocs(Service $s): array
    {
        return collect($s->documents)->mapWithKeys(fn ($d) => [$d => 'available'])->all();
    }

    // ---------------- Joining ----------------

    public function test_user_joins_and_gets_next_unique_token_with_estimate(): void
    {
        $s = $this->service();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('join', $s), ['docs' => $this->allDocs($s)])->assertRedirect();

        $t = Ticket::firstOrFail();
        $this->assertSame('D-24', $t->token());
        $this->assertSame('waiting', $t->status);
        $this->assertSame(8, $t->ahead());
        $this->assertSame(32, $t->initial_wait);           // 8 ahead × 4 min, 1 counter
        $this->assertSame(2, $t->counterNumber());
        $this->assertSame(24, $s->fresh()->last_number);
    }

    public function test_duplicate_join_returns_existing_token_instead_of_a_new_one(): void
    {
        $s = $this->service();
        $user = User::factory()->create();
        $m = app(QueueManager::class);

        $a = $m->join($s, $user, $this->allDocs($s));
        $b = $m->join($s->fresh(), $user, $this->allDocs($s));

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Ticket::count());
        $this->assertSame(24, $s->fresh()->last_number);
    }

    public function test_idempotency_key_makes_browser_retries_safe(): void
    {
        $s = $this->service();
        $user = User::factory()->create();
        $payload = ['docs' => $this->allDocs($s), 'idempotency_key' => 'abc-123'];

        $this->actingAs($user)->post(route('join', $s), $payload);
        $this->actingAs($user)->post(route('join', $s), $payload);

        $this->assertSame(1, Ticket::count());
    }

    public function test_many_users_get_distinct_sequential_tokens(): void
    {
        $s = $this->service();
        $m = app(QueueManager::class);
        $numbers = collect(range(1, 10))->map(fn () => $m->join($s->fresh(), User::factory()->create(), $this->allDocs($s))->number);

        $this->assertSame(range(24, 33), $numbers->all());
        $this->assertSame(10, $numbers->unique()->count());
    }

    public function test_database_rejects_two_active_entries_for_same_user_and_service(): void
    {
        $s = $this->service();
        $user = User::factory()->create();
        app(QueueManager::class)->join($s, $user);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $dup = new Ticket(['name' => 'x']);
        $dup->forceFill(['service_id' => $s->id, 'user_id' => $user->id, 'number' => 99, 'status' => 'waiting', 'initial_wait' => 0, 'active_key' => $s->id.':'.$user->id])->save();
    }

    public function test_missing_documents_require_confirmation_but_do_not_block(): void
    {
        $s = $this->service();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('join', $s), ['docs' => []])->assertSessionHas('warn_missing');
        $this->assertSame(0, Ticket::count());

        $this->actingAs($user)->post(route('join', $s), ['docs' => [], 'confirm_missing' => 1])->assertRedirect();
        $this->assertSame(1, Ticket::count());
        $this->assertFalse(Ticket::first()->docs_ready);
    }

    public function test_service_can_require_all_documents(): void
    {
        $s = $this->service(['requires_all_documents' => true]);
        $this->expectException(QueueException::class);
        app(QueueManager::class)->join($s, User::factory()->create(), []);
    }

    public function test_user_can_leave_and_then_rejoin(): void
    {
        $s = $this->service();
        $user = User::factory()->create();
        $t = app(QueueManager::class)->join($s, $user, $this->allDocs($s));

        $this->actingAs($user)->post(route('ticket.leave', $t))->assertRedirect(route('profile'));
        $this->assertSame('left', $t->fresh()->status);
        $this->assertNull($t->fresh()->active_key);

        $again = app(QueueManager::class)->join($s->fresh(), $user, $this->allDocs($s));
        $this->assertNotSame($t->id, $again->id);
    }

    public function test_people_who_left_are_not_counted_ahead(): void
    {
        $s = $this->service(['current_number' => 0, 'last_number' => 0]);
        $m = app(QueueManager::class);
        $a = $m->join($s, User::factory()->create());
        $b = $m->join($s->fresh(), User::factory()->create());
        $c = $m->join($s->fresh(), User::factory()->create());
        $this->assertSame(2, $c->fresh()->ahead());

        $m->leave($b, $b->user);
        $this->assertSame(1, $c->fresh()->ahead());
        $this->assertSame(2, $s->fresh()->waitingCount());
    }

    // ---------------- Staff workflow & state machine ----------------

    public function test_staff_call_next_start_complete_flow(): void
    {
        $s = $this->service(['current_number' => 0, 'last_number' => 0]);
        $staff = $this->staffFor($s->location);
        $customer = User::factory()->create();
        $t = app(QueueManager::class)->join($s, $customer, $this->allDocs($s));

        $this->actingAs($staff)->post(route('staff.next', $s))->assertSessionHas('ok');
        $this->assertSame('called', $t->fresh()->status);
        $this->assertSame(1, $customer->notifications()->count());

        $this->actingAs($staff)->post(route('staff.start', $t));
        $this->assertSame('serving', $t->fresh()->status);

        $this->actingAs($staff)->post(route('staff.verify', $t), ['result' => 'verified'])->assertSessionHas('ok');
        $this->assertTrue($t->fresh()->docs_verified);
        $this->assertDatabaseHas('document_verifications', ['ticket_id' => $t->id, 'staff_id' => $staff->id, 'result' => 'verified']);

        $this->actingAs($staff)->post(route('staff.complete', $t));
        $this->assertSame('completed', $t->fresh()->status);
        $this->assertSame(['joined', 'called', 'serving', 'completed'], $t->events()->reorder('id')->pluck('type')->all());
    }

    public function test_called_but_never_started_becomes_absent_on_next_call(): void
    {
        $s = $this->service(['current_number' => 0, 'last_number' => 0]);
        $staff = $this->staffFor($s->location);
        $m = app(QueueManager::class);
        $a = $m->join($s, User::factory()->create());
        $b = $m->join($s->fresh(), User::factory()->create());

        $m->callNext($s, $staff);
        $m->callNext($s->fresh(), $staff);

        $this->assertSame('absent', $a->fresh()->status);
        $this->assertSame('called', $b->fresh()->status);
    }

    public function test_call_next_skips_tokens_that_left(): void
    {
        $s = $this->service(['current_number' => 0, 'last_number' => 0]);
        $staff = $this->staffFor($s->location);
        $m = app(QueueManager::class);
        $a = $m->join($s, User::factory()->create());
        $b = $m->join($s->fresh(), User::factory()->create());
        $m->leave($a, $a->user);

        $called = $m->callNext($s->fresh(), $staff);
        $this->assertSame($b->id, $called->id);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $t = app(QueueManager::class)->join($s, User::factory()->create());

        $this->expectException(QueueException::class);
        app(QueueManager::class)->startService($t, $staff);   // waiting → serving is not allowed
    }

    public function test_paused_queue_cannot_call_next(): void
    {
        $s = $this->service(['is_paused' => true]);
        $this->expectException(QueueException::class);
        app(QueueManager::class)->callNext($s, $this->staffFor($s->location));
    }

    public function test_move_to_counter_is_audited_and_scoped_to_location(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $t = app(QueueManager::class)->join($s, User::factory()->create());
        $c5 = Counter::create(['location_id' => $s->location_id, 'number' => 5]);

        $this->actingAs($staff)->post(route('staff.move', $t), ['counter_id' => $c5->id])->assertSessionHas('ok');
        $this->assertSame(5, $t->fresh()->counterNumber());
        $this->assertDatabaseHas('queue_events', ['ticket_id' => $t->id, 'type' => 'moved_counter']);

        $other = $this->service();
        $foreign = Counter::where('location_id', $other->location_id)->first();
        $this->actingAs($staff)->post(route('staff.move', $t), ['counter_id' => $foreign->id])->assertSessionHasErrors('queue');
    }

    // ---------------- Waiting-time engine ----------------

    public function test_estimate_is_a_range_and_uses_open_counters(): void
    {
        $s = $this->service();
        $est = app(WaitTimeEstimator::class)->forPeopleAhead($s, 8);
        $this->assertSame(32, $est['minutes']);
        $this->assertLessThan(32, $est['low']);
        $this->assertGreaterThan(32, $est['high']);
        $this->assertSame('low', $est['confidence']);

        Counter::create(['location_id' => $s->location_id, 'service_id' => $s->id, 'number' => 9]);
        $this->assertSame(16, app(WaitTimeEstimator::class)->forPeopleAhead($s, 8)['minutes']);
    }

    public function test_estimate_learns_from_completed_services(): void
    {
        $s = $this->service(['avg_minutes' => 4]);
        foreach (range(1, 10) as $i) {
            $t = new Ticket(['name' => 'h']);
            $t->forceFill(['service_id' => $s->id, 'number' => 100 + $i, 'status' => 'completed', 'initial_wait' => 0,
                'serving_at' => now()->subMinutes(10), 'completed_at' => now()])->save();
        }
        [$avg, $samples] = app(WaitTimeEstimator::class)->averageMinutes($s);
        $this->assertSame(10, $samples);
        $this->assertEqualsWithDelta(8.8, $avg, 0.01);   // 20% × 4 + 80% × 10
    }

    // ---------------- Polling endpoint ----------------

    public function test_status_endpoint_returns_consistent_json_and_never_creates_entries(): void
    {
        $s = $this->service();
        $user = User::factory()->create();
        $t = app(QueueManager::class)->join($s, $user);

        foreach (range(1, 3) as $_) {
            $this->actingAs($user)->getJson(route('ticket.state', $t))
                ->assertOk()
                ->assertJsonPath('message', 'Queue status retrieved successfully')
                ->assertJsonPath('data.token', 'D-24')
                ->assertJsonStructure(['data' => ['current_token', 'ahead', 'wait_low', 'wait_high', 'counter', 'status']]);
        }
        $this->assertSame(1, Ticket::count());
    }
}
