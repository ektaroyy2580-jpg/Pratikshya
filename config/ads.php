<?php

/*
|--------------------------------------------------------------------------
| Advertising (module: ads)
|--------------------------------------------------------------------------
| Sponsored cards, rewarded ads and the advertiser portal.
|
| Principles enforced in code (see app/Services/Ads/*):
|  - Ads NEVER affect queue order, document checks, medical priority or staff decisions.
|  - Targeting is contextual only (placement + page location/category). No behavioural profiling.
|  - Every ad is labelled "Sponsored"; healthcare ads also carry a disclosure.
|  - Impressions/clicks are only ever recorded from real page views; nothing is fabricated.
|  - Rewards from ads are granted only after server-side verification.
*/

return [

    // Master switch. When false every slot renders nothing and rewarded ads are unavailable.
    'enabled' => env('ADS_ENABLED', true),

    'placements' => [
        'explore' => 'Explore page',
        'nearby_places' => 'Nearby places',
        'game_hub' => 'Game hub',
        'post_game_reward' => 'Post-game reward screen',
        'queue_page' => 'Queue page (waiting only)',
    ],

    'categories' => [
        'cafe' => 'Café', 'restaurant' => 'Restaurant', 'pharmacy' => 'Pharmacy', 'hospital' => 'Hospital',
        'bookstore' => 'Bookstore', 'shopping' => 'Shopping centre', 'transport' => 'Transport', 'other' => 'Other',
    ],

    // Categories whose campaigns must carry a healthcare disclosure.
    'healthcare_categories' => ['pharmacy', 'hospital'],

    // Generated gradient creatives (used when no image is uploaded).
    'themes' => [
        'blue' => ['#2f5bea', '#7046e8'],
        'teal' => ['#0f766e', '#2f5bea'],
        'amber' => ['#b45309', '#e11d48'],
        'rose' => ['#be123c', '#7046e8'],
        'forest' => ['#166534', '#0f766e'],
        'slate' => ['#1e293b', '#475569'],
    ],

    'delivery' => [
        // An impression from the same viewer for the same campaign + placement counts once per window.
        'dedupe_minutes' => (int) env('ADS_DEDUPE_MINUTES', 30),
        // Max counted impressions of one campaign per viewer per day; beyond this it is not served.
        'frequency_cap_per_day' => (int) env('ADS_FREQUENCY_CAP', 6),
        // Signed slot tokens are accepted by the impression beacon for this long.
        'token_ttl_minutes' => 60,
    ],

    'creative' => [
        'max_kb' => 1024,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'disk' => 'public',
        'directory' => 'ads',
    ],

    'rewarded' => [
        'enabled' => env('ADS_REWARDED_ENABLED', true),
        // Server-side minimum time between "start" and "complete".
        'required_seconds' => (int) env('ADS_REWARDED_SECONDS', 15),
        // A started claim must be completed within this window, otherwise it expires.
        'expires_minutes' => 10,
        // Completed rewarded views per user per day (all campaigns).
        'daily_limit' => (int) env('ADS_REWARDED_DAILY_LIMIT', 3),
        'rewards' => [
            'extra_round' => ['label' => 'Extra game round', 'icon' => 'bi-arrow-repeat'],
            'cosmetic' => ['label' => 'Sponsor supporter badge', 'icon' => 'bi-patch-check'],
            'points' => ['label' => 'WaitPoints', 'icon' => 'bi-stars', 'amount' => 10],
        ],
    ],

    /*
    | Ad network provider. 'demo' = local DEMO sponsored videos (no external network, no revenue).
    |
    | To integrate a real rewarded-ad network you need, from the network:
    |   - a server-to-server (S2S) reward callback that POSTs to route('ads.provider.callback')
    |   - a shared secret used to sign the raw request body (HMAC-SHA256, hex) in a header
    | Then set ADS_PROVIDER=network, ADS_NETWORK_CALLBACK_SECRET and ADS_NETWORK_SIGNATURE_HEADER.
    | Until both are configured, every callback is rejected (HTTP 403) — see ProviderCallbackVerifier.
    */
    'provider' => env('ADS_PROVIDER', 'demo'),

    'providers' => [
        'network' => [
            'name' => env('ADS_NETWORK_NAME', 'Unconfigured network'),
            'callback_secret' => env('ADS_NETWORK_CALLBACK_SECRET'),
            'signature_header' => env('ADS_NETWORK_SIGNATURE_HEADER', 'X-Ad-Signature'),
            // Reject callbacks whose timestamp is older than this (replay protection).
            'max_age_seconds' => 300,
        ],
    ],
];
