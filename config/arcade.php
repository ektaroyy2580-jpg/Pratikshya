<?php

/* Arcade (solo games) settings. WaitPoints never affect queue position. */
return [
    // Max WaitPoints a user can earn from arcade games per day (on top of RewardService's global cap).
    'daily_points_cap' => (int) env('ARCADE_DAILY_POINTS_CAP', 120),

    // Sessions older than this can no longer be finished (prevents replaying stale puzzles).
    'session_max_minutes' => (int) env('ARCADE_SESSION_MAX_MINUTES', 120),

    // Ask the AI provider (if enabled) for extra quiz-style questions; always validated, bank fallback.
    'ai_variations' => (bool) env('ARCADE_AI_VARIATIONS', true),
];
