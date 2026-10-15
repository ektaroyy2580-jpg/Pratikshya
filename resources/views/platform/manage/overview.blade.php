@extends('platform.layouts.console')
@section('title', 'Management console — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-grid-1x2"></i> Management console</span>
        <h1>Good to see you, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</h1>
        <p>{{ $isPlatformAdmin ? 'You manage every organization on the platform.' : 'You manage the organizations listed below — nothing else is visible to you.' }}</p>
    </div>
    <span class="pf-demo"><i class="bi bi-database"></i> Local demo data</span>
</div>

@php
    $tiles = [
        ['Organizations', $stats['organizations'], 'bi-buildings', '', 'platform.manage.organizations'],
        ['Locations', $stats['locations'], 'bi-geo-alt', 'violet', 'platform.manage.locations'],
        ['Services', $stats['services'], 'bi-ticket-perforated', 'green', 'platform.manage.locations'],
        ['Paused queues', $stats['paused'], 'bi-pause-circle', 'amber', 'platform.manage.locations'],
        ['Counters open', $stats['counters_open'].' / '.$stats['counters'], 'bi-door-open', '', 'platform.manage.locations'],
        ['Staff assignments', $stats['staff'], 'bi-person-badge', 'violet', 'platform.manage.staff'],
        ['Pending approvals', $stats['pending'], 'bi-hourglass-split', $stats['pending'] ? 'rose' : 'green', 'platform.manage.staff'],
        ['Nearby & listings', $stats['places'], 'bi-shop', 'amber', 'platform.manage.places'],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach($tiles as $i => [$label, $value, $icon, $tone, $route])
        <div class="col-6 col-xl-3 reveal" style="--d: {{ $i * .04 }}s">
            <a href="{{ route($route) }}" class="pk-card lift pf-kpi text-decoration-none text-reset">
                <span class="pk-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                <div><div class="v">{{ $value }}</div><div class="l">{{ $label }}</div></div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="pk-card pf-panel h-100 reveal" aria-labelledby="orgsH">
            <div class="pf-panel-head">
                <h2 id="orgsH"><i class="bi bi-buildings text-primary"></i> Your organizations</h2>
                <a href="{{ route('platform.manage.organizations') }}" class="btn btn-ghost btn-xs">View all</a>
            </div>
            @forelse($organizations->take(6) as $org)
                <a href="{{ route('platform.manage.organizations.show', $org) }}" class="d-flex align-items-center gap-3 px-3 px-md-4 py-3 border-top text-decoration-none text-reset" style="border-color:#f0f1f7 !important">
                    <span class="pk-icon" style="width:38px;height:38px;font-size:1rem"><i class="bi {{ ['government' => 'bi-bank', 'bank' => 'bi-piggy-bank', 'hospital' => 'bi-hospital', 'ward' => 'bi-house-door', 'private' => 'bi-shop'][$org->type] ?? 'bi-building' }}"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-bold text-truncate">{{ $org->name }}</div>
                        <div class="small pk-muted">{{ ucfirst($org->type) }} · {{ $org->locations_count }} {{ \Illuminate\Support\Str::plural('location', $org->locations_count) }}</div>
                    </div>
                    <i class="bi bi-chevron-right pk-muted"></i>
                </a>
            @empty
                <div class="pf-empty"><span class="pk-icon mb-2"><i class="bi bi-buildings"></i></span><p class="pk-muted mb-0">No organizations yet.</p></div>
            @endforelse
        </section>
    </div>
    <div class="col-lg-5">
        <section class="pk-card pf-panel h-100 reveal" style="--d:.08s" aria-labelledby="qaH">
            <div class="pf-panel-head"><h2 id="qaH"><i class="bi bi-lightning-charge text-primary"></i> Quick actions</h2></div>
            <div class="pf-panel-body d-grid gap-2">
                <a class="btn btn-pk" href="{{ route('platform.manage.locations.create') }}"><i class="bi bi-plus-lg me-1"></i> Add a location</a>
                <a class="btn btn-ghost" href="{{ route('platform.manage.staff') }}"><i class="bi bi-person-plus me-1"></i> Assign staff</a>
                <a class="btn btn-ghost" href="{{ route('platform.manage.places.create') }}"><i class="bi bi-shop me-1"></i> Add a nearby place</a>
                @if($isPlatformAdmin)
                    <a class="btn btn-ghost" href="{{ route('platform.analytics') }}"><i class="bi bi-graph-up me-1"></i> Open analytics</a>
                @endif
                <p class="small pk-muted mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Every change here is recorded in the audit log. Queue order can only be changed by the queue engine — never from this console.</p>
            </div>
        </section>
    </div>
</div>
@endsection
