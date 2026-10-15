@extends('layouts.app')
@section('title', 'Getting there — Smart Explore & Rides · प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/modules/rides.css') }}?v={{ filemtime(public_path('css/modules/rides.css')) }}">
<style> body > footer.footer-ww { margin-top: 0 !important; } </style>
@endpush

@section('content')
@php
    $last = $recent->first();
    $boot = [
        'quoteUrl' => route('rides.quote'),
        'storeUrl' => route('rides.store'),
        'destination' => $destination,
        'pickup' => $last ? ['lat' => $last->pickup_lat, 'lng' => $last->pickup_lng, 'label' => $last->pickup_label ?: 'Last pickup'] : null,
        'landmarks' => $landmarks,
        'locations' => $locations,
        'places' => $places,
        'mode' => $initialMode,
        'routing' => $routingConfigured,
        'hasTicket' => (bool) $ticket,
        'ticketLocationId' => $ticket?->service?->location_id,
    ];
@endphp

<div class="rd-shell">
    <aside class="rd-panel" aria-label="Trip planner">
        <div class="rd-head mb-3 fade-up">
            <a href="{{ $ticket ? route('ticket', $ticket) : route('find') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>{{ $ticket ? 'Back to my token' : 'Find a service' }}</a>
            <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                <span class="pk-eyebrow"><i class="bi bi-compass"></i> Smart Explore &amp; Rides</span>
            </div>
            <h1>Getting there</h1>
            <p class="small pk-muted mb-0">Compare walking, bike, car and bus times — and estimated ride fares in NPR.</p>
        </div>

        {{-- Queue-aware banner --}}
        @if($ticket && $ticketState)
            <div class="rd-leave mb-3 fade-up" id="leaveCard" style="animation-delay:.05s" aria-live="polite">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="small fw-semibold"><i class="bi bi-ticket-perforated me-1"></i>Token {{ $ticket->token() }} · {{ $ticket->service->name }}</div>
                        <div class="big mt-1" id="leaveBig">
                            @if($ticket->status === 'waiting')
                                Your turn in ~{{ $ticketState['wait_low'] }}–{{ $ticketState['wait_high'] }} min
                            @else
                                You've been called — head to counter {{ $ticketState['counter'] }}
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-white text-dark">Leave by</span>
                </div>
                <div class="small mt-1" id="leaveSub">Choose a pickup to see when to leave. We keep a {{ config('rides.leave_buffer_minutes') }}-minute buffer.</div>
            </div>
        @endif

        {{-- Pickup & destination --}}
        <div class="pk-card rd-trip mb-3 fade-up" style="animation-delay:.08s">
            <span class="rd-connector" aria-hidden="true"></span>
            <div class="rd-field" id="pickField">
                <span class="mk pick" aria-hidden="true"></span>
                <label for="pickInput" class="visually-hidden">Pickup</label>
                <input id="pickInput" type="text" autocomplete="off" placeholder="Pickup — search, tap the map or use GPS" role="combobox" aria-expanded="false" aria-controls="pickSuggest" maxlength="120">
                <button type="button" class="btn btn-ghost btn-sm px-2 py-2" id="gpsBtn" title="Use my location" aria-label="Use my location"><i class="bi bi-crosshair"></i></button>
                <div class="rd-suggest d-none" id="pickSuggest" role="listbox"></div>
            </div>
            <div class="rd-field" id="destField">
                <span class="mk dest" aria-hidden="true"></span>
                <label for="destInput" class="visually-hidden">Destination</label>
                <input id="destInput" type="text" autocomplete="off" placeholder="Where to? Office, place or landmark" role="combobox" aria-expanded="false" aria-controls="destSuggest" maxlength="120">
                <div class="rd-suggest d-none" id="destSuggest" role="listbox"></div>
            </div>
            <div class="small pk-muted mt-2 d-none" id="gpsNote" role="status"></div>
            <div class="rd-chips mt-2" aria-label="Quick pickup landmarks">
                @foreach($landmarks as $lm)
                    <button type="button" class="rd-chip" data-landmark="{{ $loop->index }}"><i class="bi bi-geo me-1"></i>{{ $lm['name'] }}</button>
                @endforeach
            </div>
        </div>

        {{-- Results --}}
        <div id="results" aria-live="polite">
            <div class="rd-empty" id="emptyState">
                <span class="pk-icon violet mb-2"><i class="bi bi-signpost-split"></i></span>
                <div class="fw-bold text-dark mb-1">Where are you starting from?</div>
                <div>Tap “Use my location”, pick a landmark, or click the map to drop your pickup pin.</div>
            </div>
            <div class="d-none" id="loadingState" aria-hidden="true">
                <div class="skeleton mb-2" style="height:62px;border-radius:14px"></div>
                <div class="skeleton mb-2" style="height:74px;border-radius:16px"></div>
                <div class="skeleton" style="height:74px;border-radius:16px"></div>
            </div>
            <div class="alert alert-danger d-none" id="errorState" role="alert"></div>

            <div class="d-none" id="planState">
                <div class="rd-modes mb-2" role="tablist" aria-label="Travel mode" id="modeTabs"></div>
                <div class="d-flex flex-wrap align-items-center gap-2 small pk-muted mb-3" id="tripMeta"></div>

                <div id="modeBody"></div>

                <div class="rd-info mt-3">
                    <i class="bi bi-shield-check"></i>
                    <div>
                        <b>Fares are our estimates</b>, not live prices. inDrive, Yango and Pathao don't offer a public booking API we can use, so the real fare and booking are always confirmed in their app.
                    </div>
                </div>
            </div>
        </div>

        {{-- Carpool opt-in (off by default) --}}
        <div class="pk-card p-3 mt-3 fade-up" style="animation-delay:.12s">
            <div class="rd-switch form-switch p-0">
                <input class="form-check-input" type="checkbox" role="switch" id="carpoolToggle" aria-describedby="carpoolHelp">
                <div class="flex-grow-1">
                    <label for="carpoolToggle" class="fw-bold d-block">Carpool with others going to the same office</label>
                    <div class="small pk-muted" id="carpoolHelp">Optional and off by default. We never assume people nearby want to share.</div>
                </div>
            </div>
            <div class="rd-consent mt-2 d-none" id="carpoolConsent">
                <div class="mb-2"><b>What's shared if you turn this on:</b> only your <b>rough pickup area (≈1 km)</b> and <b>destination</b>, used to count other people who also opted in. Your name, phone and exact location are never shown, and you'll only ever see anonymous counts.</div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="carpoolAck">
                    <label class="form-check-label fw-semibold" for="carpoolAck">I agree to share that for the trip I save next.</label>
                </div>
            </div>
        </div>

        @if($recent->isNotEmpty())
            <div class="pk-card p-3 mt-3 rd-recent">
                <h2 class="rd-section-t">Recent trips <span class="fw-semibold text-lowercase" style="letter-spacing:0">only visible to you</span></h2>
                @foreach($recent as $r)
                    <a href="{{ route('rides.show', $r) }}">
                        <span class="rd-badge" style="--acc: {{ $r->providerConfig()['color'] ?? '#2f5bea' }};width:34px;height:34px;font-size:.75rem;border-radius:10px">{{ $r->providerConfig()['initial'] ?? '?' }}</span>
                        <span class="flex-grow-1" style="min-width:0">
                            <span class="nm fw-bold d-block text-truncate">{{ $r->destination_label }}</span>
                            <span class="small pk-muted">{{ $r->providerName() }} {{ $r->vehicleLabel() }} · {{ $r->created_at->diffForHumans() }}</span>
                        </span>
                        @include('rides.partials.status-chip', ['ride' => $r])
                    </a>
                @endforeach
            </div>
        @endif
    </aside>

    <section class="rd-mapwrap" aria-label="Map">
        <div id="rdMap" role="application" aria-label="Map — click to set your pickup"></div>
        <div class="rd-map-note" id="mapNote"><i class="bi bi-hand-index me-1"></i>Click the map to set pickup · drag the green pin to adjust</div>
    </section>
</div>

{{-- Confirmation bottom sheet --}}
<div class="rd-backdrop" id="sheetBackdrop"></div>
<div class="rd-sheet" id="sheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" aria-hidden="true">
    <div class="rd-grab" aria-hidden="true"></div>
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="rd-badge" id="sheetBadge">?</span>
        <div class="flex-grow-1" style="min-width:0">
            <h2 class="h5 mb-0" id="sheetTitle">Ride</h2>
            <div class="small pk-muted" id="sheetSub"></div>
        </div>
        <button type="button" class="btn-close" aria-label="Close" id="sheetClose"></button>
    </div>
    <div class="rd-route mb-3">
        <span class="mk" style="background:var(--pk-success)"></span><span id="sheetFrom" class="text-truncate"></span>
        <span class="mk" style="background:var(--pk-violet);border-radius:3px"></span><span id="sheetTo" class="text-truncate"></span>
    </div>
    <div class="d-flex align-items-end justify-content-between gap-2 p-3 rounded-4 mb-3" style="background:var(--pk-bg)">
        <div>
            <div class="small pk-muted fw-semibold">Estimated fare <span class="rd-est ms-1"><i class="bi bi-calculator"></i>Estimate</span></div>
            <div class="h4 mb-0 mt-1" id="sheetFare">—</div>
        </div>
        <div class="text-end small pk-muted fw-semibold" id="sheetTime"></div>
    </div>
    <p class="small pk-muted mb-3" id="sheetNote"></p>

    <form method="POST" target="_blank" id="handoffForm" class="mb-2">
        @csrf
        <button type="submit" class="btn btn-pk rd-option" id="handoffBtn">
            <i class="bi bi-box-arrow-up-right fs-5"></i>
            <span class="flex-grow-1"><span class="t d-block" id="handoffTitle">Open provider</span><span class="d" id="handoffText">Fare and booking are confirmed in the provider app</span></span>
        </button>
    </form>
    <form method="POST" id="demoForm">
        @csrf
        <button type="submit" class="btn btn-ghost rd-option">
            <i class="bi bi-play-circle fs-5" style="color:var(--pk-warn)"></i>
            <span class="flex-grow-1"><span class="t d-block">Try the demo booking</span><span class="d">Simulated status only — no real driver or vehicle</span></span>
            <span class="rd-demo-badge d-none d-sm-inline-flex">DEMO</span>
        </button>
    </form>
    <div class="small pk-muted mt-3 d-flex gap-2"><i class="bi bi-info-circle"></i><span>We save this trip so you can see it later. Nothing is booked until you confirm inside the provider's app.</span></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>window.RIDES = @json($boot);</script>
<script src="{{ asset('js/modules/rides-plan.js') }}?v={{ filemtime(public_path('js/modules/rides-plan.js')) }}"></script>
@endpush
