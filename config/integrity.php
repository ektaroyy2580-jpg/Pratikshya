<?php

/*
| Dalal AI (queue integrity) + Voluntary Queue Exchange.
|
| Privacy: signals keep only a salted HMAC of the IP (keyed with APP_KEY, truncated)
| and a coarse browser family. They exist for human review of possible queue abuse,
| never as proof, and are deleted after `retention_days` by `php artisan integrity:prune`
| (schedule it daily). Alerts keep counts and time spans, never raw IPs.
*/

return [
    'retention_days' => (int) env('INTEGRITY_RETENTION_DAYS', 30),

    // Let Dalal AI ask the configured AiClient (config/ai.php) to word explanations.
    // Only the rule name, counts and time spans are sent. Template text is used otherwise.
    'ai_explanations' => (bool) env('INTEGRITY_AI_EXPLANATIONS', true),

    // Rule thresholds (counts within a window, in minutes).
    'rules' => [
        'duplicate_tokens' => ['count' => 3, 'window' => 10],
        'churn' => ['count' => 3, 'window' => 1440],
        'multi_tokens' => ['count' => 3],
        'account_burst' => ['count' => 3, 'window' => 30, 'account_age_hours' => 24],
        'exchanges' => ['count' => 3, 'window' => 1440],
        'staff_absent_cancel' => ['count' => 5, 'window' => 15],
        'staff_call_next' => ['window' => 15, 'z' => 3.0, 'min_count' => 8, 'baseline_days' => 7, 'min_buckets' => 4],
        'rejected_transitions' => ['count' => 3, 'window' => 30],
        'rule_bypass' => ['count' => 3, 'window' => 60],
    ],

    'exchange' => [
        'expiry_minutes' => (int) env('EXCHANGE_EXPIRY_MINUTES', 10),          // recipient must answer within this
        'approval_minutes' => (int) env('EXCHANGE_APPROVAL_MINUTES', 30),      // staff approval window after acceptance
        'daily_limit' => (int) env('EXCHANGE_DAILY_LIMIT', 5),                 // requests one user may send per day
    ],
];
