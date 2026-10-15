<?php

namespace Tests\Feature\Modules;

use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\FraudAlert;
use App\Models\IntegritySignal;
use App\Models\Location;
use App\Models\QueueEvent;
use App\Models\QueueExchangeRequest;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Integrity\DalalEngine;
use App\Services\Integrity\ExplanationService;
use App\Services\Queue\QueueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ helpers

    private function service(array $overrides = [], ?Location $loc = null): Service
    {
        $loc ??= Location::create(['name' => 'Test Office '.uniqid(), 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
        $service = $loc->services()->create(array_merge([
            'name' => 'Licence Renewal', 'prefix' => 'D', 'counter' => 2, 'avg_minutes' => 4,
            'current_number' => 10, 'last_number' => 10, 'documents' => [],
        ], $overrides));
        Counter::firstOrCreate(['location_id' => $loc->id, 'number' => $service->counter], ['service_id' => $service->id, 'label' => 'Counter '.$service->counter]);

        return $service;
    }

    private function staffFor(Location $loc): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'staff'])->save();
        StaffAssignment::create(['user_id' => $u->id, 'location_id' => $loc->id]);

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function join(Service $s, ?User $u = null): Ticket
    {
        return app(QueueManager::class)->join($s->fresh(), $u ?? User::factory()->create());
    }

    /** Two users waiting in the same service, A ahead of B. */
    private function pair(array $serviceOverrides = []): array
    {
        $s = $this->service($serviceOverrides);
        $a = $this->join($s);
        $this->join($s);                 // someone in between
        $b = $this->join($s);

        return [$s, $a, $b, $a->user, $b->user];
    }

    private function requestExchange(Ticket $mine, User $as, string $label)
    {
        return $this->actingAs($as)->post(route('integrity.exchange.store', $mine), ['target_token' => $label]);
    }

    private function fakeAi(?array $response): AiClient
    {
        $fake = new class extends AiClient
        {
            public array $calls = [];

            public ?array $response = null;

            public function enabled(): bool
            {
                return true;
            }

            public function providerLabel(): string
            {
                return 'fake · test-model';
            }

            public function json(string $system, string $prompt, callable $validator, int $maxTokens = 600): ?array
            {
                $this->calls[] = ['system' => $system, 'prompt' => $prompt];

                return $this->response !== null && $validator($this->response) ? $this->response : null;
            }
        };
        $fake->response = $response;
        $this->app->instance(AiClient::class, $fake);

        return $fake;
    }

    // ------------------------------------------------------------ Dalal AI

    public function test_duplicate_join_attempts_create_one_deduplicated_alert(): void
    {
        $s = $this->service();
        $u = User::factory()->create();
        $m = app(QueueManager::class);
        $first = $m->join($s, $u);
        foreach (range(1, 4) as $i) {
            $this->assertSame($first->id, $m->join($s->fresh(), $u)->id);   // prevention still works
        }

        $this->assertSame(1, Ticket::count());
        $this->assertSame(4, IntegritySignal::where('type', 'duplicate_join')->count());
        $alerts = FraudAlert::where('rule', 'duplicate_token_attempts')->get();
        $this->assertCount(1, $alerts);
        $this->assertSame($u->id, $alerts[0]->subject_user_id);
        $this->assertSame(4, $alerts[0]->signal_count);
        $this->assertSame('open', $alerts[0]->status);
        $this->assertSame(4, $alerts[0]->evidence['facts']['count']);
    }

    public function test_two_duplicate_attempts_stay_below_threshold(): void
    {
        $s = $this->service();
        $u = User::factory()->create();
        $this->join($s, $u);
        $this->join($s, $u);
        $this->join($s, $u);

        $this->assertSame(0, FraudAlert::count());
    }

    public function test_shared_network_burst_is_low_severity_with_careful_wording(): void
    {
        $s = $this->service();
        $old = User::factory()->create(['created_at' => now()->subDays(5)]);
        $this->actingAs($old)->post(route('join', $s))->assertRedirect();   // established accounts do not count

        foreach (range(1, 2) as $i) {
            $this->actingAs(User::factory()->create())->post(route('join', $s))->assertRedirect();
        }
        $this->assertSame(0, FraudAlert::where('rule', 'new_account_burst_shared_network')->count());

        $this->actingAs(User::factory()->create())->post(route('join', $s))->assertRedirect();

        $alert = FraudAlert::where('rule', 'new_account_burst_shared_network')->sole();
        $this->assertSame('low', $alert->severity);
        $this->assertLessThanOrEqual(30, $alert->risk_score);
        $this->assertNull($alert->subject_user_id);   // a pattern, not an accusation of one person
        $this->assertStringContainsString('Shared networks are common', $alert->explanation);
        $this->assertStringContainsString('never conclusive', $alert->explanation);
        // Raw IPs are never stored.
        $this->assertFalse(IntegritySignal::where('network_hash', 'like', '%127.0.0.1%')->exists());
        $this->assertSame(32, strlen(IntegritySignal::whereNotNull('network_hash')->value('network_hash')));
    }

    public function test_ai_disabled_falls_back_to_template_explanation(): void
    {
        config(['ai.provider' => 'none']);
        $s = $this->service();
        $u = User::factory()->create();
        foreach (range(1, 4) as $i) {
            $this->join($s, $u);
        }
        $alert = FraudAlert::sole();
        $this->assertSame(ExplanationService::TEMPLATE_PROVIDER, $alert->explanation_provider);

        [$text, $provider] = app(ExplanationService::class)->explain($alert, true);
        $this->assertSame(ExplanationService::TEMPLATE_PROVIDER, $provider);
        $this->assertStringContainsString('internal signal for human review', $text);

        $this->actingAs($this->admin())->post(route('integrity.admin.explain', $alert))->assertRedirect();
        $this->assertSame(ExplanationService::TEMPLATE_PROVIDER, $alert->fresh()->explanation_provider);
    }

    public function test_no_personal_data_is_sent_to_the_ai_provider(): void
    {
        $s = $this->service(['prefix' => 'Q']);
        $u = User::factory()->create(['name' => 'Ramesh Uniquename', 'email' => 'ramesh.unique@example.np', 'phone' => '9800000001']);
        foreach (range(1, 4) as $i) {
            $this->join($s, $u);
        }
        $alert = FraudAlert::sole();
        $ticket = Ticket::sole();

        $ai = $this->fakeAi(['summary' => 'This account repeatedly tried to take another token within a short window. It is a signal for human review only.']);
        [$text, $provider] = app(ExplanationService::class)->explain($alert, true);

        $this->assertCount(1, $ai->calls);
        $payload = $ai->calls[0]['system'].$ai->calls[0]['prompt'];
        foreach (['Ramesh', 'Uniquename', 'ramesh.unique', '@', '9800000001', '127.0.0.1', $ticket->token(), $s->name, $s->location->name] as $pii) {
            $this->assertStringNotContainsString($pii, $ai->calls[0]['prompt'], "AI prompt leaked: {$pii}");
        }
        $this->assertStringNotContainsString((string) IntegritySignal::whereNotNull('network_hash')->value('network_hash'), $payload);
        $this->assertStringContainsString('duplicate_token_attempts', $ai->calls[0]['prompt']);
        $this->assertStringContainsString('"count":3', $ai->calls[0]['prompt']);
        $this->assertStringStartsWith('AI · fake', $provider);
        $this->assertStringContainsString('human review', $text);
    }

    public function test_invalid_or_accusatory_ai_output_is_rejected(): void
    {
        $s = $this->service();
        $u = User::factory()->create();
        foreach (range(1, 4) as $i) {
            $this->join($s, $u);
        }
        $this->fakeAi(['summary' => 'This user is clearly a fraudster abusing the queue system again and again.']);
        [, $provider] = app(ExplanationService::class)->explain(FraudAlert::sole(), true);
        $this->assertSame(ExplanationService::TEMPLATE_PROVIDER, $provider);
    }

    public function test_admin_reviews_and_dismisses_alert_with_outcome_recorded(): void
    {
        $s = $this->service();
        $u = User::factory()->create();
        foreach (range(1, 4) as $i) {
            $this->join($s, $u);
        }
        $alert = FraudAlert::sole();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('integrity.admin.index'))->assertOk()->assertSee('Repeated duplicate-token attempts');
        $this->actingAs($admin)->get(route('integrity.admin.show', $alert))->assertOk()->assertSee('Why this was flagged')->assertSee('Record decision');

        // A note is required.
        $this->actingAs($admin)->post(route('integrity.admin.review', $alert), ['decision' => 'dismiss'])->assertSessionHasErrors('note');
        $this->assertSame('open', $alert->fresh()->status);

        $this->actingAs($admin)->post(route('integrity.admin.review', $alert), ['decision' => 'dismiss', 'note' => 'Slow 3G connection, visitor tapped join repeatedly.'])
            ->assertRedirect(route('integrity.admin.show', $alert));

        $alert->refresh();
        $this->assertSame('dismissed', $alert->status);
        $this->assertSame('false_positive', $alert->review_outcome);
        $this->assertSame($admin->id, $alert->reviewed_by);
        $this->assertNotNull($alert->reviewed_at);
        $this->assertTrue(AuditLog::where('action', 'integrity_alert_false_positive')->where('subject_id', $alert->id)->where('actor_id', $admin->id)->exists());

        // Further attempts the same day refresh evidence but never reopen a reviewed alert.
        $this->join($s, $u);
        $this->assertSame('dismissed', $alert->fresh()->status);
        $this->assertSame(1, FraudAlert::count());

        $this->actingAs($admin)->post(route('integrity.admin.review', $alert), ['decision' => 'escalate', 'note' => 'Second look requested by supervisor.']);
        $this->assertSame('escalated', $alert->fresh()->status);
    }

    public function test_staff_and_users_cannot_see_or_review_alerts(): void
    {
        $s = $this->service();
        $u = User::factory()->create();
        foreach (range(1, 4) as $i) {
            $this->join($s, $u);
        }
        $alert = FraudAlert::sole();
        $staff = $this->staffFor($s->location);

        foreach ([$staff, $u, User::factory()->create()] as $who) {
            $this->actingAs($who)->get(route('integrity.admin.index'))->assertForbidden();
            $this->actingAs($who)->get(route('integrity.admin.show', $alert))->assertForbidden();
            $this->actingAs($who)->post(route('integrity.admin.review', $alert), ['decision' => 'dismiss', 'note' => 'trying to hide it'])->assertForbidden();
            $this->actingAs($who)->post(route('integrity.admin.scan'))->assertForbidden();
        }
        $this->assertSame('open', $alert->fresh()->status);
        // Users never see alerts on their own pages either.
        $this->actingAs($u)->get(route('integrity.exchange.index'))->assertOk()->assertDontSee('duplicate-token');
    }

    public function test_staff_report_is_scoped_to_assigned_location(): void
    {
        $s = $this->service();
        $other = $this->service(['prefix' => 'H']);
        $staff = $this->staffFor($s->location);
        $mine = $this->join($s);
        $foreign = $this->join($other);

        $this->actingAs($staff)->post(route('integrity.staff.report', $foreign), ['reason' => 'same_person_two_tokens', 'note' => 'Saw the same visitor with two tokens today.'])
            ->assertForbidden();
        $this->assertSame(0, FraudAlert::count());

        $this->actingAs($staff)->post(route('integrity.staff.report', $mine), ['reason' => 'same_person_two_tokens', 'note' => 'short'])
            ->assertSessionHasErrors('note');

        $this->actingAs($staff)->from(route('integrity.staff.exchanges'))
            ->post(route('integrity.staff.report', $mine), ['reason' => 'same_person_two_tokens', 'note' => 'Same visitor presented two printed tokens at the desk.'])
            ->assertRedirect(route('integrity.staff.exchanges'));
        $alert = FraudAlert::sole();
        $this->assertSame('staff_report', $alert->rule);
        $this->assertSame($mine->id, $alert->subject_ticket_id);
        $this->assertSame($staff->id, $alert->reported_by);
        $this->assertSame('waiting', $mine->fresh()->status);   // the report never changes the token

        // Regular users cannot file staff reports.
        $this->actingAs($mine->user)->post(route('integrity.staff.report', $mine), ['reason' => 'other', 'note' => 'Reporting myself for fun.'])->assertForbidden();
    }

    public function test_staff_absent_burst_and_rejected_transitions_raise_alerts(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $m = app(QueueManager::class);
        $tickets = collect(range(1, 5))->map(fn () => $this->join($s));
        $tickets->each(fn ($t) => $m->markAbsent($t, $staff));

        $alert = FraudAlert::where('rule', 'staff_absent_cancel_burst')->sole();
        $this->assertSame($staff->id, $alert->subject_user_id);
        $this->assertSame(5, $alert->evidence['facts']['absent']);

        foreach ($tickets->take(3) as $t) {
            try {
                $m->startService($t, $staff);   // absent → serving is not allowed
            } catch (\App\Services\Queue\QueueException) {
            }
        }
        $this->assertSame(1, FraudAlert::where('rule', 'repeated_rejected_transitions')->count());
    }

    public function test_call_next_burst_above_service_baseline_is_flagged_by_z_score(): void
    {
        $s = $this->service(['last_number' => 10]);
        $staff = $this->staffFor($s->location);
        // Baseline: ~2 calls per 15-minute window across six earlier windows.
        foreach (range(1, 6) as $w) {
            foreach (range(1, 2) as $i) {
                $sig = IntegritySignal::create(['type' => 'staff_call_next', 'actor_id' => $staff->id, 'service_id' => $s->id, 'location_id' => $s->location_id]);
                $sig->forceFill(['created_at' => now()->subHours($w)->addMinutes($i)])->save();
            }
        }
        foreach (range(1, 10) as $i) {
            $this->join($s);
        }
        $m = app(QueueManager::class);
        foreach (range(1, 9) as $i) {
            $m->callNext($s->fresh(), $staff);
        }
        $alert = FraudAlert::where('rule', 'staff_call_next_anomaly')->sole();
        $this->assertGreaterThanOrEqual(3, $alert->evidence['facts']['z_score']);
        $this->assertSame(0, FraudAlert::where('rule', 'staff_absent_cancel_burst')->count());
    }

    public function test_prune_command_removes_signals_past_retention(): void
    {
        $old = IntegritySignal::create(['type' => 'joined']);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        IntegritySignal::create(['type' => 'joined']);

        $this->artisan('integrity:prune')->assertSuccessful();
        $this->assertSame(1, IntegritySignal::count());
        $this->artisan('dalal:scan --no-ai')->assertSuccessful();
    }

    // ------------------------------------------------------------ Queue exchange

    public function test_exchange_swaps_positions_atomically_and_notifies_both(): void
    {
        [$s, $a, $b, $ua, $ub] = $this->pair();
        [$na, $nb] = [$a->number, $b->number];

        $this->requestExchange($a, $ua, $s->token($nb))->assertRedirect()->assertSessionHasNoErrors();
        $x = QueueExchangeRequest::sole();
        $this->assertSame('pending', $x->status);
        $this->assertSame($ub->id, $x->recipient_id);
        $this->assertSame($na, $a->fresh()->number);   // nothing changes before acceptance
        $this->assertStringContainsString('asks to exchange', $ub->notifications()->first()->data['message']);

        $this->actingAs($ub)->post(route('integrity.exchange.accept', $x))->assertRedirect()->assertSessionHasNoErrors();

        $a->refresh();
        $b->refresh();
        $this->assertSame($nb, $a->number);
        $this->assertSame($na, $b->number);
        $this->assertSame([$ua->id, $ub->id], [$a->user_id, $b->user_id]);   // identities intact
        $this->assertSame(['waiting', 'waiting'], [$a->status, $b->status]);
        $this->assertSame('completed', $x->fresh()->status);
        $this->assertNull($x->fresh()->open_key);
        $this->assertSame(2, QueueEvent::where('type', 'exchanged')->whereIn('ticket_id', [$a->id, $b->id])->count());
        $this->assertSame(2, AuditLog::where('action', 'queue_exchange_completed')->count());
        $this->assertTrue($ua->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'Your token is now '.$s->token($nb))));
        $this->assertTrue($ub->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'Your token is now '.$s->token($na))));
        $this->assertSame(3, Ticket::where('service_id', $s->id)->count());
        $this->assertSame(Ticket::where('service_id', $s->id)->count(), Ticket::where('service_id', $s->id)->distinct()->count('number'));
    }

    public function test_exchange_is_only_possible_within_the_same_service(): void
    {
        [$s, $a, , $ua] = $this->pair();
        $other = $this->service(['prefix' => 'X', 'name' => 'Other'], $s->location);
        $foreign = $this->join($other);

        $this->requestExchange($a, $ua, $other->token($foreign->number))->assertSessionHasErrors('queue');
        $this->requestExchange($a, $ua, $s->token(999))->assertSessionHasErrors('queue');   // not in this queue
        $this->assertSame(0, QueueExchangeRequest::count());
        $this->assertSame(2, IntegritySignal::where('type', 'rule_violation')->count());
    }

    public function test_only_the_recipient_can_accept_and_third_parties_cannot_act(): void
    {
        [$s, $a, $b, $ua, $ub] = $this->pair();
        $intruder = User::factory()->create();
        $this->join($s, $intruder);

        // Third party cannot request an exchange using someone else's ticket.
        $this->requestExchange($a, $intruder, $s->token($b->number))->assertForbidden();

        $this->requestExchange($a, $ua, $s->token($b->number));
        $x = QueueExchangeRequest::sole();

        $this->actingAs($intruder)->post(route('integrity.exchange.accept', $x))->assertForbidden();
        $this->actingAs($intruder)->post(route('integrity.exchange.decline', $x))->assertForbidden();
        $this->actingAs($intruder)->post(route('integrity.exchange.cancel', $x))->assertForbidden();
        $this->actingAs($ua)->post(route('integrity.exchange.accept', $x))->assertForbidden();   // requester cannot self-accept
        $this->actingAs($ub)->post(route('integrity.exchange.cancel', $x))->assertForbidden();   // recipient cannot cancel on requester's behalf
        $this->actingAs($ua)->post(route('integrity.staff.exchanges.approve', $x))->assertForbidden();

        $this->assertSame('pending', $x->fresh()->status);
        $this->assertSame($a->number, $a->fresh()->number);
        $this->assertGreaterThanOrEqual(5, IntegritySignal::where('type', 'rule_violation')->where('actor_id', $intruder->id)->count() + IntegritySignal::where('type', 'rule_violation')->where('actor_id', $ua->id)->count());

        $this->actingAs($ua)->post(route('integrity.exchange.cancel', $x))->assertRedirect();
        $this->assertSame('cancelled', $x->fresh()->status);
    }

    public function test_expiry_is_enforced_server_side(): void
    {
        [$s, $a, $b, $ua, $ub] = $this->pair();
        $this->requestExchange($a, $ua, $s->token($b->number));
        $x = QueueExchangeRequest::sole();

        $this->travel(config('integrity.exchange.expiry_minutes') + 1)->minutes();
        $this->actingAs($ub)->post(route('integrity.exchange.accept', $x))->assertSessionHasErrors('queue');

        $this->assertSame('expired', $x->fresh()->status);
        $this->assertSame($a->number, $a->fresh()->number);
        $this->assertSame($b->number, $b->fresh()->number);

        // The slot is free again: a new request can be made.
        $this->requestExchange($a, $ua, $s->token($b->number))->assertSessionHasNoErrors();
        $this->assertSame(1, QueueExchangeRequest::open()->count());
    }

    public function test_staff_approval_is_required_when_configured(): void
    {
        [$s, $a, $b, $ua, $ub] = $this->pair();
        $s->forceFill(['requires_exchange_approval' => true])->save();
        [$na, $nb] = [$a->number, $b->number];
        $staff = $this->staffFor($s->location);
        $otherStaff = $this->staffFor($this->service(['prefix' => 'H'])->location);

        $this->requestExchange($a, $ua, $s->token($nb));
        $x = QueueExchangeRequest::sole();
        $this->actingAs($ub)->post(route('integrity.exchange.accept', $x))->assertSessionHasNoErrors();

        $this->assertSame('awaiting_staff', $x->fresh()->status);
        $this->assertSame($na, $a->fresh()->number);   // never before all approvals

        $this->actingAs($staff)->get(route('integrity.staff.exchanges'))->assertOk()->assertSee($s->token($na));
        $this->actingAs($otherStaff)->post(route('integrity.staff.exchanges.approve', $x))->assertForbidden();
        $this->actingAs($ub)->post(route('integrity.staff.exchanges.approve', $x))->assertForbidden();
        $this->assertSame($na, $a->fresh()->number);

        $this->actingAs($staff)->post(route('integrity.staff.exchanges.approve', $x), ['note' => 'Both present at desk'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $x->fresh()->status);
        $this->assertSame($staff->id, $x->fresh()->decided_by);
        $this->assertSame([$nb, $na], [$a->fresh()->number, $b->fresh()->number]);
    }

    public function test_exchange_is_rejected_when_service_disallows_it_or_a_ticket_stops_waiting(): void
    {
        [$s, $a, $b, $ua, $ub] = $this->pair(['allow_exchange' => false]);
        $this->requestExchange($a, $ua, $s->token($b->number))->assertSessionHasErrors('queue');
        $this->assertSame(0, QueueExchangeRequest::count());

        [$s2, $c, $d, $uc, $ud] = $this->pair(['prefix' => 'K']);
        $this->requestExchange($c, $uc, $s2->token($d->number))->assertSessionHasNoErrors();
        $x = QueueExchangeRequest::sole();
        app(QueueManager::class)->leave($c, $uc);   // requester leaves before acceptance

        $this->actingAs($ud)->post(route('integrity.exchange.accept', $x))->assertSessionHasErrors('queue');
        $this->assertSame('failed', $x->fresh()->status);
        $this->assertSame($d->number, $d->fresh()->number);
    }

    public function test_repeated_exchange_requests_feed_dalal_ai(): void
    {
        $s = $this->service();
        $u = User::factory()->create();
        $mine = $this->join($s, $u);
        foreach (range(1, 3) as $i) {
            $other = $this->join($s);
            $this->requestExchange($mine, $u, $s->token($other->number))->assertSessionHasNoErrors();
            $this->actingAs($u)->post(route('integrity.exchange.cancel', QueueExchangeRequest::latest('id')->first()));
        }
        $this->assertSame(1, FraudAlert::where('rule', 'repeated_exchanges')->where('subject_user_id', $u->id)->count());
    }

    public function test_exchange_pages_render_for_participants(): void
    {
        [$s, $a, $b, $ua, $ub] = $this->pair();
        $this->requestExchange($a, $ua, $s->token($b->number));

        $this->actingAs($ub)->get(route('integrity.exchange.index'))->assertOk()->assertSee('asks to swap places with you');
        $this->actingAs($ua)->get(route('integrity.exchange.index'))->assertOk()->assertSee('Withdraw');
        $this->actingAs($ub)->getJson(route('integrity.exchange.poll'))->assertOk()->assertJsonPath('data.incoming', 1);
        $this->actingAs($ua)->get(route('ticket', $a))->assertOk();

        $this->actingAs($ub);
        $html = view('integrity.partials.ticket-widget', ['ticket' => $b->fresh('service')])->render();
        $this->assertStringContainsString('Swap places', $html);
    }
}
