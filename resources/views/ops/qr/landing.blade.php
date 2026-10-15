@extends('layouts.app')
@section('title', $service->name.' · '.$service->location->name.' — प्रतीक्षा')

@push('head')
<meta name="robots" content="noindex">
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
<style>
    .ql-head h1 { font-size: clamp(1.8rem, 5vw, 2.6rem); line-height: 1.08; }
    .ql-meta { display: flex; flex-wrap: wrap; gap: .35rem 1.1rem; color: var(--pk-ink-2); font-weight: 600; font-size: .92rem; }
    .ql-meta i { color: var(--pk-muted); }
    .ql-tile { background: #fff; border: 1px solid var(--pk-line); border-radius: 16px; padding: 1rem 1.1rem; height: 100%; box-shadow: var(--pk-shadow-sm); }
    .ql-tile .l { font-size: .72rem; font-weight: 700; color: var(--pk-muted); text-transform: uppercase; letter-spacing: .05em; display: flex; align-items: center; gap: .4rem; }
    .ql-tile .v { font-size: clamp(1.45rem, 3.4vw, 1.9rem); font-weight: 800; letter-spacing: -.03em; line-height: 1.1; margin-top: .4rem; white-space: nowrap; }
    .ql-tile .v small { font-size: .85rem; font-weight: 700; color: var(--pk-muted); letter-spacing: 0; }
    .ql-tile.accent { background: var(--pk-grad); color: #fff; border-color: transparent; box-shadow: 0 14px 30px -16px rgba(47, 91, 234, .8); }
    .ql-tile.accent .l, .ql-tile.accent .v small { color: rgba(255, 255, 255, .82); }
    .doc-li { display: flex; align-items: center; gap: .7rem; padding: .7rem .85rem; border: 1px solid var(--pk-line); border-radius: 12px; background: #fff; font-weight: 600; font-size: .92rem; }
    .doc-li i { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--pk-tint); color: var(--pk-blue); flex-shrink: 0; }
    .ctr { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--pk-line); font-size: .9rem; }
    .ctr:last-child { border-bottom: 0; }
    .ctr b { font-weight: 800; }
    .join-card { position: relative; overflow: hidden; }
    @media (min-width: 992px) { .join-sticky { position: sticky; top: 88px; } }
    .sib { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem .2rem; border-bottom: 1px solid var(--pk-line); text-decoration: none; color: var(--pk-ink); font-weight: 600; font-size: .9rem; }
    .sib:last-child { border-bottom: 0; }
    .sib:hover { color: var(--pk-blue); }
</style>
@endpush

@section('content')
@php
    $loc = $service->location;
    $openCounters = $service->counters->where('is_open', true);
    $docs = $service->documents ?? [];
    $joinable = $service->is_active;
@endphp
<header class="pk-page-head ql-head">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 fade-up">
            <span class="chip"><i class="bi bi-qr-code-scan"></i> Scanned at the office</span>
            @if(! $service->is_active)
                <span class="pk-status done">Not taking tokens</span>
            @elseif($service->is_paused)
                <span class="pk-status waiting">Paused</span>
            @else
                <span class="chip" style="background:var(--pk-success-tint);color:var(--pk-success)"><span class="live-dot"></span> Live queue</span>
            @endif
        </div>
        <div class="pk-muted fw-semibold mb-1 fade-up" style="animation-delay:.04s"><i class="bi {{ $loc->icon }} me-1"></i>{{ $loc->name }} · {{ $loc->type }}</div>
        <h1 class="mb-2 fade-up" style="animation-delay:.08s">{{ $service->name }}</h1>
        <div class="ql-meta mb-4 fade-up" style="animation-delay:.12s">
            <span><i class="bi bi-geo-alt me-1"></i>{{ $loc->area }}</span>
            <span><i class="bi bi-door-open me-1"></i>Counter {{ $service->counter }}</span>
            <span><i class="bi bi-stopwatch me-1"></i>~{{ $service->avg_minutes }} min per person</span>
        </div>

        @include('ops.partials.announcement-banner', ['service' => $service, 'notices' => $notices])

        <div class="row g-2 g-md-3">
            <div class="col-6 col-lg-3 reveal"><div class="ql-tile"><div class="l"><span class="live-dot"></span> Now serving</div><div class="v">{{ $service->current_number > 0 ? $service->token($service->current_number) : '—' }}</div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.06s"><div class="ql-tile"><div class="l"><i class="bi bi-people"></i> In queue</div><div class="v">{{ $waiting }} <small>people</small></div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.12s"><div class="ql-tile accent"><div class="l"><i class="bi bi-hourglass-split"></i> If you join now</div><div class="v">{{ $estimate['low'] }}–{{ $estimate['high'] }} <small>min</small></div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.18s"><div class="ql-tile"><div class="l"><i class="bi bi-door-open"></i> Counters open</div><div class="v">{{ $openCounters->count() }}<small>/{{ $service->counters->count() }}</small></div></div></div>
        </div>
        <p class="small pk-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Estimate, not a guarantee. Confidence: <b>{{ $estimate['confidence'] }}</b>.</p>
    </div>
</header>

<div class="container py-4 py-lg-5">
    <div class="row g-4">
        <div class="col-lg-7 order-2 order-lg-1">
            <section class="pk-card p-4 reveal" aria-labelledby="docsHead">
                <h2 id="docsHead" class="h6 fw-bold mb-1">Bring these documents</h2>
                <p class="small pk-muted mb-3">{{ $service->requires_all_documents ? 'All documents are required to be served.' : 'Having them ready keeps your visit short.' }}</p>
                @if($docs)
                    <div class="d-grid gap-2">
                        @foreach($docs as $d)<div class="doc-li"><i class="bi bi-file-earmark-text"></i>{{ $d }}</div>@endforeach
                    </div>
                @else
                    <div class="small pk-muted">No documents listed for this service.</div>
                @endif
            </section>

            <section class="pk-card p-4 mt-4 reveal" aria-labelledby="ctrHead">
                <h2 id="ctrHead" class="h6 fw-bold mb-2">Counters</h2>
                @forelse($service->counters->sortBy('number') as $c)
                    <div class="ctr">
                        <span><b>Counter {{ $c->number }}</b>@if($c->label && $c->label !== 'Counter '.$c->number) <span class="pk-muted">· {{ $c->label }}</span>@endif</span>
                        <span class="pk-status {{ $c->is_open ? 'called' : 'done' }}" style="font-size:.74rem">{{ $c->is_open ? 'Open' : 'Closed' }}</span>
                    </div>
                @empty
                    <div class="small pk-muted">Counter {{ $service->counter }}</div>
                @endforelse
            </section>

            @if($siblings->isNotEmpty())
                <section class="pk-card p-4 mt-4 reveal" aria-labelledby="sibHead">
                    <h2 id="sibHead" class="h6 fw-bold mb-2">Other queues at {{ $loc->name }}</h2>
                    @foreach($siblings as $s)
                        <a href="{{ route('ops.qr.landing', $s->qr_code) }}" class="sib"><span class="text-truncate">{{ $s->name }}</span><span class="pk-muted small flex-shrink-0">{{ $s->waitingCount() }} waiting <i class="bi bi-chevron-right"></i></span></a>
                    @endforeach
                </section>
            @endif
        </div>

        <div class="col-lg-5 order-1 order-lg-2">
            <div class="join-sticky">
                <section class="pk-card p-4 join-card reveal" aria-labelledby="joinHead">
                    <h2 id="joinHead" class="h5 mb-1">Join this queue</h2>
                    @if(! $joinable)
                        <p class="pk-muted mb-3">This service isn’t taking new tokens right now. Please ask at the help desk.</p>
                        <button class="btn btn-pk w-100" disabled>Not available</button>
                    @else
                        <p class="pk-muted mb-3">Get a digital token, track your place live and do something useful while you wait.@if($service->is_paused) The queue is paused, so waits may be longer.@endif</p>
                        @auth
                            <a href="{{ route('service', $service) }}" class="btn btn-pk w-100 py-3"><i class="bi bi-ticket-perforated me-1"></i>Continue to join</a>
                            <p class="small pk-muted mt-2 mb-0 text-center">You’ll confirm your documents on the next step.</p>
                        @else
                            <a href="{{ route('service', $service) }}" class="btn btn-pk w-100 py-3"><i class="bi bi-box-arrow-in-right me-1"></i>Log in to join</a>
                            <p class="small pk-muted mt-2 mb-0 text-center">New here? <a href="{{ route('register.show') }}">Create a free account</a>, then scan again.</p>
                        @endauth
                    @endif
                    <hr class="my-4" style="border-color:var(--pk-line);opacity:1">
                    <div class="d-flex gap-2 small pk-muted"><i class="bi bi-shield-check"></i><span>This page shows public queue information only. Your token is issued only after you log in.</span></div>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
