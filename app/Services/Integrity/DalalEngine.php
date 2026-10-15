<?php

namespace App\Services\Integrity;

use App\Http\Requests\Integrity\StaffReportRequest;
use App\Models\FraudAlert;
use App\Models\IntegritySignal;
use App\Models\QueueExchangeRequest;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dalal AI: deterministic rules + a simple statistical anomaly check over integrity signals.
 *
 * Principles (master prompt §20):
 *  - Alerts are internal review signals, deduplicated by fingerprint (one per subject/rule/day).
 *  - Nothing here blocks, penalises or labels a person. A human reviewer decides.
 *  - Shared networks / devices are never treated as conclusive (that rule is capped at LOW).
 */
class DalalEngine
{
    public function __construct(private ExplanationService $explainer)
    {
    }

    /** Cheap, scoped evaluation right after one new signal. */
    public function afterSignal(IntegritySignal $s): void
    {
        match ($s->type) {
            'duplicate_join' => $s->user_id && $s->service_id ? $this->duplicateTokens($s->user_id, $s->service_id) : null,
            'joined' => $this->afterJoin($s),
            'left' => $s->user_id ? $this->churn($s->user_id) : null,
            'exchange_requested' => $s->actor_id ? $this->repeatedExchanges($s->actor_id) : null,
            'staff_absent', 'staff_cancel' => $s->actor_id ? $this->staffAbsentCancel($s->actor_id) : null,
            'staff_call_next' => $s->actor_id && $s->service_id ? $this->staffCallNext($s->actor_id, $s->service_id) : null,
            'rejected_transition' => $s->actor_id ? $this->rejectedTransitions($s->actor_id) : null,
            'rule_violation' => $s->actor_id ? $this->ruleBypass($s->actor_id) : null,
            default => null,
        };
    }

    private function afterJoin(IntegritySignal $s): void
    {
        if ($s->user_id && $s->location_id) {
            $this->multiTokens($s->user_id, $s->location_id);
        }
        if ($s->service_id && $s->network_hash) {
            $this->accountBurst($s->service_id, $s->network_hash);
        }
    }

    /**
     * Full pass over the last 24h of signals (artisan dalal:scan, scheduled every 15 min).
     * Also expires stale exchange requests and, when AI is on, rewords template explanations.
     */
    public function scan(bool $withAi = true): array
    {
        $since = now()->subDay();
        $raised = 0;
        $count = function (?FraudAlert $a) use (&$raised) {
            $raised += $a ? 1 : 0;
        };

        $recent = IntegritySignal::where('created_at', '>=', $since)->get();

        foreach ($recent->where('type', 'duplicate_join')->groupBy(fn ($s) => $s->user_id.':'.$s->service_id) as $group) {
            $count($this->duplicateTokens($group->first()->user_id, $group->first()->service_id));
        }
        foreach ($recent->where('type', 'left')->pluck('user_id')->filter()->unique() as $u) {
            $count($this->churn($u));
        }
        foreach ($recent->where('type', 'joined')->groupBy(fn ($s) => $s->user_id.':'.$s->location_id) as $group) {
            if ($group->first()->user_id && $group->first()->location_id) {
                $count($this->multiTokens($group->first()->user_id, $group->first()->location_id));
            }
        }
        foreach ($recent->where('type', 'joined')->whereNotNull('network_hash')->groupBy(fn ($s) => $s->service_id.':'.$s->network_hash) as $group) {
            $count($this->accountBurst($group->first()->service_id, $group->first()->network_hash, $group->max('created_at')));
        }
        foreach ($recent->where('type', 'exchange_requested')->pluck('actor_id')->filter()->unique() as $u) {
            $count($this->repeatedExchanges($u));
        }
        foreach ($recent->whereIn('type', ['staff_absent', 'staff_cancel'])->pluck('actor_id')->filter()->unique() as $u) {
            $count($this->staffAbsentCancel($u));
        }
        foreach ($recent->where('type', 'staff_call_next')->groupBy(fn ($s) => $s->actor_id.':'.$s->service_id) as $group) {
            $count($this->staffCallNext($group->first()->actor_id, $group->first()->service_id));
        }
        foreach ($recent->where('type', 'rejected_transition')->pluck('actor_id')->filter()->unique() as $u) {
            $count($this->rejectedTransitions($u));
        }
        foreach ($recent->where('type', 'rule_violation')->pluck('actor_id')->filter()->unique() as $u) {
            $count($this->ruleBypass($u));
        }

        $expired = app(ExchangeService::class)->expireStale();

        $reworded = 0;
        if ($withAi) {
            FraudAlert::whereIn('status', FraudAlert::OPEN_STATUSES)->where('explanation_provider', ExplanationService::TEMPLATE_PROVIDER)
                ->latest('id')->take(25)->get()
                ->each(function (FraudAlert $a) use (&$reworded) {
                    [$text, $provider] = $this->explainer->explain($a, true);
                    if ($provider !== ExplanationService::TEMPLATE_PROVIDER) {
                        $a->forceFill(['explanation' => $text, 'explanation_provider' => $provider])->save();
                        $reworded++;
                    }
                });
        }

        return ['signals' => $recent->count(), 'alerts_matched' => $raised, 'exchanges_expired' => $expired, 'ai_reworded' => $reworded];
    }

    // ------------------------------------------------------------------ rules

    public function duplicateTokens(int $userId, int $serviceId): ?FraudAlert
    {
        ['count' => $min, 'window' => $win] = config('integrity.rules.duplicate_tokens');
        $signals = $this->signals('duplicate_join', $win)->where('user_id', $userId)->where('service_id', $serviceId)->get();
        if ($signals->count() < $min) {
            return null;
        }
        $retries = $this->signals('idempotent_retry', $win)->where('user_id', $userId)->where('service_id', $serviceId)->count();

        return $this->raise('duplicate_tokens', 'duplicate_token_attempts', "u{$userId}:s{$serviceId}", $signals,
            min(85, 40 + ($signals->count() - $min) * 10),
            ['count' => $signals->count(), 'window_minutes' => $win, 'threshold' => $min, 'idempotent_retries' => $retries],
            ['user' => $userId, 'service' => $serviceId, 'ticket' => $signals->last()->ticket_id]);
    }

    public function churn(int $userId): ?FraudAlert
    {
        ['count' => $min, 'window' => $win] = config('integrity.rules.churn');
        $signals = $this->signals('left', $win)->where('user_id', $userId)->get();
        if ($signals->count() < $min) {
            return null;
        }

        return $this->raise('churn', 'join_leave_churn', "u{$userId}", $signals,
            min(70, 30 + ($signals->count() - $min) * 10),
            ['count' => $signals->count(), 'window_minutes' => $win, 'threshold' => $min, 'services' => $signals->pluck('service_id')->unique()->count()],
            ['user' => $userId, 'service' => $signals->last()->service_id]);
    }

    public function multiTokens(int $userId, int $locationId): ?FraudAlert
    {
        $min = config('integrity.rules.multi_tokens.count');
        $tickets = Ticket::with('service')->where('user_id', $userId)->whereIn('status', Ticket::ACTIVE)
            ->whereHas('service', fn ($q) => $q->where('location_id', $locationId))->get();
        if ($tickets->count() < $min) {
            return null;
        }
        $signals = IntegritySignal::where('type', 'joined')->where('user_id', $userId)->whereIn('ticket_id', $tickets->pluck('id'))->get();

        return $this->raise('multi_tokens', 'simultaneous_active_tokens', "u{$userId}:l{$locationId}", $signals,
            min(80, 45 + ($tickets->count() - $min) * 10),
            ['count' => $tickets->count(), 'threshold' => $min, 'services' => $tickets->pluck('service_id')->unique()->count()],
            ['user' => $userId, 'service' => $tickets->last()->service_id, 'ticket' => $tickets->last()->id],
            ['tokens' => $tickets->map(fn ($t) => $t->token())->values()->all()]);
    }

    /** LOW severity only: shared networks are common and never conclusive. */
    public function accountBurst(int $serviceId, string $networkHash, ?Carbon $at = null): ?FraudAlert
    {
        ['count' => $min, 'window' => $win, 'account_age_hours' => $age] = config('integrity.rules.account_burst');
        $at ??= now();
        $signals = IntegritySignal::where('type', 'joined')->where('service_id', $serviceId)->where('network_hash', $networkHash)
            ->whereBetween('created_at', [$at->copy()->subMinutes($win), $at])
            ->whereIn('user_id', User::where('created_at', '>=', $at->copy()->subHours($age))->select('id'))
            ->orderBy('id')->get();
        $accounts = $signals->pluck('user_id')->unique()->count();
        if ($accounts < $min) {
            return null;
        }

        return $this->raise('account_burst', 'new_account_burst_shared_network', 's'.$serviceId.':n'.substr($networkHash, 0, 12), $signals,
            min(30, 15 + ($accounts - $min) * 5),
            ['count' => $accounts, 'window_minutes' => $win, 'threshold' => $min, 'account_age_hours' => $age],
            ['service' => $serviceId],
            ['network_ref' => substr($networkHash, 0, 8).'…', 'browser_families' => $signals->pluck('ua_family')->filter()->unique()->values()->all()],
            'low');
    }

    public function repeatedExchanges(int $userId): ?FraudAlert
    {
        ['count' => $min, 'window' => $win] = config('integrity.rules.exchanges');
        $signals = $this->signals('exchange_requested', $win)->where('actor_id', $userId)->get();
        if ($signals->count() < $min) {
            return null;
        }
        $completed = QueueExchangeRequest::where('requester_id', $userId)->where('status', 'completed')
            ->where('completed_at', '>=', now()->subMinutes($win))->count();

        return $this->raise('exchanges', 'repeated_exchanges', "u{$userId}", $signals,
            min(70, 35 + ($signals->count() - $min) * 10),
            ['count' => $signals->count(), 'window_minutes' => $win, 'threshold' => $min, 'completed_exchanges' => $completed],
            ['user' => $userId, 'service' => $signals->last()->service_id]);
    }

    public function staffAbsentCancel(int $staffId): ?FraudAlert
    {
        ['count' => $min, 'window' => $win] = config('integrity.rules.staff_absent_cancel');
        $signals = IntegritySignal::whereIn('type', ['staff_absent', 'staff_cancel'])->where('actor_id', $staffId)
            ->where('created_at', '>=', now()->subMinutes($win))->orderBy('id')->get();
        if ($signals->count() < $min) {
            return null;
        }

        return $this->raise('staff_actions', 'staff_absent_cancel_burst', "staff{$staffId}", $signals,
            min(85, 50 + ($signals->count() - $min) * 5),
            ['count' => $signals->count(), 'window_minutes' => $win, 'threshold' => $min,
                'absent' => $signals->where('type', 'staff_absent')->count(), 'cancelled' => $signals->where('type', 'staff_cancel')->count()],
            ['user' => $staffId, 'service' => $signals->last()->service_id]);
    }

    /** z-score of this staff member's recent call-next count vs the service's own 15-minute baseline. */
    public function staffCallNext(int $staffId, int $serviceId): ?FraudAlert
    {
        $cfg = config('integrity.rules.staff_call_next');
        $win = $cfg['window'];
        $recent = $this->signals('staff_call_next', $win)->where('actor_id', $staffId)->where('service_id', $serviceId)->get();
        if ($recent->count() < $cfg['min_count']) {
            return null;
        }

        $buckets = IntegritySignal::where('type', 'staff_call_next')->where('service_id', $serviceId)
            ->whereBetween('created_at', [now()->subDays($cfg['baseline_days']), now()->subMinutes($win)])
            ->pluck('created_at')
            ->countBy(fn ($t) => intdiv(Carbon::parse($t)->getTimestamp(), $win * 60))
            ->values();
        if ($buckets->count() < $cfg['min_buckets']) {
            return null;   // not enough history for a fair baseline
        }
        $mean = $buckets->avg();
        $std = sqrt($buckets->map(fn ($c) => ($c - $mean) ** 2)->sum() / $buckets->count());
        $z = ($recent->count() - $mean) / max($std, 1.0);
        if ($z < $cfg['z']) {
            return null;
        }

        return $this->raise('staff_actions', 'staff_call_next_anomaly', "staff{$staffId}:s{$serviceId}", $recent,
            (int) min(90, 40 + round($z * 5)),
            ['count' => $recent->count(), 'window_minutes' => $win, 'baseline_mean' => round($mean, 1), 'baseline_std' => round($std, 1),
                'z_score' => round($z, 1), 'baseline_buckets' => $buckets->count()],
            ['user' => $staffId, 'service' => $serviceId]);
    }

    public function rejectedTransitions(int $actorId): ?FraudAlert
    {
        ['count' => $min, 'window' => $win] = config('integrity.rules.rejected_transitions');
        $signals = $this->signals('rejected_transition', $win)->where('actor_id', $actorId)->get();
        if ($signals->count() < $min) {
            return null;
        }

        return $this->raise('transitions', 'repeated_rejected_transitions', "a{$actorId}", $signals,
            min(70, 30 + ($signals->count() - $min) * 10),
            ['count' => $signals->count(), 'window_minutes' => $win, 'threshold' => $min],
            ['user' => $actorId, 'service' => $signals->last()->service_id, 'ticket' => $signals->last()->ticket_id]);
    }

    public function ruleBypass(int $actorId): ?FraudAlert
    {
        ['count' => $min, 'window' => $win] = config('integrity.rules.rule_bypass');
        $signals = $this->signals('rule_violation', $win)->where('actor_id', $actorId)->get();
        if ($signals->count() < $min) {
            return null;
        }

        return $this->raise('rule_bypass', 'repeated_rule_bypass_attempts', "a{$actorId}", $signals,
            min(80, 40 + ($signals->count() - $min) * 10),
            ['count' => $signals->count(), 'window_minutes' => $win, 'threshold' => $min],
            ['user' => $actorId, 'service' => $signals->last()->service_id],
            ['rules_hit' => $signals->map(fn ($s) => $s->meta['rule'] ?? 'unknown')->countBy()->all()]);
    }

    /** Staff-filed report for one ticket at their location. Deduped per staff + ticket + day. */
    public function staffReport(Ticket $ticket, User $staff, string $reason, string $note): FraudAlert
    {
        $signal = app(SignalRecorder::class)->record('staff_report', $ticket, $staff, ['reason' => $reason], evaluate: false);

        return $this->raise('staff_report', 'staff_report', "t{$ticket->id}:staff{$staff->id}", collect([$signal])->filter(),
            50,
            ['count' => 1, 'active_tokens_same_service' => Ticket::where('service_id', $ticket->service_id)->where('user_id', $ticket->user_id)->whereIn('status', Ticket::ACTIVE)->count()],
            ['user' => $ticket->user_id, 'service' => $ticket->service_id, 'ticket' => $ticket->id],
            ['reason' => StaffReportRequest::REASONS[$reason] ?? $reason, 'staff_note' => $note, 'token' => $ticket->token()],
            null, $staff->id);
    }

    // ---------------------------------------------------------------- helpers

    private function signals(string $type, int $windowMinutes)
    {
        return IntegritySignal::where('type', $type)->where('created_at', '>=', now()->subMinutes($windowMinutes))->orderBy('id');
    }

    /**
     * Create or refresh the alert for this rule + subject + day. Reviewed alerts keep their
     * status and review; only the evidence and counts are refreshed.
     */
    private function raise(string $type, string $rule, string $subjectKey, Collection $signals, int $score, array $facts, array $subject,
        array $extra = [], ?string $forceSeverity = null, ?int $reportedBy = null): FraudAlert
    {
        $fingerprint = substr($rule.':'.$subjectKey.':'.now()->format('Ymd'), 0, 120);
        $score = max(0, min(100, $score));
        $severity = $forceSeverity ?? FraudAlert::severityFor($score);
        $evidence = [
            'facts' => $facts,
            'signal_ids' => $signals->pluck('id')->take(-50)->values()->all(),
        ] + $extra;

        $alert = FraudAlert::firstOrNew(['fingerprint' => $fingerprint]);
        $isNew = ! $alert->exists;
        $alert->fill([
            'type' => $type,
            'rule' => $rule,
            'risk_score' => $isNew ? $score : max($score, (int) $alert->risk_score),
            'severity' => $isNew ? $severity : ($forceSeverity ?? FraudAlert::severityFor(max($score, (int) $alert->risk_score))),
            'subject_user_id' => $subject['user'] ?? null,
            'subject_ticket_id' => $subject['ticket'] ?? null,
            'subject_service_id' => $subject['service'] ?? null,
            'evidence' => $isNew ? $evidence : array_replace($alert->evidence ?? [], $evidence, [
                'signal_ids' => collect($alert->evidence['signal_ids'] ?? [])->merge($evidence['signal_ids'])->unique()->take(-50)->values()->all(),
            ]),
            'recommended_actions' => $this->actions($rule),
            'signal_count' => max($signals->count(), $isNew ? 0 : (int) $alert->signal_count),
            'first_seen_at' => $isNew ? ($signals->first()?->created_at ?? now()) : $alert->first_seen_at,
            'last_seen_at' => $signals->last()?->created_at ?? now(),
        ]);
        if ($isNew) {
            $alert->fill(['status' => 'open', 'reported_by' => $reportedBy]);
        }
        // Keep an AI-worded explanation once produced; refresh template text as counts change.
        if ($isNew || $alert->explanation_provider === ExplanationService::TEMPLATE_PROVIDER) {
            $alert->fill(['explanation' => $this->explainer->template($alert), 'explanation_provider' => ExplanationService::TEMPLATE_PROVIDER]);
        }
        $alert->save();

        return $alert;
    }

    /** Recommended review steps. Always human-led, never automatic penalties. */
    public function actions(string $rule): array
    {
        $common = ['Check the event timeline for an innocent explanation (slow network, shared device, staff instruction).'];

        return match ($rule) {
            'duplicate_token_attempts' => ['Confirm the account still holds only one active token for this service.',
                'If it continues, ask counter staff to check the visitor at the desk; do not cancel the token remotely.', ...$common],
            'join_leave_churn' => ['Look at whether the leaves happened before being called (normal) or right after joining (unusual).',
                'No action needed if the pattern stops.', ...$common],
            'simultaneous_active_tokens' => ['Check whether the services make sense together (e.g. ticket + payment desk).',
                'If not, staff may ask the visitor which token they need and release the rest with consent.', ...$common],
            'new_account_burst_shared_network' => ['Do not act on this alone: shared networks are common.',
                'Only correlate with other alerts for the same accounts; dismiss if nothing else stands out.'],
            'repeated_exchanges' => ['Review the exchange history for pressure or informal trading of places.',
                'Consider enabling staff approval for exchanges on this service if it repeats.', ...$common],
            'staff_absent_cancel_burst' => ['Ask the staff member or supervisor what happened at the counter in this period.',
                'Check whether affected visitors complained or re-joined.', ...$common],
            'staff_call_next_anomaly' => ['Compare with the counter situation at that time (extra staff, walk-ins, display issue).',
                'Check whether called tokens were later marked absent in bulk.', ...$common],
            'repeated_rejected_transitions' => ['Check for a stale screen or double-click pattern before assuming intent.', ...$common],
            'repeated_rule_bypass_attempts' => ['See which rules were hit; clearer instructions often fix this.',
                'Escalate only if attempts continue after guidance.', ...$common],
            'staff_report' => ['Read the staff note and compare it with the event timeline.',
                'Speak with the visitor at the counter before any change; never cancel based on the report alone.'],
            default => $common,
        };
    }
}
