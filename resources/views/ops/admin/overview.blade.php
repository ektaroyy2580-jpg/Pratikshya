@extends('layouts.app')
@section('title', 'Operations overview — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $cats = \App\Models\OperationalIncident::CATEGORIES;
    $unresolved = ($byStatus['open'] ?? 0) + ($byStatus['acknowledged'] ?? 0) + ($byStatus['in_progress'] ?? 0);
    $statusColors = ['open' => 'var(--pk-danger)', 'acknowledged' => 'var(--pk-warn)', 'in_progress' => 'var(--pk-blue)', 'resolved' => 'var(--pk-success)', 'closed' => '#9aa1bd'];
    $prioColors = ['critical' => 'var(--pk-danger)', 'high' => 'var(--pk-warn)', 'medium' => 'var(--pk-blue)', 'low' => '#9aa1bd'];
    $maxStatus = max(1, (int) $byStatus->max());
    $maxPrio = max(1, (int) $byPriority->max());
    $maxCat = max(1, (int) $byCategory->max());
    $maxLoc = max(1, (int) $byLocation->max());
    $mttrLabel = $mttr === null ? '—' : ($mttr >= 120 ? round($mttr / 60, 1).' h' : $mttr.' min');
@endphp
@include('ops.partials.console-head', ['active' => 'overview', 'title' => 'Operations overview', 'subtitle' => 'All locations · incident and notice activity for analytics'])

<div class="container py-4 py-lg-5">
    <div class="row g-3 mb-4">
        @foreach([
            ['Unresolved incidents', $unresolved, 'bi-exclamation-octagon', 'rose'],
            ['Critical / high open', ($byPriority['critical'] ?? 0) + ($byPriority['high'] ?? 0), 'bi-lightning-charge', 'amber'],
            ['Avg. time to resolve', $mttrLabel, 'bi-stopwatch', 'violet'],
            ['Live public notices', $notices['live'], 'bi-megaphone', ''],
        ] as $i => [$label, $value, $icon, $tone])
            <div class="col-6 col-xl-3 reveal" style="--d:{{ $i * .05 }}s">
                <div class="pk-card ops-stat"><span class="pk-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span><div><div class="v">{{ $value }}</div><div class="l">{{ $label }}</div></div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="pk-card p-4 h-100 reveal" aria-labelledby="bsHead">
                <h2 id="bsHead" class="h6 fw-bold mb-3">Incidents by status <span class="pk-muted fw-semibold">· {{ $total }} total</span></h2>
                @foreach(\App\Models\OperationalIncident::STATUSES as $s)
                    @php $n = (int) ($byStatus[$s] ?? 0); @endphp
                    <div class="bar-row">
                        <span class="lbl">{{ ucfirst(str_replace('_', ' ', $s)) }}</span>
                        <span class="track" role="img" aria-label="{{ $n }} {{ $s }}"><span class="fill d-block" style="width:{{ $n / $maxStatus * 100 }}%;background:{{ $statusColors[$s] }}"></span></span>
                        <span class="num">{{ $n }}</span>
                    </div>
                @endforeach
            </section>
        </div>
        <div class="col-lg-6">
            <section class="pk-card p-4 h-100 reveal" style="--d:.05s" aria-labelledby="bpHead">
                <h2 id="bpHead" class="h6 fw-bold mb-3">Open incidents by priority</h2>
                @foreach(array_reverse(\App\Models\OperationalIncident::PRIORITIES) as $p)
                    @php $n = (int) ($byPriority[$p] ?? 0); @endphp
                    <div class="bar-row">
                        <span class="lbl">{{ ucfirst($p) }}</span>
                        <span class="track" role="img" aria-label="{{ $n }} {{ $p }}"><span class="fill d-block" style="width:{{ $n / $maxPrio * 100 }}%;background:{{ $prioColors[$p] }}"></span></span>
                        <span class="num">{{ $n }}</span>
                    </div>
                @endforeach
            </section>
        </div>
        <div class="col-lg-6">
            <section class="pk-card p-4 h-100 reveal" aria-labelledby="bcHead">
                <h2 id="bcHead" class="h6 fw-bold mb-3">By category <span class="pk-muted fw-semibold">· all time</span></h2>
                @forelse($byCategory as $c => $n)
                    <div class="bar-row">
                        <span class="lbl"><i class="bi {{ $cats[$c][1] ?? 'bi-dot' }} me-1 pk-muted"></i>{{ $cats[$c][0] ?? $c }}</span>
                        <span class="track" role="img" aria-label="{{ $n }}"><span class="fill d-block" style="width:{{ $n / $maxCat * 100 }}%;background:var(--pk-violet)"></span></span>
                        <span class="num">{{ $n }}</span>
                    </div>
                @empty
                    <p class="pk-muted small mb-0">No incidents recorded yet.</p>
                @endforelse
            </section>
        </div>
        <div class="col-lg-6">
            <section class="pk-card p-4 h-100 reveal" style="--d:.05s" aria-labelledby="blHead">
                <h2 id="blHead" class="h6 fw-bold mb-3">Unresolved by location</h2>
                @foreach($locations as $l)
                    @php $n = (int) ($byLocation[$l->id] ?? 0); @endphp
                    <div class="bar-row">
                        <a class="lbl text-decoration-none" href="{{ route('ops.incidents.index', ['location' => $l->id]) }}" title="{{ $l->name }}">{{ $l->name }}</a>
                        <span class="track" role="img" aria-label="{{ $n }}"><span class="fill d-block" style="width:{{ $n / $maxLoc * 100 }}%"></span></span>
                        <span class="num">{{ $n }}</span>
                    </div>
                @endforeach
            </section>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <section class="pk-card inc-list reveal" aria-labelledby="rcHead">
                <div class="d-flex justify-content-between align-items-center px-4 pt-4 pb-2">
                    <h2 id="rcHead" class="h6 fw-bold mb-0">Latest incidents</h2>
                    <a href="{{ route('ops.incidents.index', ['status' => 'all']) }}" class="small fw-bold text-decoration-none">View all <i class="bi bi-arrow-right"></i></a>
                </div>
                @forelse($recent as $i)
                    <a href="{{ route('ops.incidents.show', $i) }}" class="inc-row p-{{ $i->priority }}">
                        <span class="inc-ico" aria-hidden="true"><i class="bi {{ $i->categoryIcon() }}"></i></span>
                        <div class="min-w-0">
                            <div class="inc-title"><span class="text-truncate">{{ $i->categoryLabel() }}@if($i->counter) · Counter {{ $i->counter->number }}@endif</span></div>
                            <div class="inc-meta">{{ $i->reference() }}<span class="sep">·</span>{{ $i->location->name }}<span class="sep">·</span>{{ $i->reported_at?->diffForHumans() }}</div>
                        </div>
                        <div class="inc-side">@include('ops.partials.badges', ['incident' => $i])</div>
                    </a>
                @empty
                    <div class="ops-empty"><p class="pk-muted mb-0">No incidents yet.</p></div>
                @endforelse
            </section>
        </div>
        <div class="col-lg-4">
            <section class="pk-card p-4 reveal" style="--d:.05s" aria-labelledby="nHead">
                <h2 id="nHead" class="h6 fw-bold mb-3">Public notices</h2>
                <div class="d-flex justify-content-between py-2 border-bottom small"><span class="pk-muted fw-semibold">Live now</span><b>{{ $notices['live'] }}</b></div>
                <div class="d-flex justify-content-between py-2 border-bottom small"><span class="pk-muted fw-semibold">Published (7 days)</span><b>{{ $notices['week'] }}</b></div>
                <div class="d-flex justify-content-between py-2 small"><span class="pk-muted fw-semibold">Drafts</span><b>{{ $notices['drafts'] }}</b></div>
                <a href="{{ route('ops.notices.index') }}" class="btn btn-ghost btn-sm w-100 mt-3">Manage notices</a>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/ops-console.js') }}?v={{ filemtime(public_path('js/modules/ops-console.js')) }}"></script>
@endpush
