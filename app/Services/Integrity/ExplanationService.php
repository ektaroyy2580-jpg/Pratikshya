<?php

namespace App\Services\Integrity;

use App\Models\FraudAlert;
use App\Services\Ai\AiClient;

/**
 * Plain-language explanations for Dalal AI alerts.
 *
 * The AI (when enabled) receives ONLY the rule name, its title, numeric counts and time
 * spans. No names, emails, phone numbers, IPs, network hashes, token labels or ids are
 * ever sent. Output is schema-checked; otherwise a deterministic template is used.
 */
class ExplanationService
{
    public const TEMPLATE_PROVIDER = 'template (deterministic)';

    private const FORBIDDEN = ['fraudster', 'criminal', 'guilty', 'cheater', 'proven', 'proof', 'liar', 'scammer', '@'];

    public function __construct(private AiClient $ai)
    {
    }

    /** @return array{0: string, 1: string} [explanation, provider] */
    public function explain(FraudAlert $alert, bool $allowAi = true): array
    {
        $facts = $this->facts($alert);

        if ($allowAi && config('integrity.ai_explanations') && $this->ai->enabled()) {
            $system = 'You explain internal queue-integrity review signals to a trained human reviewer at a public service office. '
                .'Use calm, neutral, factual language in 2–3 short sentences. Never accuse anyone, never call anyone a fraudster, '
                .'never claim proof. State that this is a signal for human review and that innocent explanations are possible. '
                .'Return JSON exactly like {"summary": "..."}.';
            $prompt = 'Signal facts (counts and time spans only): '.json_encode($facts, JSON_UNESCAPED_SLASHES);

            $data = $this->ai->json($system, $prompt, fn ($d) => $this->valid($d));
            if ($data) {
                $text = trim($data['summary']);
                if ($alert->rule === 'new_account_burst_shared_network' && ! str_contains(strtolower($text), 'shared network')) {
                    $text .= ' Shared networks are common, so this is never conclusive on its own.';
                }

                return [$text, 'AI · '.$this->ai->providerLabel()];
            }
        }

        return [$this->template($alert), self::TEMPLATE_PROVIDER];
    }

    /** The only data an AI provider may see: rule + numbers. */
    public function facts(FraudAlert $alert): array
    {
        $numbers = collect($alert->evidence['facts'] ?? [])
            ->filter(fn ($v) => is_int($v) || is_float($v))
            ->all();

        return [
            'rule' => $alert->rule,
            'rule_title' => $alert->title(),
            'severity' => $alert->severity,
            'risk_score' => (int) $alert->risk_score,
        ] + $numbers;
    }

    public function valid(mixed $d): bool
    {
        if (! is_array($d) || ! isset($d['summary']) || ! is_string($d['summary'])) {
            return false;
        }
        $len = mb_strlen(trim($d['summary']));
        if ($len < 30 || $len > 700) {
            return false;
        }
        $lower = mb_strtolower($d['summary']);
        foreach (self::FORBIDDEN as $word) {
            if (str_contains($lower, $word)) {
                return false;
            }
        }

        return true;
    }

    public function template(FraudAlert $alert): string
    {
        $f = $alert->evidence['facts'] ?? [];
        $n = (int) ($f['count'] ?? $alert->signal_count);
        $w = $this->span((int) ($f['window_minutes'] ?? 0));
        $review = ' This is an internal signal for human review, not a conclusion about anyone.';

        return match ($alert->rule) {
            'duplicate_token_attempts' => "The same account tried to take another token for a service it already holds {$n} times within {$w}. "
                .'The system safely returned the existing token each time, so the queue was not affected. '
                .'Repeated taps on a slow connection can cause this, but a pattern may also indicate an attempt to hold extra places.'.$review,
            'join_leave_churn' => "This account left a queue {$n} times within {$w}. "
                .'Plans change, so occasional leaving is normal; frequent join-and-leave cycles can, however, be used to probe or hold places.'.$review,
            'simultaneous_active_tokens' => "This account holds {$n} active tokens at the same location at once. "
                .'Some visitors genuinely need several services in one trip, so check whether the combination makes sense before acting.'.$review,
            'new_account_burst_shared_network' => "{$n} accounts created within the last day joined the same queue from one network within {$w}. "
                .'Shared networks are common (families, offices, hospitals, public Wi-Fi), so this is never conclusive on its own and is kept at low severity. '
                .'Only look further if other signals point the same way.'.$review,
            'repeated_exchanges' => "This account requested {$n} queue exchanges within {$w}. "
                .'Exchanges are voluntary and unpaid; a high number may mean someone is pressuring others or trading places informally.'.$review,
            'staff_absent_cancel_burst' => "A staff account marked {$n} tokens absent or cancelled within {$w}. "
                .'This can happen when many people step away at once, but it can also remove people from the queue unfairly.'.$review,
            'staff_call_next_anomaly' => "A staff account called {$n} tokens within {$w}, well above this service's usual pace (about "
                .($f['baseline_mean'] ?? '?').' per '.$w.'). Fast calling may skip people who were on their way back.'.$review,
            'repeated_rejected_transitions' => "{$n} status changes by one account were rejected by the queue rules within {$w}. "
                .'This often comes from double-clicks or stale screens, but repeated attempts can also indicate someone trying to force a state change.'.$review,
            'repeated_rule_bypass_attempts' => "One account had {$n} actions blocked by queue rules within {$w} (for example joining without required documents or invalid exchanges). "
                .'This is usually confusion about the rules, and occasionally a deliberate attempt to get around them.'.$review,
            'staff_report' => 'A staff member at this location reported a suspected duplicate-token situation for this entry. '
                .'Compare the staff note with the event history before deciding.'.$review,
            default => "Rule {$alert->rule} matched {$n} times within {$w}.".$review,
        };
    }

    private function span(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'a short period';
        }
        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            $d = intdiv($minutes, 1440);

            return $d === 1 ? '24 hours' : "{$d} days";
        }
        if ($minutes >= 60 && $minutes % 60 === 0) {
            $h = intdiv($minutes, 60);

            return $h === 1 ? '1 hour' : "{$h} hours";
        }

        return "{$minutes} minutes";
    }
}
