@extends('layouts.app')
@section('title', 'Explore near '.$location->name.' — प्रतीक्षा')

@php
    $mins = $remaining['minutes'];
    $manual = $remaining['source'] === 'manual';
    $minuteOptions = [15, 30, 45, 60, 90];
    $payload = [
        'location' => ['id' => $location->id, 'name' => $location->name, 'lat' => (float) $location->lat, 'lng' => (float) $location->lng],
        'places' => $places,
        'recs' => $recommendations,
        'remaining' => $mins,
        'status' => $remaining['status'],
        'manualMinutes' => $manual ? $mins : null,
        'ticketId' => $queuedHere ? $ticket->id : null,
        'buffer' => $buffer,
        'dismissed' => $dismissedIds,
        'provider' => $provider,
        'urls' => [
            'recs' => route('explore.recommendations'),
            'travel' => route('explore.travel', ['place' => '__ID__']),
            'dismiss' => route('explore.dismiss', ['place' => '__ID__']),
            'undismiss' => route('explore.undismiss', ['dismissal' => '__ID__']),
        ],
    ];
@endphp

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/modules/explore.css') }}?v={{ filemtime(public_path('css/modules/explore.css')) }}">
@endpush

@section('content')
<div class="ex-shell" id="exShell">
    {{-- ================= LIST PANEL ================= --}}
    <aside class="ex-panel" aria-label="Nearby places">
        <div class="ex-panel-inner">
            <span class="ex-grabber" aria-hidden="true"></span>

            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <span class="pk-eyebrow"><i class="bi bi-compass"></i>Explore</span>
                    <h1 class="ex-title">Near {{ $location->name }}</h1>
                    <div class="pk-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $location->area }}</div>
                </div>
                <a href="{{ route('explore.preferences') }}" class="btn btn-ghost btn-sm px-2 py-1" title="Explore preferences" aria-label="Explore preferences"><i class="bi bi-sliders"></i></a>
            </div>

            <form method="GET" action="{{ route('explore.index') }}" class="mt-3" id="locForm">
                <label for="locSelect" class="visually-hidden">Location</label>
                <select id="locSelect" name="location" class="form-select ex-loc-select" onchange="this.form.submit()">
                    @foreach($locations as $l)
                        <option value="{{ $l->id }}" @selected($l->id === $location->id)>{{ $l->name }} — {{ $l->area }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-ghost btn-sm mt-2">Go</button></noscript>
            </form>

            @if($isHospital)
                <a href="{{ route('explore.hospital', $location) }}" class="hx-banner mt-3 text-decoration-none">
                    <span class="ic"><i class="bi bi-heart-pulse-fill"></i></span>
                    <span class="flex-grow-1 small"><b>Hospital assistance</b><br>Emergency contacts, departments, lab, pharmacies.</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            @endif

            {{-- ---------- Time banner ---------- --}}
            <div class="mt-3">
                @if($queuedHere && $remaining['status'] === 'waiting')
                    <div class="ex-time fade-up" role="status">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="sub">You have approximately</span>
                            <a href="{{ route('ticket', $ticket) }}" class="tok text-white text-decoration-none"><i class="bi bi-ticket-perforated me-1"></i>{{ $ticket->token() }}</a>
                        </div>
                        <div class="big">{{ $mins }} minutes</div>
                        <div class="sub mt-1">Low end of your queue estimate · we keep {{ $buffer }} min spare before your turn</div>
                    </div>
                @elseif($queuedHere)
                    <div class="ex-time called fade-up" role="status">
                        <div class="fw-bold"><i class="bi bi-megaphone-fill me-1"></i>Your token {{ $ticket->token() }} is {{ $remaining['status'] === 'called' ? 'being called' : 'being served' }}.</div>
                        <div class="small mt-1">Please go to the counter — no suggestions right now.</div>
                    </div>
                @else
                    <div class="ex-time calm">
                        <div class="fw-bold mb-1">{{ $ticket ? 'Your queue is at another location' : 'How much time do you have?' }}</div>
                        <div class="small pk-muted mb-2">
                            @if($ticket)
                                You're queued at {{ $ticket->service->location->name }}. <a href="{{ route('explore.index', ['location' => $ticket->service->location_id]) }}">Explore there</a>, or pick a time to browse here.
                            @else
                                Pick a time to see places that fit, with a {{ $buffer }}-minute safety buffer.
                            @endif
                        </div>
                        <div class="ex-minutes" role="group" aria-label="Minutes available">
                            @foreach($minuteOptions as $m)
                                <a href="{{ route('explore.index', ['location' => $location->id, 'minutes' => $m]) }}" class="{{ $manual && $mins === $m ? 'on' : '' }}">{{ $m }} min</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ---------- Where am I ---------- --}}
            <div class="ex-origin mt-3">
                <button type="button" class="btn btn-ghost" id="btnLocate"><i class="bi bi-crosshair me-1"></i>Use my location</button>
                <button type="button" class="btn btn-ghost" id="btnPick"><i class="bi bi-pin-map me-1"></i>Set on map</button>
                <button type="button" class="btn btn-link btn-sm text-decoration-none d-none" id="btnResetOrigin">Reset</button>
            </div>
            <div class="ex-origin-note mt-2" id="originNote" aria-live="polite"><i class="bi bi-geo me-1"></i>Times are from {{ $location->name }} and back.</div>
            <div class="ex-consent mt-2 d-none" id="consentBox" role="dialog" aria-labelledby="consentTitle">
                <div class="fw-bold mb-1" id="consentTitle"><i class="bi bi-shield-lock me-1"></i>Share your location once?</div>
                <div class="mb-2">Your browser will ask for permission. We use it only to estimate walking time on this page — it is not saved to your account.</div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-pk btn-sm" id="consentYes">Allow once</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="consentPick">Pick on map instead</button>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none" id="consentNo">Cancel</button>
                </div>
            </div>

            {{-- ---------- Neutral recommendations ---------- --}}
            <div class="ex-sec">
                <h2><i class="bi bi-stars me-1 text-primary"></i>Fits your wait</h2>
                <span class="note">Not sponsored · ranked by distance &amp; rating</span>
            </div>
            <div id="recList" aria-live="polite">
                @if($recommendations['items'])
                    @foreach($recommendations['items'] as $item)
                        @include('explore.partials.rec-card', ['item' => $item, 'remaining' => $mins])
                    @endforeach
                @else
                    <div class="ex-empty">
                        <i class="bi {{ $recommendations['enabled'] ? 'bi-hourglass-split' : 'bi-toggle-off' }} d-block fs-4 mb-1"></i>
                        {{ $recommendations['message'] }}
                        @unless($recommendations['enabled'])
                            <div class="mt-2"><a href="{{ route('explore.preferences') }}" class="fw-bold">Turn suggestions on</a></div>
                        @endunless
                    </div>
                @endif
            </div>
            <div class="small pk-muted mt-2" id="recNote">{{ $recommendations['items'] ? $recommendations['message'] : '' }}</div>

            {{-- ---------- Sponsored (separate, labelled by the ads module) ---------- --}}
            @includeIf('ads.partials.slot', ['placement' => 'explore'])

            {{-- ---------- All places ---------- --}}
            <div class="ex-sec">
                <h2>All nearby places</h2>
                <span class="note" id="placeCount">{{ $places->count() }} places</span>
            </div>
            <div class="ex-chips" role="toolbar" aria-label="Filter by category" id="chips">
                <button type="button" class="ex-chip on" data-cat="all" aria-pressed="true"><i class="bi bi-grid"></i>All</button>
                @foreach($chips as $c)
                    <button type="button" class="ex-chip" data-cat="{{ $c['key'] }}" style="--c: {{ $c['color'] }}" aria-pressed="false"><i class="bi {{ $c['icon'] }}"></i>{{ $c['label'] }}</button>
                @endforeach
            </div>
            <div id="placeList" class="mt-2">
                @forelse($places as $p)
                    <div class="ex-place" data-place="{{ $p['id'] }}" data-cat="{{ $p['category'] }}" style="--c: {{ $p['color'] }}" tabindex="0" role="button">
                        @if(! empty($p['image']))
                            <span class="ico photo"><i class="bi {{ $p['icon'] }}"></i><img src="{{ $p['image'] }}" alt="" loading="lazy" decoding="async" onload="this.classList.add('is-in')" onerror="this.remove()"></span>
                        @else
                            <span class="ico"><i class="bi {{ $p['icon'] }}"></i></span>
                        @endif
                        <div class="flex-grow-1 min-w-0">
                            <div class="nm text-truncate">{{ $p['name'] }}</div>
                            <div class="sm"><span data-f="dist">{{ $p['walk_there'] }} min walk · {{ number_format($p['distance_m']) }} m</span> · {{ $p['category_label'] }}@if(! empty($p['preorder_url'])) · <span class="text-success fw-bold">Pre-order</span>@endif</div>
                        </div>
                        @if(! empty($p['is_cafe']))<a href="{{ $p['url'] }}" class="ex-open" aria-label="Open café: {{ $p['name'] }}">Open café</a>@endif
                        @if($p['sponsored'])<span class="ex-badge">Sponsored</span>@endif
                        @if($mins !== null && ! in_array($p['category'], ['hospital', 'transport']))
                            <span class="ex-fit {{ $p['fits'] ? 'yes' : 'no' }}" data-f="fit">{{ $p['fits'] ? 'Fits' : 'Too long' }}</span>
                        @endif
                    </div>
                @empty
                    <div class="ex-empty">No places listed for this location yet.</div>
                @endforelse
            </div>
            <p class="small pk-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Places are demo data for this local build. Walking times are approximate (straight-line based) — confirm details locally.</p>
        </div>
    </aside>

    {{-- ================= MAP ================= --}}
    <section class="ex-mapwrap" aria-label="Map">
        <div id="exMap" role="application" aria-label="Map of nearby places"></div>
        <div class="ex-pick-hint" id="pickHint"><i class="bi bi-hand-index me-1"></i>Tap the map where you are</div>
        <div class="ex-map-note" id="mapNote"><i class="bi bi-rulers me-1"></i>{{ $provider === 'osrm' ? 'Walking routes from the configured routing service — approximate.' : 'Dashed lines are direct lines, not routes. Times approximate.' }}</div>
    </section>
</div>

{{-- ================= PLACE DRAWER ================= --}}
<div class="offcanvas offcanvas-end ex-drawer" tabindex="-1" id="placeDrawer" aria-labelledby="drawerTitle">
    <div class="offcanvas-header pb-2">
        <div class="min-w-0">
            <div class="chip mb-1" id="dCat"></div>
            <h2 class="h5 mb-0" id="drawerTitle"></h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="drawerBody"></div>
</div>

<noscript><div class="container py-3"><div class="alert alert-warning">The map needs JavaScript. The list on this page still works.</div></div></noscript>
<script type="application/json" id="exploreData">@json($payload)</script>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/modules/explore.js') }}?v={{ filemtime(public_path('js/modules/explore.js')) }}"></script>
@endpush
