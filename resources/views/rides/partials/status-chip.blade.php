@php
    $map = [
        'quoted' => ['Planned', 'done'],
        'handed_off' => ['Opened app', 'serving'],
        'demo_requested' => ['Demo · requested', 'waiting'],
        'demo_driver_assigned' => ['Demo · driver', 'waiting'],
        'demo_arriving' => ['Demo · arriving', 'waiting'],
        'demo_on_trip' => ['Demo · on trip', 'called'],
        'demo_completed' => ['Demo · done', 'done'],
        'cancelled' => ['Cancelled', 'done'],
    ];
    [$label, $cls] = $map[$ride->status] ?? [ucfirst(str_replace('_', ' ', $ride->status)), 'done'];
@endphp
<span class="pk-status {{ $cls }}" data-status-chip>{{ $label }}</span>
