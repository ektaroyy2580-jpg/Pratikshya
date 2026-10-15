<?php

/*
|--------------------------------------------------------------------------
| Smart Explore & Ride Booking (module "rides")
|--------------------------------------------------------------------------
| HONESTY NOTE: inDrive, Yango and Pathao do not offer public booking or fare APIs
| that this app is authorised to use. Every fare below is OUR OWN ESTIMATE MODEL in NPR,
| not a provider price. Real fares/bookings happen only in the provider's official app.
| The "api" blocks exist so an authorised partner integration can be added later; they are
| all null by default and nothing in this module calls a provider API today.
*/

$fare = fn (float $base, float $perKm, float $perMin, float $minimum, array $pickupEta = [3, 9], ?string $surge = null) => [
    'base' => $base,          // NPR flag-down (estimate)
    'per_km' => $perKm,       // NPR per road km (estimate)
    'per_min' => $perMin,     // NPR per minute in traffic (estimate)
    'minimum' => $minimum,    // NPR minimum fare (estimate)
    'pickup_eta' => $pickupEta, // typical minutes until pickup — a hint, not live data
    'surge_note' => $surge ?? 'Peak hours, rain and festivals can raise real fares above this range.',
    'source' => 'estimate',   // never "live": we have no provider price feed
];

return [

    // Optional OSRM-compatible routing server, e.g. http://127.0.0.1:5000 (self-hosted).
    // When null we draw a straight dashed line and estimate road distance with road_factor.
    'routing_url' => env('RIDES_ROUTING_URL'),
    'routing_timeout' => 3,

    // Haversine distance × road_factor ≈ road distance in Kathmandu valley.
    'road_factor' => 1.3,

    // Travel modes. Speeds are average door-to-door km/h; all times are approximate.
    'modes' => [
        'walking' => ['label' => 'Walk', 'icon' => 'bi-person-walking', 'speed_kmh' => 4.5, 'wait_minutes' => 0],
        'bike' => ['label' => 'Bike', 'icon' => 'bi-bicycle', 'speed_kmh' => 22, 'wait_minutes' => 0],
        'car' => ['label' => 'Car / Taxi', 'icon' => 'bi-car-front', 'speed_kmh' => 18, 'wait_minutes' => 0], // Kathmandu traffic
        'transit' => ['label' => 'Public transport', 'icon' => 'bi-bus-front', 'speed_kmh' => 12, 'wait_minutes' => 10],
    ],

    // Fare range around the model price: low = price × low, high = price × high (rounded to NPR 5).
    'range' => ['low' => 0.9, 'high' => 1.2],

    // Clock times (e.g. "Leave by 2:45 PM") are shown in this zone; the app itself runs in UTC.
    'display_timezone' => env('RIDES_TIMEZONE', 'Asia/Kathmandu'),

    // Extra minutes kept in hand when computing "Leave by" for queue tickets.
    'leave_buffer_minutes' => 10,

    // Beyond this straight-line distance we recommend a ride/bus instead of walking.
    'far_threshold_km' => 2.0,

    // Planner input bounds (Nepal service area) and limits.
    'bounds' => ['lat_min' => 26.3, 'lat_max' => 30.5, 'lng_min' => 80.0, 'lng_max' => 88.3],
    'max_distance_km' => 80,
    'min_distance_km' => 0.05,

    // Demonstration booking: status advances server-side from demo_started_at (seconds).
    'demo' => [
        'timeline' => [
            'demo_requested' => 0,
            'demo_driver_assigned' => 10,
            'demo_arriving' => 25,
            'demo_on_trip' => 55,
            'demo_completed' => 140,
        ],
        'badge' => 'DEMO — no real driver/vehicle',
    ],

    // Opt-in carpool interest. Only coarse areas are compared; only anonymous counts are shown.
    'carpool' => [
        'window_hours' => 3,
        'pickup_radius_km' => 3.0,
        'destination_radius_km' => 0.5,
        'coarse_decimals' => 2, // ≈1 km grid for the stored/compared pickup area
    ],

    // Preset pickup landmarks (approximate public coordinates).
    'landmarks' => [
        ['name' => 'Ratna Park', 'lat' => 27.7063, 'lng' => 85.3151],
        ['name' => 'Koteshwor', 'lat' => 27.6788, 'lng' => 85.3496],
        ['name' => 'Kalanki', 'lat' => 27.6933, 'lng' => 85.2817],
        ['name' => 'Chabahil', 'lat' => 27.7173, 'lng' => 85.3466],
        ['name' => 'Lagankhel', 'lat' => 27.6667, 'lng' => 85.3226],
        ['name' => 'Thamel', 'lat' => 27.7154, 'lng' => 85.3123],
        ['name' => 'Balaju', 'lat' => 27.7347, 'lng' => 85.3044],
        ['name' => 'New Baneshwor', 'lat' => 27.6889, 'lng' => 85.3420],
    ],

    'providers' => [
        'indrive' => [
            'enabled' => env('RIDES_INDRIVE_ENABLED', true),
            'name' => 'inDrive',
            'initial' => 'iD',
            'color' => '#1f9d55',
            'tagline' => 'You offer a fare; drivers accept or counter in the app.',
            'website' => 'https://indrive.com/',
            // deep_link must come from inDrive's official partner documentation. We do not invent URI schemes.
            'deep_link' => null,
            'vehicles' => [
                'bike' => ['label' => 'Moto', 'mode' => 'bike', 'icon' => 'bi-scooter', 'seats' => 1] + $fare(35, 20, 0.75, 70, [3, 8], 'inDrive fares are negotiated with the driver; the final price is agreed in the app.'),
                'car' => ['label' => 'Car', 'mode' => 'car', 'icon' => 'bi-car-front', 'seats' => 4] + $fare(90, 44, 2, 200, [4, 10], 'inDrive fares are negotiated with the driver; the final price is agreed in the app.'),
            ],
            'api' => ['base_url' => env('RIDES_INDRIVE_API_URL'), 'client_id' => env('RIDES_INDRIVE_CLIENT_ID'), 'client_secret' => env('RIDES_INDRIVE_CLIENT_SECRET')],
        ],
        'yango' => [
            'enabled' => env('RIDES_YANGO_ENABLED', true),
            'name' => 'Yango',
            'initial' => 'Y',
            'color' => '#e0314b',
            'tagline' => 'Tariff-based fares shown in the app before you order.',
            'website' => 'https://yango.com/',
            // deep_link must come from Yango's official partner documentation. We do not invent URI schemes.
            'deep_link' => null,
            'vehicles' => [
                'bike' => ['label' => 'Bike', 'mode' => 'bike', 'icon' => 'bi-scooter', 'seats' => 1] + $fare(40, 21, 1, 70, [3, 8]),
                'car' => ['label' => 'Economy', 'mode' => 'car', 'icon' => 'bi-car-front', 'seats' => 4] + $fare(95, 45, 2, 210, [4, 10]),
                'comfort' => ['label' => 'Comfort', 'mode' => 'car', 'icon' => 'bi-car-front-fill', 'seats' => 4] + $fare(140, 60, 3, 300, [6, 14]),
            ],
            'api' => ['base_url' => env('RIDES_YANGO_API_URL'), 'client_id' => env('RIDES_YANGO_CLIENT_ID'), 'client_secret' => env('RIDES_YANGO_CLIENT_SECRET')],
        ],
        'pathao' => [
            'enabled' => env('RIDES_PATHAO_ENABLED', true),
            'name' => 'Pathao',
            'initial' => 'P',
            'color' => '#c2410c',
            'tagline' => 'Bike and car rides with the fare shown in the app.',
            'website' => 'https://pathao.com/np/',
            // deep_link must come from Pathao's official partner documentation. We do not invent URI schemes.
            'deep_link' => null,
            'vehicles' => [
                'bike' => ['label' => 'Bike', 'mode' => 'bike', 'icon' => 'bi-scooter', 'seats' => 1] + $fare(40, 22, 1, 75, [2, 7]),
                'car' => ['label' => 'Car', 'mode' => 'car', 'icon' => 'bi-car-front', 'seats' => 4] + $fare(100, 48, 2.5, 220, [5, 12]),
            ],
            'api' => ['base_url' => env('RIDES_PATHAO_API_URL'), 'client_id' => env('RIDES_PATHAO_CLIENT_ID'), 'client_secret' => env('RIDES_PATHAO_CLIENT_SECRET')],
        ],
    ],
];
