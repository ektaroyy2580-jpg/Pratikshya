<?php

/*
| English speaking practice (module: english).
| Audio is never stored or uploaded: speech recognition happens in the browser —
| on-device with Vosk/WebAssembly (public/vendor/vosk, offline) or the browser's
| Web Speech API — and only the transcript text is sent. When AI_PROVIDER (config/ai.php)
| is enabled, transcripts (with emails/phone numbers redacted) are sent to that provider
| for feedback; otherwise a local rule-based engine is used.
*/

return [
    'max_turn_chars' => (int) env('ENGLISH_MAX_TURN_CHARS', 1200),
    // Prompts per session length (minutes => prompts); capped by the scenario's prompt count.
    'prompts_per_duration' => [3 => 3, 5 => 5, 10 => 8],
    // WaitPoints for completing a session with at least `reward_min_turns` answers (idempotent per session).
    'reward_points' => (int) env('ENGLISH_REWARD_POINTS', 15),
    'reward_min_turns' => 2,
    // Offline speech model archive in public/vendor/vosk/models/<name>.tar.gz (loaded by the browser only).
    'speech_model' => env('ENGLISH_SPEECH_MODEL', 'vosk-model-small-en-us-0.15'),
];
