<?php

/*
|--------------------------------------------------------------------------
| Explore module — nearby places, waiting-time-aware recommendations,
| hospital assistance.
|--------------------------------------------------------------------------
| Example .env keys (optional, all have safe defaults):
|   EXPLORE_ROUTING_PROVIDER=none          # none | osrm
|   EXPLORE_OSRM_URL=http://127.0.0.1:5000 # self-hosted OSRM (foot profile)
|   EXPLORE_SAFETY_BUFFER=8
*/

return [
    // 'none' = straight-line estimates only (no fake street routes are ever drawn).
    'routing_provider' => env('EXPLORE_ROUTING_PROVIDER', 'none'),
    'osrm_url' => env('EXPLORE_OSRM_URL'),
    'osrm_timeout' => (float) env('EXPLORE_OSRM_TIMEOUT', 3),

    // Straight-line → street distance multiplier and walking speed.
    'road_factor' => 1.3,
    'walk_kmh' => 4.5,

    // Minutes always reserved before your estimated turn.
    'safety_buffer_minutes' => (int) env('EXPLORE_SAFETY_BUFFER', 8),

    // Never suggest places further than this one-way walk ("don't send people to distant popular places").
    'max_walk_minutes' => 15,

    'max_recommendations' => 6,

    // Dismissed recommendations stay hidden for this many days.
    'dismiss_days' => 7,

    // Nepal emergency numbers — shown with a "verify locally" label.
    'emergency_numbers' => [
        ['label' => 'Ambulance', 'number' => '102', 'icon' => 'bi-truck-front-fill'],
        ['label' => 'Police', 'number' => '100', 'icon' => 'bi-shield-fill'],
        ['label' => 'Fire', 'number' => '101', 'icon' => 'bi-fire'],
    ],
];
