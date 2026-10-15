@extends('layouts.app')
@section('title', ($ride->is_demo ? 'Demo ride' : 'Your trip').' — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/modules/rides.css') }}?v={{ filemtime(public_path('css/modules/rides.css')) }}">
@endpush

@section('content')
@php
    $pc = $ride->providerConfig();
    $color = $pc['color'] ?? '#2f5bea';
    $name = $ride->providerName();
    $steps = [
        'demo_requested' => ['Ride requested', 'bi-send'],
        'demo_driver_assigned' => ['Driver assigned', 'bi-person-check'],
        'demo_arriving' => ['Driver arriving', 'bi-geo-alt'],
        'demo_on_trip' => ['On trip', 'bi-signpost-2'],
        'demo_completed' => ['Arrived', 'bi-flag'],
    ];
    $boot = [
        'stateUrl' => route('rides.state', $ride),
        'pickup' => [$ride->pickup_lat, $ride->pickup_lng],
        'dest' => [$ride->destination_lat, $ride->destination_lng],
        'state' => $state,
        'steps' => array_keys($steps),
        'poll' => $ride->is_demo && in_array($ride->status, \App\Models\RideRequest::DEMO_ACTIVE, true),
    ];
@endphp

<header class="pk-page-head py-4">
    <div class="container">
        <a href="{{ route('rides.plan') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Plan another trip</a>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mb-1">
            @if($ride->is_demo)<span class="rd-demo-badge"><i class="bi bi-cone-striped"></i>{{ config('rides.demo.badge') }}</span>@endif
            <span id="statusChip">@include('rides.partials.status-chip', ['ride' => $ride])</span>
            <span class="rd-est"><i class="bi bi-calculator"></i>Fare estimate</span>
        </div>
        <h1 class="h2 mb-1">{{ $ride->is_demo ? 'Demo ride' : ($ride->status === 'handed_off' ? 'Continue in '.$name : 'Your planned trip') }}</h1>
        <p class="pk-muted mb-0 fw-semibold">{{ $ride->pickup_label }} <i class="bi bi-arrow-right mx-1"></i> {{ $ride->destination_label }}</p>
    </div>
</header>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-7 order-lg-2">
            <div class="rd-status-map fade-up"><div id="rdMap" style="position:absolute;inset:0"></div>
                <div class="rd-map-note" style="bottom:12px"><i class="bi bi-info-circle me-1"></i>{{ $ride->is_demo ? 'Simulated vehicle on a direct line — not a real vehicle' : 'Map for orientation only — we do not track provider rides' }}</div>
            </div>

            @if($leaveBy)
                <div class="rd-leave mt-3 {{ $leaveBy['leave_now'] ? 'urgent' : '' }}">
                    <div class="small fw-semibold"><i class="bi bi-ticket-perforated me-1"></i>Token {{ $leaveBy['token'] }} · Leave by</div>
                    <div class="big">{{ $leaveBy['leave_now'] ? 'Leave now' : 'Leave by '.$leaveBy['leave_at'] }}</div>
                    <div class="small">~{{ $leaveBy['travel_minutes'] }} min trip + {{ $leaveBy['buffer_minutes'] }} min buffer · your turn in ~{{ $leaveBy['turn_low'] }}–{{ $leaveBy['turn_high'] }} min (estimate)</div>
                </div>
            @endif
        </div>

        <div class="col-lg-5 order-lg-1 d-grid gap-3 align-content-start">
            @if($ride->is_demo)
                <div class="rd-demo-strip"><i class="bi bi-exclamation-triangle-fill"></i><span>This is a demonstration. No request was sent to {{ $name }} and no driver or vehicle is coming.</span></div>

                <div class="pk-card p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">Simulated status</h2>
                        <span class="small pk-muted fw-semibold" id="etaText" aria-live="polite"></span>
                    </div>
                    @if($ride->status === 'cancelled')
                        <div class="rd-empty"><i class="bi bi-x-circle me-1"></i>Demo ride cancelled.</div>
                    @else
                        <ol class="rd-steps" id="steps">
                            @foreach($steps as $key => [$label, $icon])
                                <li data-step="{{ $key }}"><span class="ic"><i class="bi {{ $icon }}"></i></span><span>{{ $label }}<span class="when d-block">{{ $loop->first ? 'Simulated request' : 'Simulated' }}</span></span></li>
                            @endforeach
                        </ol>
                        <div class="progress mt-3" style="height:6px" role="progressbar" aria-label="Trip progress"><div class="progress-bar" id="tripBar" style="width:0%"></div></div>
                        <div class="d-flex align-items-center gap-3 mt-3 p-3 rounded-4 {{ $state['driver'] ? '' : 'd-none' }}" id="driverCard" style="background:var(--pk-bg)">
                            <span class="pk-icon"><i class="bi bi-person-badge"></i></span>
                            <div class="flex-grow-1"><div class="fw-bold" id="driverName">{{ $state['driver']['name'] ?? '' }}</div><div class="small pk-muted" id="driverVeh">{{ ($state['driver']['vehicle'] ?? '').' · '.($state['driver']['plate'] ?? '') }}</div></div>
                            <span class="rd-demo-badge">DEMO</span>
                        </div>
                    @endif
                </div>
            @else
                <div class="pk-card p-3 p-md-4">
                    <div class="d-flex gap-3 align-items-center mb-3">
                        <span class="rd-badge" style="--acc: {{ $color }}">{{ $pc['initial'] ?? '?' }}</span>
                        <div><div class="fw-bold">{{ $name }} · {{ $ride->vehicleLabel() }}</div><div class="small pk-muted">{{ $pc['tagline'] ?? '' }}</div></div>
                    </div>
                    @if($ride->status === 'cancelled')
                        <div class="rd-empty"><i class="bi bi-x-circle me-1"></i>Removed from your plans. If you booked in the {{ $name }} app, cancel it there too.</div>
                    @else
                        <div class="rd-info mb-3"><i class="bi bi-info-circle"></i><div><b>Fare and booking are confirmed in the {{ $name }} app.</b> We can't see whether a ride was booked there, so we don't show a booking status.</div></div>
                        @if($handoffUrl)
                            <form method="POST" action="{{ route('rides.handoff', $ride) }}" target="_blank" class="mb-2">@csrf
                                <button class="btn btn-pk w-100"><i class="bi bi-box-arrow-up-right me-1"></i>{{ $ride->status === 'handed_off' ? 'Open '.$name.' again' : 'Open '.$name }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('rides.demo', $ride) }}">@csrf
                            <button class="btn btn-ghost w-100"><i class="bi bi-play-circle me-1"></i>Try the demo booking <span class="rd-demo-badge ms-1">DEMO</span></button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="pk-card p-3 p-md-4">
                <div class="rd-kv"><span class="k">Estimated fare</span><span class="v">{{ $ride->fareRange() }} <span class="rd-est">Estimate</span></span></div>
                <div class="rd-kv"><span class="k">Based on</span><span class="v">{{ $name }} {{ $ride->vehicleLabel() }} model</span></div>
                <div class="rd-kv"><span class="k">Distance · time</span><span class="v">≈ {{ number_format($ride->distance_km, 1) }} km · ~{{ $ride->est_minutes }} min</span></div>
                @if($ride->external_reference)<div class="rd-kv"><span class="k">Demo reference</span><span class="v">{{ $ride->external_reference }}</span></div>@endif
                <div class="rd-kv"><span class="k">Saved</span><span class="v">{{ $ride->created_at->copy()->setTimezone(config('rides.display_timezone'))->format('M j, g:i A') }}</span></div>
                <p class="small pk-muted mt-2 mb-0">{{ $ride->vehicleConfig()['surge_note'] ?? '' }}</p>
                @if($ride->isCancellable())
                    <form method="POST" action="{{ route('rides.cancel', $ride) }}" class="mt-3" id="cancelForm" onsubmit="return confirm('{{ $ride->is_demo ? 'Cancel this demo ride?' : 'Remove this trip from your plans?' }}')">@csrf
                        <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-x-lg me-1"></i>{{ $ride->is_demo ? 'Cancel demo ride' : 'Remove from my plans' }}</button>
                    </form>
                @endif
            </div>

            {{-- Carpool: explicit opt-in per trip, anonymous counts only --}}
            @if($ride->status !== 'cancelled')
                <div class="pk-card p-3 p-md-4">
                    <h2 class="h6 fw-bold mb-1"><i class="bi bi-people me-1"></i>Carpool with others going to the same office</h2>
                    @if($ride->consent_share)
                        <p class="small pk-muted mb-2">On for this trip. Others only see anonymous counts — never your name, contact or exact pickup.</p>
                        <div class="row g-2 mb-3 text-center" id="carpoolCounts">
                            <div class="col-6"><div class="p-2 rounded-3" style="background:var(--pk-bg)"><div class="h4 mb-0" data-cp="same_destination">{{ $carpool['same_destination'] ?? 0 }}</div><div class="small pk-muted">opted-in, same destination</div></div></div>
                            <div class="col-6"><div class="p-2 rounded-3" style="background:var(--pk-bg)"><div class="h4 mb-0" data-cp="nearby_pickup">{{ $carpool['nearby_pickup'] ?? 0 }}</div><div class="small pk-muted">of them start within ~{{ (int) config('rides.carpool.pickup_radius_km') }} km</div></div></div>
                        </div>
                        <p class="small pk-muted">Connecting riders isn't available in this build — counts only show whether sharing could work.</p>
                        <form method="POST" action="{{ route('rides.carpool', $ride) }}">@csrf<input type="hidden" name="consent_share" value="0">
                            <button class="btn btn-ghost btn-sm w-100">Turn off carpool matching</button>
                        </form>
                    @else
                        <p class="small pk-muted mb-2">Off. Turn it on to see how many other people — who also opted in — are heading to the same place. We never assume people nearby want to share.</p>
                        <form method="POST" action="{{ route('rides.carpool', $ride) }}">@csrf<input type="hidden" name="consent_share" value="1">
                            <div class="rd-consent mb-2">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="acknowledge" value="1" id="ack" required>
                                    <label class="form-check-label" for="ack">Share only my <b>rough pickup area (≈1 km)</b> and <b>destination</b> for matching. My identity is never shown.</label>
                                </div>
                            </div>
                            @error('acknowledge')<div class="small text-danger mb-2">{{ $message }}</div>@enderror
                            <button class="btn btn-ghost btn-sm w-100"><i class="bi bi-toggle-off me-1"></i>Turn on carpool matching</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>window.RIDE = @json($boot);</script>
<script src="{{ asset('js/modules/rides-status.js') }}?v={{ filemtime(public_path('js/modules/rides-status.js')) }}"></script>
@endpush
