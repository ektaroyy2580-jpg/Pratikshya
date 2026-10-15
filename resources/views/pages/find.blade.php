@extends('layouts.app')
@section('title', 'Find a Service — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<style>
    .min-w-0 { min-width: 0; }
    .find-head h1 { font-size: clamp(1.9rem, 4.4vw, 2.9rem); line-height: 1.08; }
    .search-field { position: relative; max-width: 680px; }
    .search-field .bi-search { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: var(--pk-muted); font-size: 1.15rem; pointer-events: none; }
    .search-input { height: 60px; border-radius: 999px; padding: 0 7.5rem 0 3.25rem; font-size: 1.05rem; font-weight: 500; background: #fff; border: 1px solid var(--pk-line); box-shadow: 0 12px 32px -18px rgba(31, 41, 110, .35), var(--pk-shadow-sm); }
    .search-input::placeholder { color: #98a0bd; }
    .search-submit { position: absolute; right: 7px; top: 7px; bottom: 7px; border-radius: 999px !important; padding: 0 1.2rem !important; }
    @media (max-width: 575.98px) {
        .search-input { height: 54px; padding-right: 1rem; font-size: 1rem; }
        .search-submit { display: none; }
    }

    .filter-bar { display: flex; gap: .5rem; overflow-x: auto; padding: 2px 2px 4px; scrollbar-width: none; }
    .filter-bar::-webkit-scrollbar { display: none; }
    .filter-pill { flex-shrink: 0; display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--pk-line); background: #fff; color: var(--pk-ink-2); font-weight: 700; font-size: .85rem; padding: .48rem .95rem; border-radius: 999px; transition: background .2s, color .2s, border-color .2s, box-shadow .2s; }
    .filter-pill:hover { border-color: #cdd3ea; color: var(--pk-blue); }
    .filter-pill.active { background: var(--pk-ink); border-color: var(--pk-ink); color: #fff; box-shadow: 0 6px 16px -8px rgba(15, 21, 53, .55); }
    .filter-pill i { font-size: .95rem; }

    .loc-card { overflow: hidden; transition: border-color .2s, box-shadow .25s var(--pk-ease); }
    .loc-card.active { border-color: #c3cdf8; box-shadow: 0 0 0 4px rgba(47, 91, 234, .08), var(--pk-shadow); }
    .loc-head { display: flex; align-items: center; gap: .9rem; padding: 1.1rem 1.25rem; width: 100%; background: transparent; border: 0; text-align: left; color: inherit; cursor: pointer; }
    .loc-head:focus-visible { outline-offset: -3px; border-radius: var(--pk-radius) var(--pk-radius) 0 0; }
    .loc-head .name { font-weight: 800; font-size: 1.05rem; letter-spacing: -.015em; line-height: 1.25; }
    .loc-head .meta { font-size: .82rem; color: var(--pk-muted); font-weight: 600; }
    .loc-total { text-align: right; flex-shrink: 0; }
    .loc-total .n { font-weight: 800; font-size: 1.25rem; line-height: 1; letter-spacing: -.02em; }
    .loc-total .l { font-size: .72rem; color: var(--pk-muted); font-weight: 600; }

    .svc-list { border-top: 1px solid var(--pk-line); }
    .svc-row { display: flex; align-items: center; gap: 1rem; padding: .95rem 1.25rem; text-decoration: none; color: inherit; transition: background .2s; }
    .svc-row + .svc-row { border-top: 1px solid #f0f1f7; }
    .svc-row:hover { background: #f9faff; color: inherit; }
    .svc-row:focus-visible { outline-offset: -3px; border-radius: 0; }
    .svc-name { font-weight: 700; line-height: 1.3; }
    .svc-meta { display: flex; flex-wrap: wrap; gap: .3rem .9rem; margin-top: .3rem; font-size: .8rem; color: var(--pk-muted); font-weight: 600; }
    .svc-meta b { color: var(--pk-ink); font-weight: 800; }
    .load-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .svc-wait { text-align: right; flex-shrink: 0; min-width: 92px; }
    .svc-wait .range { font-weight: 800; font-size: 1.05rem; letter-spacing: -.02em; white-space: nowrap; }
    .svc-wait .range small { font-size: .75rem; font-weight: 700; color: var(--pk-muted); }
    .svc-cta { display: inline-flex; align-items: center; gap: .25rem; font-size: .8rem; font-weight: 800; color: var(--pk-blue); margin-top: .2rem; white-space: nowrap; }
    .svc-cta i { transition: transform .2s var(--pk-ease); }
    .svc-row:hover .svc-cta i { transform: translateX(3px); }
    .paused-tag { font-size: .7rem; font-weight: 800; color: var(--pk-warn); background: var(--pk-warn-tint); border-radius: 999px; padding: .1rem .5rem; margin-left: .35rem; vertical-align: 2px; }

    @media (max-width: 575.98px) {
        .loc-head, .svc-row { padding-left: 1rem; padding-right: 1rem; }
        .svc-row { gap: .75rem; }
        .svc-wait { min-width: 0; }
    }

    .map-card { overflow: hidden; }
    #locMap { height: 230px; background: #e9edf7; z-index: 0; }
    @media (min-width: 992px) {
        .map-sticky { position: sticky; top: 88px; }
        #locMap { height: 420px; }
    }
    .map-foot { padding: .9rem 1.1rem; display: flex; align-items: center; gap: .75rem; }
    .leaflet-container { font-family: var(--pk-font); }
    .leaflet-tooltip { border-radius: 10px; border: 1px solid var(--pk-line); box-shadow: var(--pk-shadow); font-weight: 700; color: var(--pk-ink); }

    .empty-state { border: 1px dashed #d5daea; border-radius: var(--pk-radius); background: #fff; padding: 2.75rem 1.5rem; text-align: center; }
    .empty-state .pk-icon { width: 56px; height: 56px; font-size: 1.5rem; border-radius: 16px; }
</style>
@endpush

@section('content')
@php
    $filters = ['All' => 'bi-grid', 'Government' => 'bi-bank2', 'Bank' => 'bi-cash-coin', 'Hospital' => 'bi-hospital', 'Ward Office' => 'bi-house-door'];
@endphp

<header class="pk-page-head find-head">
    <div class="container">
      <div class="row g-4 align-items-center">
       <div class="col-lg-8">
        <div class="pk-eyebrow mb-2 fade-up"><span class="live-dot"></span> Live queues</div>
        <h1 class="mb-2 fade-up" style="animation-delay:.04s">Where are you going <span class="text-gradient">today?</span></h1>
        <p class="pk-muted mb-4 fade-up" style="animation-delay:.08s;max-width:36rem">See who is being served, how many are ahead and an honest wait range before you leave home.</p>

        {{-- Instant client-side filtering, with a server-side search fallback on Enter. --}}
        <form method="GET" action="{{ route('find') }}" class="search-field mb-3 fade-up" style="animation-delay:.12s" role="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="q" class="visually-hidden">Search locations and services</label>
            <input id="q" name="q" value="{{ $q }}" maxlength="80" type="search" class="form-control search-input" placeholder="Try licence, passport, bank…" autocomplete="off">
            <button type="submit" class="btn btn-pk search-submit">Search</button>
        </form>

        <div class="filter-bar fade-up" style="animation-delay:.16s" id="filters" role="group" aria-label="Filter by type">
            @foreach($filters as $f => $icon)
                <button type="button" class="filter-pill filter-btn {{ $loop->first ? 'active' : '' }}" data-type="{{ $f }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $f }}</button>
            @endforeach
        </div>

        @if($q !== '')
            <div class="small pk-muted mt-3 fw-semibold">{{ $locations->count() }} {{ \Illuminate\Support\Str::plural('place', $locations->count()) }} match “{{ $q }}” · <a href="{{ route('find') }}" class="fw-bold">Clear search</a></div>
        @endif
       </div>
       <div class="col-lg-4 d-none d-lg-block">
            @php
                $allSvc = $locations->flatMap->services;
                $quietest = $allSvc->sortBy(fn($s) => $s->waitingCount())->first();
            @endphp
            <div class="pk-card p-4 fade-up" style="animation-delay:.18s">
                <div class="pk-eyebrow mb-3" style="color:var(--pk-muted)">At a glance</div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="pk-icon"><i class="bi bi-people"></i></span>
                    <div><div class="fw-bold fs-4 lh-1">{{ $allSvc->sum(fn($s) => $s->waitingCount()) }}</div><div class="small pk-muted fw-semibold">people waiting now</div></div>
                </div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="pk-icon violet"><i class="bi bi-broadcast"></i></span>
                    <div><div class="fw-bold fs-4 lh-1">{{ $allSvc->count() }}</div><div class="small pk-muted fw-semibold">live services in {{ $locations->count() }} {{ \Illuminate\Support\Str::plural('place', $locations->count()) }}</div></div>
                </div>
                @if($quietest)
                    <a href="{{ route('service', $quietest) }}" class="d-flex align-items-center gap-3 text-decoration-none text-reset p-2 rounded-3" style="background:var(--pk-success-tint)">
                        <span class="pk-icon green" style="width:38px;height:38px;background:#fff"><i class="bi bi-lightning-charge"></i></span>
                        <span class="min-w-0"><span class="d-block small fw-bold" style="color:#116a35">Shortest queue</span><span class="d-block small fw-semibold text-truncate">{{ $quietest->name }}</span></span>
                    </a>
                @endif
            </div>
       </div>
      </div>
    </div>
</header>

<div class="container py-4 py-lg-5">
    <div class="row g-4">
        {{-- Map: first on mobile for context, sticky on the right on desktop --}}
        <div class="col-lg-5 order-lg-2 {{ $locations->isEmpty() ? 'd-none d-lg-block' : '' }}">
            <div class="map-sticky">
                <div class="pk-card map-card fade-up" style="animation-delay:.1s">
                    <div id="locMap" role="region" aria-label="Map of locations"></div>
                    <div class="map-foot">
                        <span class="pk-icon" style="width:38px;height:38px;font-size:1.05rem" id="mapIcon"><i class="bi bi-geo-alt-fill"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-truncate" id="mapName">{{ $locations->first()?->name ?? 'Kathmandu' }}</div>
                            <div class="small pk-muted fw-semibold text-truncate" id="mapArea">{{ $locations->first()?->area ?? 'Select a location' }}</div>
                        </div>
                        <span class="chip"><span class="live-dot"></span> Live</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 order-lg-1">
            <div class="d-flex justify-content-between align-items-baseline mb-3">
                <h2 class="h5 fw-bold mb-0">Nearby offices</h2>
                <span class="small pk-muted fw-semibold" id="resultCount" aria-live="polite">{{ $locations->count() }} {{ \Illuminate\Support\Str::plural('place', $locations->count()) }}</span>
            </div>

            <div class="d-grid gap-3" id="locList">
                @foreach($locations as $loc)
                    @php $waiting = $loc->services->sum(fn($s) => $s->waitingCount()); @endphp
                    <article class="pk-card loc-card reveal {{ $loop->first ? 'active' : '' }}" style="--d: {{ min($loop->index, 5) * .06 }}s"
                             data-id="{{ $loc->id }}" data-type="{{ $loc->type }}"
                             data-search="{{ strtolower($loc->name.' '.$loc->area.' '.$loc->type.' '.$loc->services->pluck('name')->join(' ')) }}">
                        <button type="button" class="loc-head" data-select="{{ $loc->id }}" aria-label="Show {{ $loc->name }} on the map">
                            <span class="pk-icon"><i class="bi {{ $loc->icon }}"></i></span>
                            <span class="flex-grow-1 min-w-0">
                                <span class="name d-block">{{ $loc->name }}</span>
                                <span class="meta d-block text-truncate"><i class="bi bi-geo-alt"></i> {{ $loc->area }}<span class="d-none d-sm-inline"> · {{ $loc->type }}</span></span>
                            </span>
                            <span class="loc-total">
                                <span class="n d-block">{{ $waiting }}</span>
                                <span class="l d-block">waiting</span>
                            </span>
                        </button>
                        <div class="svc-list">
                            @forelse($loc->services as $s)
                                @php
                                    $est = $s->estimate();
                                    $w = $s->waitingCount();
                                    $load = $w > 12 ? ['var(--pk-danger)', 'Busy'] : ($w > 6 ? ['var(--pk-warn)', 'Moderate'] : ['var(--pk-success)', 'Quiet']);
                                @endphp
                                <a class="svc-row" href="{{ route('service', $s) }}" aria-label="{{ $s->name }}: {{ $w }} waiting, about {{ $est['low'] }} to {{ $est['high'] }} minutes. View and join.">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="svc-name">{{ $s->name }}@if($s->is_paused)<span class="paused-tag">Paused</span>@endif</div>
                                        <div class="svc-meta">
                                            <span>Serving <b>{{ $s->token($s->current_number) }}</b></span>
                                            <span><span class="load-dot" style="background:{{ $load[0] }}"></span> <b>{{ $w }}</b> waiting</span>
                                            <span class="d-none d-sm-inline">Counter {{ $s->counter }}</span>
                                        </div>
                                    </div>
                                    <div class="svc-wait">
                                        <div class="range">{{ $est['low'] }}–{{ $est['high'] }} <small>min</small></div>
                                        <div class="svc-cta">View &amp; join <i class="bi bi-arrow-right"></i></div>
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-3 small pk-muted">No live services here right now.</div>
                            @endforelse
                        </div>
                    </article>
                @endforeach

                <div id="empty" class="empty-state {{ $locations->isEmpty() ? '' : 'd-none' }}">
                    <span class="pk-icon violet mb-3"><i class="bi bi-search"></i></span>
                    <h3 class="h5 fw-bold mb-1">No matching services</h3>
                    <p class="small pk-muted mb-3 mx-auto" style="max-width:22rem">Try a broader word like “licence”, “bank” or “hospital”, or switch the filter back to All.</p>
                    <a href="{{ route('find') }}" class="btn btn-ghost btn-sm" id="resetSearch">Show all places</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
@php $locJson = $locations->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'area' => $l->area, 'icon' => $l->icon, 'lat' => (float) $l->lat, 'lng' => (float) $l->lng]); @endphp
const locs = @json($locJson);
const map = L.map('locMap', { zoomControl: false, scrollWheelZoom: false }).setView([27.69, 85.315], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
L.control.zoom({ position: 'bottomright' }).addTo(map);
const markers = {};
locs.forEach(l => { markers[l.id] = L.marker([l.lat, l.lng]).addTo(map).bindTooltip(l.name).on('click', () => select(l.id, true)); });
if (locs.length > 1) map.fitBounds(locs.map(l => [l.lat, l.lng]), { padding: [30, 30], maxZoom: 14 });

function select(id, scroll) {
    const l = locs.find(x => x.id == id); if (!l) return;
    document.querySelectorAll('.loc-card').forEach(c => c.classList.toggle('active', c.dataset.id == id));
    document.getElementById('mapName').textContent = l.name;
    document.getElementById('mapArea').textContent = l.area || '';
    document.getElementById('mapIcon').innerHTML = `<i class="bi ${l.icon || 'bi-geo-alt-fill'}"></i>`;
    map.flyTo([l.lat, l.lng], 15, { duration: .6 }); markers[id].openTooltip();
    if (scroll) { const c = document.querySelector(`.loc-card[data-id="${id}"]`); c && c.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
}
document.querySelectorAll('[data-select]').forEach(b => b.addEventListener('click', () => select(b.dataset.select)));

let type = 'All';
function filter() {
    const q = document.getElementById('q').value.toLowerCase().trim(); let shown = 0, first = null;
    document.querySelectorAll('.loc-card').forEach(c => {
        const ok = (type === 'All' || c.dataset.type === type) && (!q || c.dataset.search.includes(q));
        c.classList.toggle('d-none', !ok); if (ok) { shown++; first = first || c; }
    });
    document.getElementById('empty').classList.toggle('d-none', shown > 0);
    document.getElementById('resultCount').textContent = shown + (shown === 1 ? ' place' : ' places');
    if (first) select(first.dataset.id);
}
document.getElementById('q').addEventListener('input', filter);
document.querySelectorAll('.filter-btn').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(x => { x.classList.remove('active'); x.setAttribute('aria-pressed', 'false'); });
    b.classList.add('active'); b.setAttribute('aria-pressed', 'true'); type = b.dataset.type; filter();
}));
document.getElementById('resetSearch').addEventListener('click', e => {
    if (!new URLSearchParams(location.search).get('q')) {
        e.preventDefault(); document.getElementById('q').value = ''; document.querySelector('.filter-btn[data-type="All"]').click();
    }
});
</script>
@endpush
