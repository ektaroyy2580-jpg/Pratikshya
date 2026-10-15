@extends('layouts.app')
@section('title', $place->name.' — Explore — प्रतीक्षा')

@php
    $rec = ! in_array($item['category'], ['hospital', 'transport'], true);
    $routed = $estimate['method'] === 'osrm' && $estimate['geometry'];
    $mapData = [
        'from' => [(float) $location->lat, (float) $location->lng],
        'to' => [$item['lat'], $item['lng']],
        'color' => $item['color'], 'icon' => $item['icon'],
        'geometry' => $routed ? $estimate['geometry'] : null,
        'label' => $routed ? $estimate['walk_minutes'].' min · walking route (approximate)' : $estimate['walk_minutes'].' min · direct line, not a route',
    ];
@endphp

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('css/modules/explore.css') }}?v={{ filemtime(public_path('css/modules/explore.css')) }}">
<style>
    #placeMap { height: 340px; border-radius: 18px; border: 1px solid var(--pk-line); }
    @media (max-width: 767.98px) { #placeMap { height: 260px; } }
</style>
@endpush

@section('content')
<section class="pk-page-head">
    <div class="container">
        <a class="pk-breadcrumb" href="{{ route('explore.index', ['location' => $location->id]) }}"><i class="bi bi-arrow-left me-1"></i>Explore near {{ $location->name }}</a>
        <div class="d-flex align-items-center gap-3 mt-3 fade-up">
            <span class="pk-icon" style="background: color-mix(in srgb, {{ $item['color'] }} 14%, #fff); color: {{ $item['color'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
            <div class="min-w-0">
                <h1 class="h3 mb-1">{{ $place->name }}</h1>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="chip">{{ $item['category_label'] }}@if($item['subcategory']) · {{ $item['subcategory'] }}@endif</span>
                    <span class="small pk-muted fw-semibold"><i class="bi bi-star-fill" style="color:#f5b301"></i> {{ number_format($item['rating'], 1) }} (demo)</span>
                    @if($item['is_partner'])<span class="chip" style="background:var(--pk-success-tint);color:var(--pk-success)">Participating business</span>@endif
                    @if($item['sponsored'])<span class="ex-badge">Sponsored</span>@endif
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-7">
            <div id="placeMap" class="reveal" role="img" aria-label="Map showing {{ $place->name }} and {{ $location->name }}"></div>
            <div class="small pk-muted mt-2"><i class="bi bi-info-circle me-1"></i>
                @if($routed) Route from the configured routing service — approximate.
                @else The dashed line is a <b>direct line, not a route</b>. Follow streets and signs on the ground.
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="pk-card p-4 reveal" style="--d:.06s">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4"><div class="ex-stat"><div class="l">Walk there</div><div class="v">{{ $item['walk_there'] }}<small class="fs-6 pk-muted"> min</small></div></div></div>
                    <div class="col-4"><div class="ex-stat"><div class="l">{{ $rec ? 'Visit' : 'Stop' }}</div><div class="v">{{ $item['visit'] }}<small class="fs-6 pk-muted"> min</small></div></div></div>
                    <div class="col-4"><div class="ex-stat"><div class="l">Back</div><div class="v">{{ $item['walk_back'] }}<small class="fs-6 pk-muted"> min</small></div></div></div>
                </div>

                @if($rec && $remaining['minutes'] !== null)
                    <div class="alert {{ $item['fits'] ? 'alert-success' : 'alert-warning' }} small mb-3">
                        <i class="bi {{ $item['fits'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }} me-1"></i>
                        <b>{{ $ticket ? 'You have approximately '.$remaining['minutes'].' minutes.' : '' }}</b> {{ $item['explanation'] }}
                    </div>
                @elseif($rec)
                    <p class="small pk-muted mb-3">{{ $item['explanation'] }} Join a queue at {{ $location->name }} to see whether it fits your wait.</p>
                @endif

                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    @if($rideUrl)<a href="{{ $rideUrl }}" class="btn btn-pk btn-sm"><i class="bi bi-car-front me-1"></i>Ride there</a>@endif
                    @if($preorderUrl)<a href="{{ $preorderUrl }}" class="btn btn-ghost btn-sm"><i class="bi bi-bag-check me-1"></i>Preorder</a>@endif
                    <a href="{{ route('explore.index', ['location' => $location->id]) }}" class="btn btn-ghost btn-sm"><i class="bi bi-map me-1"></i>Back to map</a>
                    @if($rec)
                        @if($dismissed)
                            <span class="small pk-muted align-self-center"><i class="bi bi-eye-slash me-1"></i>Hidden from suggestions</span>
                        @else
                            <form method="POST" action="{{ route('explore.dismiss', $place) }}" class="m-0">@csrf
                                <button class="btn btn-link btn-sm text-danger text-decoration-none"><i class="bi bi-eye-slash me-1"></i>Not interested</button>
                            </form>
                        @endif
                    @endif
                </div>

                <div class="ex-kv"><i class="bi bi-rulers"></i><div>≈ {{ number_format($estimate['distance_m']) }} m on foot from {{ $location->name }} <span class="pk-muted">({{ $estimate['label'] }}; straight line {{ number_format($estimate['straight_m']) }} m)</span></div></div>
                <div class="ex-kv"><i class="bi bi-clock"></i><div>{{ $item['hours_text'] }} @if($item['hours_verified'])<span class="badge text-bg-success ms-1">Verified</span>@else<span class="pk-muted">— check before you go</span>@endif</div></div>
                @if($place->tip)<div class="ex-kv"><i class="bi bi-lightbulb"></i><div>{{ $place->tip }}</div></div>@endif
                @if($item['accessibility'])<div class="ex-kv"><i class="bi bi-universal-access"></i><div>{{ $item['accessibility'] }}</div></div>@endif
                @if($place->address)<div class="ex-kv"><i class="bi bi-geo-alt"></i><div>{{ $place->address }}</div></div>@endif
                @if($place->phone)<div class="ex-kv"><i class="bi bi-telephone"></i><div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $place->phone) }}">{{ $place->phone }}</a></div></div>@endif
            </div>

            @if($item['category'] === 'pharmacy')
                <div class="small pk-muted mt-3"><i class="bi bi-info-circle me-1"></i>Pharmacy listing only. Ask a qualified pharmacist or doctor about medicines — this app gives no medical advice.</div>
            @endif
        </div>
    </div>
</div>
<script type="application/json" id="placeMapData">@json($mapData)</script>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(() => {
    if (!window.L) return;
    const d = JSON.parse(document.getElementById('placeMapData').textContent);
    const map = L.map('placeMap', { scrollWheelZoom: false, zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
    L.marker(d.from, { icon: L.divIcon({ className: '', html: '<div class="ex-home"><i class="bi bi-hourglass-split"></i>Queue</div>', iconSize: [80, 30], iconAnchor: [40, 15] }) }).addTo(map);
    L.marker(d.to, { icon: L.divIcon({ className: '', html: `<div class="ex-pin sel" style="--c:${d.color}"><i class="bi ${d.icon}"></i></div>`, iconSize: [32, 32], iconAnchor: [16, 38] }) }).addTo(map);
    const pts = d.geometry || [d.from, d.to];
    const line = L.polyline(pts, d.geometry ? { color: '#2f5bea', weight: 5, opacity: .85 } : { color: '#2f5bea', weight: 3, dashArray: '2 10', lineCap: 'round' }).addTo(map);
    line.bindTooltip(d.label, { permanent: true, direction: 'center', className: 'ex-line-label' });
    map.fitBounds(L.latLngBounds(pts).pad(0.35), { maxZoom: 17.5 });
})();
</script>
@endpush
