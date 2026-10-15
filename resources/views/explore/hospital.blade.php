@extends('layouts.app')
@section('title', 'Hospital assistance — '.$location->name.' — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/explore.css') }}?v={{ filemtime(public_path('css/modules/explore.css')) }}">
@endpush

@php
    $section = fn ($icon, $color, $title) => '<div class="d-flex align-items-center gap-2 mb-3"><span class="pk-icon '.$color.'" style="width:38px;height:38px;font-size:1.05rem"><i class="bi '.$icon.'"></i></span><h2 class="h6 mb-0">'.e($title).'</h2></div>';
@endphp

@section('content')
<section class="pk-page-head pb-4">
    <div class="container">
        <a class="pk-breadcrumb" href="{{ route('explore.index', ['location' => $location->id]) }}"><i class="bi bi-arrow-left me-1"></i>Explore near {{ $location->name }}</a>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-2 mb-3">
            <div>
                <span class="pk-eyebrow"><i class="bi bi-heart-pulse"></i>Hospital assistance</span>
                <h1 class="h3 mb-1 mt-1">{{ $location->name }}</h1>
                <div class="pk-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $location->area }}</div>
            </div>
            <span class="hx-demo"><i class="bi bi-info-circle-fill"></i>{{ $profile?->official_source ?: 'Demo data — confirm with hospital' }}</span>
        </div>
        <div class="fade-up">@include('explore.partials.emergency-panel', ['profile' => $profile])</div>
    </div>
</section>

<div class="container py-4">
    @if($ticket)
        <div class="pk-card p-3 mb-4 d-flex flex-wrap align-items-center gap-3 reveal">
            <span class="pk-icon"><i class="bi bi-ticket-perforated"></i></span>
            <div class="flex-grow-1 small"><b>Your token {{ $ticket->token() }}</b> — {{ $ticket->service->name }}. Queue positions are for routine visits only; if your condition worsens, go to Emergency or tell any staff member.</div>
            <a href="{{ route('ticket', $ticket) }}" class="btn btn-ghost btn-sm">View token</a>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            @if(! $profile)
                <div class="pk-card p-4 mb-4 reveal">
                    <div class="ex-empty"><i class="bi bi-hospital d-block fs-4 mb-1"></i>The hospital hasn't published department or desk details here yet. Ask at the information desk at the main entrance.</div>
                </div>
            @endif

            @if($services->isNotEmpty())
                <div class="pk-card p-4 mb-4 reveal">
                    {!! $section('bi-ticket-detailed', '', 'Queues you can join here') !!}
                    <ul class="hx-list">
                        @foreach($services as $s)
                            <li>
                                <i class="bi bi-dot fs-4 text-primary" style="margin-top:-6px"></i>
                                <div class="flex-grow-1">{{ $s->name }}<div class="w">Counter {{ $s->counter }} · {{ $s->waitingCount() }} waiting</div></div>
                                <a href="{{ route('service', $s) }}" class="btn btn-ghost btn-sm align-self-center">View</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($profile)
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="pk-card p-4 h-100 reveal">
                            {!! $section('bi-diagram-3', 'violet', 'Departments') !!}
                            <ul class="hx-list">
                                @forelse($profile->departments ?? [] as $d)
                                    <li><div><b>{{ $d['name'] ?? '' }}</b>@if(! empty($d['where']))<div class="w">{{ $d['where'] }}</div>@endif @if(! empty($d['note']))<div class="w">{{ $d['note'] }}</div>@endif</div></li>
                                @empty
                                    <li class="pk-muted small">Not published yet.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pk-card p-4 h-100 reveal" style="--d:.06s">
                            {!! $section('bi-droplet-half', 'rose', 'Laboratory services') !!}
                            <ul class="hx-list">
                                @forelse($profile->lab_services ?? [] as $l)
                                    <li><div><b>{{ $l['name'] ?? '' }}</b>@if(! empty($l['where']))<div class="w">{{ $l['where'] }}</div>@endif @if(! empty($l['note']))<div class="w">{{ $l['note'] }}</div>@endif</div></li>
                                @empty
                                    <li class="pk-muted small">Not published yet.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pk-card p-4 h-100 reveal">
                            {!! $section('bi-person-raised-hand', 'green', 'Hospital-approved assistance desks') !!}
                            <ul class="hx-list">
                                @forelse($profile->assistance_desks ?? [] as $a)
                                    <li><div><b>{{ $a['name'] ?? '' }}</b>@if(! empty($a['where']))<div class="w"><i class="bi bi-geo-alt me-1"></i>{{ $a['where'] }}</div>@endif @if(! empty($a['note']))<div class="w">{{ $a['note'] }}</div>@endif</div></li>
                                @empty
                                    <li class="pk-muted small">Not published yet.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pk-card p-4 h-100 reveal" style="--d:.06s">
                            {!! $section('bi-universal-access', '', 'Accessibility') !!}
                            <ul class="hx-list">
                                @forelse($profile->accessibility ?? [] as $a)
                                    <li><i class="bi bi-check2 text-success"></i><div>{{ is_array($a) ? ($a['name'] ?? '') : $a }}</div></li>
                                @empty
                                    <li class="pk-muted small">Not published yet.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="pk-card p-4 mb-4 reveal">
                {!! $section('bi-telephone-fill', 'rose', 'Emergency contacts') !!}
                <ul class="hx-list">
                    @foreach($emergency as $n)
                        <li><i class="bi {{ $n['icon'] }} text-danger"></i><div class="flex-grow-1">{{ $n['label'] }}</div><a href="tel:{{ $n['number'] }}" class="fw-bold">{{ $n['number'] }}</a></li>
                    @endforeach
                    @foreach($profile?->emergency_contacts ?? [] as $c)
                        <li><i class="bi bi-hospital text-danger"></i><div class="flex-grow-1">{{ $c['label'] ?? '' }}@if(! empty($c['note']))<div class="w">{{ $c['note'] }}</div>@endif</div>@if(! empty($c['number']))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['number']) }}" class="fw-bold">{{ $c['number'] }}</a>@endif</li>
                    @endforeach
                </ul>
                <div class="small pk-muted mt-2">Verify numbers locally.</div>
            </div>

            <div class="pk-card p-4 mb-4 reveal" style="--d:.06s">
                {!! $section($pharmacyIcon, 'green', 'Nearby pharmacies') !!}
                <ul class="hx-list">
                    @forelse($pharmacies as $p)
                        <li>
                            <div class="flex-grow-1"><a href="{{ $p['url'] }}" class="fw-bold text-decoration-none">{{ $p['name'] }}</a>
                                <div class="w">{{ $p['walk_there'] }} min walk · ≈{{ number_format($p['distance_m']) }} m · {{ $p['hours_text'] }}</div></div>
                        </li>
                    @empty
                        <li class="pk-muted small">No pharmacies listed nearby yet.</li>
                    @endforelse
                </ul>
                <div class="small pk-muted mt-2">Listing only. Ask a pharmacist or doctor about medicines.</div>
            </div>

            <div class="pk-card p-4 reveal" style="--d:.1s">
                {!! $section('bi-signpost-split-fill', '', 'Nearby transport') !!}
                <ul class="hx-list">
                    @forelse($transport as $t)
                        <li><div class="flex-grow-1"><a href="{{ $t['url'] }}" class="fw-bold text-decoration-none">{{ $t['name'] }}</a><div class="w">{{ $t['walk_there'] }} min walk · {{ $t['subcategory'] ?? $t['category_label'] }}</div></div></li>
                    @empty
                        <li class="pk-muted small">No pickup points listed yet.</li>
                    @endforelse
                </ul>
                @if($rideUrl)
                    <a href="{{ $rideUrl }}" class="btn btn-ghost btn-sm w-100 mt-2"><i class="bi bi-car-front me-1"></i>Plan a ride</a>
                @endif
                <div class="small pk-muted mt-2">For a medical emergency, call an ambulance (102) rather than a taxi or ride app.</div>
            </div>
        </div>
    </div>

    <p class="small pk-muted mt-4 mb-0"><i class="bi bi-shield-check me-1"></i>This page lists hospital services and nearby places only. It does not give medical advice. Information shown is demo data — confirm with the hospital.</p>
</div>
@endsection
