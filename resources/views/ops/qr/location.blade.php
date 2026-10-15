@extends('layouts.app')
@section('title', $location->name.' — प्रतीक्षा')

@push('head')
<meta name="robots" content="noindex">
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
<style>
    .svc-pick { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .3rem 1rem; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--pk-line); text-decoration: none; color: var(--pk-ink); transition: background .2s; }
    .svc-pick:last-child { border-bottom: 0; }
    .svc-pick:hover { background: #fbfcff; color: var(--pk-ink); }
    .svc-pick .pfx { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; background: var(--pk-tint); color: var(--pk-blue); }
    .svc-pick .w { text-align: right; font-weight: 800; white-space: nowrap; }
    .svc-pick .w small { display: block; font-size: .74rem; color: var(--pk-muted); font-weight: 600; }
</style>
@endpush

@section('content')
<header class="pk-page-head">
    <div class="container">
        <span class="chip mb-3 fade-up"><i class="bi bi-qr-code-scan"></i> Scanned at the office</span>
        <h1 class="mb-1 fade-up" style="animation-delay:.05s">{{ $location->name }}</h1>
        <div class="pk-muted fw-semibold fade-up" style="animation-delay:.1s"><i class="bi bi-geo-alt me-1"></i>{{ $location->area }} · {{ $location->type }}</div>
    </div>
</header>
<div class="container py-4 py-lg-5" style="max-width: 760px">
    <h2 class="h6 fw-bold mb-3">Choose your service</h2>
    <section class="pk-card overflow-hidden reveal" aria-label="Services">
        @forelse($services as $s)
            @php $est = $s->estimate(); @endphp
            <a href="{{ route('ops.qr.landing', $s->qr_code) }}" class="svc-pick">
                <span class="pfx">{{ $s->prefix }}</span>
                <span class="min-w-0"><span class="fw-bold d-block text-truncate">{{ $s->name }}</span><span class="small pk-muted">Counter {{ $s->counter }} · {{ $s->waitingCount() }} waiting @unless($s->is_active)· not taking tokens @endunless</span></span>
                <span class="w">{{ $est['low'] }}–{{ $est['high'] }} min<small>est. wait</small></span>
            </a>
        @empty
            <div class="ops-empty"><p class="pk-muted mb-0">No services listed here yet.</p></div>
        @endforelse
    </section>
    <p class="small pk-muted mt-3"><i class="bi bi-shield-check me-1"></i>Public queue information only. Joining a queue requires logging in.</p>
</div>
@endsection
