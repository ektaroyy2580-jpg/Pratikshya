<?php

/*
 * Cafe preordering module.
 *
 * Payments: this build ships DEMO payment only. A real provider (e.g. eSewa or
 * Khalti) must be integrated with server-side verified callbacks before
 * `payment_provider` may be anything other than "demo"; until then the keys
 * below are documented placeholders and are NOT used to take money.
 * Card details are never collected or stored.
 */
return [
    'service_charge_percent' => (int) env('PREORDER_SERVICE_CHARGE_PERCENT', 10),
    'vat_percent' => (int) env('PREORDER_VAT_PERCENT', 13),

    // Timing check (minutes)
    'collect_minutes' => (int) env('PREORDER_COLLECT_MINUTES', 3),
    'safety_margin_minutes' => (int) env('PREORDER_SAFETY_MARGIN_MINUTES', 5),
    'walk_metres_per_minute' => (int) env('PREORDER_WALK_METRES_PER_MINUTE', 75),

    // Cart limits
    'max_qty_per_item' => 20,
    'max_items_per_order' => 40,

    // Payments — only "demo" is implemented.
    'payment_provider' => env('PREORDER_PAYMENT_PROVIDER', 'demo'),
    'esewa' => [
        'merchant_code' => env('ESEWA_MERCHANT_CODE'),
        'secret_key' => env('ESEWA_SECRET_KEY'),
        'callback_url' => env('ESEWA_CALLBACK_URL'),
    ],
    'khalti' => [
        'public_key' => env('KHALTI_PUBLIC_KEY'),
        'secret_key' => env('KHALTI_SECRET_KEY'),
        'callback_url' => env('KHALTI_CALLBACK_URL'),
    ],
];
