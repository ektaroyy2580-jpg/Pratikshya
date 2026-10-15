@extends('layouts.app')
@section('title', 'Advertiser portal — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">
@endpush

@section('content')
<header class="pk-page-head">
    <div class="container">
        <span class="pk-eyebrow"><i class="bi bi-megaphone"></i>Advertiser portal</span>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mt-1">
            <div>
                <h1 class="h2 mb-1">{{ $advertisers->count() === 1 ? $advertisers->first()->name : 'Your businesses' }}</h1>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @foreach($advertisers as $a)
                        <span class="pk-status {{ ['approved' => 'called', 'pending' => 'waiting'][$a->status] ?? 'done' }}">{{ $advertisers->count() > 1 ? $a->name.' · ' : '' }}{{ ucfirst($a->status) }}</span>
                        @if($a->is_demo)<span class="ad-demo">Demo advertiser</span>@endif
                    @endforeach
                </div>
            </div>
            <a href="{{ route('ads.campaigns.create') }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i>New campaign</a>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    @include('ads.partials.errors')
    @if($advertisers->contains('status', 'pending'))
        <div class="alert alert-warning d-flex gap-2 fade-up"><i class="bi bi-hourglass-split mt-1"></i><div>Your business is <strong>awaiting admin approval</strong>. You can build campaigns as drafts; they can be submitted once approved.</div></div>
    @endif
    @if($advertisers->contains('status', 'suspended'))
        <div class="alert alert-danger d-flex gap-2"><i class="bi bi-slash-circle mt-1"></i><div>A business on your account is <strong>suspended</strong>. Its campaigns are not shown to anyone.</div></div>
    @endif

    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-lg-3 reveal"><div class="ads-kpi"><div class="l"><i class="bi bi-broadcast"></i>Live campaigns</div><div class="v">{{ $campaigns->where('status', 'active')->count() }}<small> / {{ $campaigns->count() }}</small></div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.05s"><div class="ads-kpi"><div class="l"><i class="bi bi-eye"></i>Impressions</div><div class="v">{{ number_format($totals['impressions']) }}</div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.1s"><div class="ads-kpi"><div class="l"><i class="bi bi-cursor"></i>Clicks · CTR</div><div class="v">{{ number_format($totals['clicks']) }}<small> · {{ $totals['ctr'] === null ? '—' : $totals['ctr'].'%' }}</small></div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.15s"><div class="ads-kpi"><div class="l"><i class="bi bi-cash-coin"></i>Spend estimate</div><div class="v" style="font-size:1.35rem">{{ \App\Services\Ads\AdSpend::format($totals['spend_paisa']) }}</div></div></div>
    </div>
    @if($hasDemo)
        <div class="ads-demo-note mb-4"><i class="bi bi-info-circle"></i><div><strong>Demo data.</strong> Demo campaigns belong to fictional businesses. Figures count only real views and clicks in this local app; spend is an estimate and nobody is billed.</div></div>
    @endif

    <div class="pk-card reveal">
        <div class="d-flex align-items-center justify-content-between p-3 p-md-4 pb-2">
            <h2 class="h5 mb-0">Campaigns</h2>
            <span class="small pk-muted">{{ $campaigns->count() }} total</span>
        </div>
        @forelse($campaigns as $c)
            @php [$ca, $cb] = $c->themeColors(); $s = $stats[$c->id]; @endphp
            <div class="ads-row flex-wrap">
                <div class="ads-thumb" style="--ad-a: {{ $ca }}; --ad-b: {{ $cb }}">@if($c->imageUrl())<img src="{{ $c->imageUrl() }}" alt="">@else<i class="bi {{ $c->categoryIcon() }}"></i>@endif</div>
                <div class="flex-grow-1" style="min-width: 180px">
                    <a href="{{ route('ads.campaigns.show', $c) }}" class="ads-name">{{ $c->name }}</a>
                    <div class="ads-sub">{{ $c->starts_on->format('j M') }} – {{ $c->ends_on->format('j M Y') }} · {{ collect($c->placements)->map(fn ($p) => config('ads.placements.'.$p))->implode(', ') }}</div>
                </div>
                <span class="pk-status {{ $c->statusTone() }}">{{ $c->statusLabel() }}</span>
                @if($c->is_demo)<span class="ad-demo">Demo</span>@endif
                <div class="d-flex gap-3">
                    <div class="ads-metric"><div class="n">{{ number_format($s['impressions']) }}</div><div class="k">Views</div></div>
                    <div class="ads-metric"><div class="n">{{ $s['ctr'] === null ? '—' : $s['ctr'].'%' }}</div><div class="k">CTR</div></div>
                </div>
                <a href="{{ route('ads.campaigns.show', $c) }}" class="btn btn-ghost btn-sm" aria-label="Open {{ $c->name }}"><i class="bi bi-chevron-right"></i></a>
            </div>
        @empty
            <div class="text-center p-5">
                <span class="pk-icon mb-3"><i class="bi bi-megaphone"></i></span>
                <h3 class="h6">No campaigns yet</h3>
                <p class="pk-muted small mb-3">Create a sponsored card for people waiting nearby.</p>
                <a href="{{ route('ads.campaigns.create') }}" class="btn btn-pk btn-sm">Create your first campaign</a>
            </div>
        @endforelse
    </div>

    @include('ads.partials.policy', ['class' => 'mt-4'])
</div>
@endsection
