@extends('layouts.app')
@section('title', 'Incidents — Office operations — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
@endpush

@section('content')
@include('ops.partials.console-head', ['active' => 'incidents', 'title' => 'Incidents', 'subtitle' => 'Equipment, counter and service problems at your locations. Details here are internal.'])

<div class="container py-4 py-lg-5">
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4 reveal">
            <div class="pk-card ops-stat"><span class="pk-icon rose"><i class="bi bi-exclamation-octagon"></i></span><div><div class="v">{{ $counts['active'] }}</div><div class="l">Unresolved</div></div></div>
        </div>
        <div class="col-6 col-lg-4 reveal" style="--d:.05s">
            <div class="pk-card ops-stat"><span class="pk-icon amber"><i class="bi bi-lightning-charge"></i></span><div><div class="v">{{ $counts['critical'] }}</div><div class="l">High / critical open</div></div></div>
        </div>
        <div class="col-12 col-lg-4 reveal" style="--d:.1s">
            <div class="pk-card ops-stat"><span class="pk-icon green"><i class="bi bi-check2-circle"></i></span><div><div class="v">{{ $counts['resolved_today'] }}</div><div class="l">Resolved today</div></div></div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <nav class="seg-tabs" aria-label="Filter by status">
            @foreach(['active' => 'Active', 'resolved' => 'Resolved', 'all' => 'All'] as $k => $label)
                <a href="{{ route('ops.incidents.index', array_merge(request()->only('location', 'priority'), ['status' => $k])) }}" class="{{ $status === $k ? 'active' : '' }}" @if($status === $k) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('ops.incidents.index') }}" class="ops-filter" id="incFilter">
            <input type="hidden" name="status" value="{{ $status }}">
            @if($locations->count() > 1)
                <label class="visually-hidden" for="fLoc">Location</label>
                <select id="fLoc" name="location" class="form-select" onchange="this.form.submit()">
                    <option value="">All locations</option>
                    @foreach($locations as $l)<option value="{{ $l->id }}" @selected(($filters['location'] ?? null) == $l->id)>{{ $l->name }}</option>@endforeach
                </select>
            @endif
            <label class="visually-hidden" for="fPrio">Priority</label>
            <select id="fPrio" name="priority" class="form-select" onchange="this.form.submit()">
                <option value="">Any priority</option>
                @foreach(\App\Models\OperationalIncident::PRIORITIES as $p)<option value="{{ $p }}" @selected(($filters['priority'] ?? null) === $p)>{{ ucfirst($p) }}</option>@endforeach
            </select>
            <noscript><button class="btn btn-ghost btn-sm">Filter</button></noscript>
        </form>
    </div>

    <section class="pk-card inc-list reveal" aria-label="Incidents">
        @forelse($incidents as $i)
            <a href="{{ route('ops.incidents.show', $i) }}" class="inc-row p-{{ $i->priority }}">
                <span class="inc-ico" aria-hidden="true"><i class="bi {{ $i->categoryIcon() }}"></i></span>
                <div class="min-w-0">
                    <div class="inc-title"><span class="text-truncate">{{ $i->categoryLabel() }}@if($i->counter) · Counter {{ $i->counter->number }}@endif @if($i->equipment) · {{ $i->equipment }}@endif</span></div>
                    <div class="inc-desc">{{ $i->description }}</div>
                    <div class="inc-meta">
                        <span class="fw-semibold">{{ $i->reference() }}</span><span class="sep">·</span>{{ $i->location->name }}<span class="sep">·</span>{{ $i->reported_at?->diffForHumans() }}
                        @if($i->assigned_technician)<span class="sep">·</span><i class="bi bi-wrench-adjustable"></i> {{ $i->assigned_technician }}@endif
                        @if($i->photo_path)<span class="sep">·</span><i class="bi bi-image" aria-label="Has photo"></i>@endif
                    </div>
                </div>
                <div class="inc-side">@include('ops.partials.badges', ['incident' => $i])</div>
            </a>
        @empty
            <div class="ops-empty">
                <span class="pk-icon green mb-3"><i class="bi bi-emoji-smile"></i></span>
                <h2 class="h5 mb-1">{{ $status === 'active' ? 'All clear' : 'No incidents here' }}</h2>
                <p class="pk-muted mb-3">{{ $status === 'active' ? 'Nothing is broken at your locations right now.' : 'Try a different filter.' }}</p>
                <a href="{{ route('ops.incidents.create') }}" class="btn btn-ghost btn-sm"><i class="bi bi-plus-lg me-1"></i>Report an incident</a>
            </div>
        @endforelse
    </section>

    @if($incidents->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3 small">
            <span class="pk-muted">Showing {{ $incidents->firstItem() }}–{{ $incidents->lastItem() }} of {{ $incidents->total() }}</span>
            <div class="d-flex gap-2">
                @if($incidents->previousPageUrl())<a class="btn btn-ghost btn-sm" href="{{ $incidents->previousPageUrl() }}"><i class="bi bi-chevron-left"></i> Newer</a>@endif
                @if($incidents->nextPageUrl())<a class="btn btn-ghost btn-sm" href="{{ $incidents->nextPageUrl() }}">Older <i class="bi bi-chevron-right"></i></a>@endif
            </div>
        </div>
    @endif
</div>
@endsection
