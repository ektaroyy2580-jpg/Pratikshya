<?php

return [
    // Invite links expire after this many days (the owner can rotate or revoke at any time).
    'invite_ttl_days' => (int) env('BILLS_INVITE_TTL_DAYS', 7),
    'max_participants' => (int) env('BILLS_MAX_PARTICIPANTS', 30),
    'max_items' => 80,
    // Nepal defaults (editable per bill).
    'default_service_charge_percent' => 10,
    'default_vat_percent' => 13,
    // Nepal convention: VAT is charged on (subtotal − discount + service charge). Per-bill toggle.
    'default_vat_on_service_charge' => true,
];
