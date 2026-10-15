@extends('layouts.app')
@section('title', 'Advertising — Admin')

@push('head')
<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $pendingAdv = $advertisers->where('status', 'pending')->count();
    $tabs = [
        'review' => ['Review queue', 'bi-inbox', $pending->count()],
        'campaigns' => ['All campaigns', 'bi-collection', null],
        'advertisers' => ['Advertisers', 'bi-shop', $pendingAdv],
        'placements' => ['Placements', 'bi-grid-1x2', null],
    ];
@endphp
<header class="pk-page-head">
    <div class="container">
        <a href="{{ route('admin') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Admin</a>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mt-2">
            <div>
                <span class="pk-eyebrow"><i class="bi bi-megaphone"></i>Advertising</span>
                <h1 class="h2 mb-0 mt-1">Campaigns &amp; placements</h1>
            </div>
            <span class="chip"><span class="live-dot"></span>{{ $campaigns->filter->isLive()->count() }} live now</span>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    @include('ads.partials.errors')
    @include('ads.partials.policy', ['class' => 'mb-4'])

    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-lg-3 reveal"><div class="ads-kpi"><div class="l"><i class="bi bi-eye"></i>Impressions</div><div class="v">{{ number_format($totals['impressions']) }}</div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.05s"><div class="ads-kpi"><div class="l"><i class="bi bi-cursor"></i>Clicks · CTR</div><div class="v">{{ number_format($totals['clicks']) }}<small> · {{ $totals['ctr'] === null ? '—' : $totals['ctr'].'%' }}</small></div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.1s"><div class="ads-kpi"><div class="l"><i class="bi bi-gift"></i>Rewarded views</div><div class="v">{{ number_format($totals['reward_completions']) }}</div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.15s"><div class="ads-kpi"><div class="l"><i class="bi bi-cash-coin"></i>Spend estimate</div><div class="v" style="font-size:1.35rem">{{ \App\Services\Ads\AdSpend::format($totals['spend_paisa']) }}</div></div></div>
    </div>
    @if($campaigns->contains('is_demo', true))
        <div class="ads-demo-note mb-4"><i class="bi bi-info-circle"></i><div><strong>Demo data.</strong> Campaigns marked "Demo" belong to fictional advertisers. Totals count only real interactions in this local app; no revenue is earned or billed.</div></div>
    @endif

    <nav class="ads-tabs mb-3" aria-label="Advertising sections">
        @foreach($tabs as $key => [$label, $icon, $count])
            <a href="{{ route('ads.admin', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif><i class="bi {{ $icon }} me-1"></i>{{ $label }}@if($count)<span class="count">{{ $count }}</span>@endif</a>
        @endforeach
    </nav>

    @if($tab === 'review' || $tab === 'campaigns')
        @php $list = $tab === 'review' ? $pending : $campaigns; @endphp
        <div class="pk-card fade-up">
            @forelse($list as $c)
                @php [$ca, $cb] = $c->themeColors(); $s = $stats[$c->id]; @endphp
                <div class="ads-row flex-wrap">
                    <div class="ads-thumb" style="--ad-a: {{ $ca }}; --ad-b: {{ $cb }}">@if($c->imageUrl())<img src="{{ $c->imageUrl() }}" alt="">@else<i class="bi {{ $c->categoryIcon() }}"></i>@endif</div>
                    <div class="flex-grow-1" style="min-width: 200px">
                        <a href="{{ route('ads.admin.campaigns.show', $c) }}" class="ads-name">{{ $c->name }}</a>
                        <div class="ads-sub">{{ $c->advertiser->name }} · {{ $c->starts_on->format('j M') }} – {{ $c->ends_on->format('j M') }} · {{ count($c->placements) }} placement{{ count($c->placements) === 1 ? '' : 's' }}@if($c->submitted_at && $c->status === 'pending_review') · submitted {{ $c->submitted_at->diffForHumans() }}@endif</div>
                    </div>
                    <span class="pk-status {{ $c->statusTone() }}">{{ $c->statusLabel() }}</span>
                    @if($c->is_demo)<span class="ad-demo">Demo</span>@endif
                    @if($c->is_healthcare)<span class="chip" style="background:var(--pk-danger-tint);color:var(--pk-danger)"><i class="bi bi-heart-pulse"></i></span>@endif
                    <div class="d-flex gap-3">
                        <div class="ads-metric"><div class="n">{{ number_format($s['impressions']) }}</div><div class="k">Views</div></div>
                        <div class="ads-metric"><div class="n">{{ $s['ctr'] === null ? '—' : $s['ctr'].'%' }}</div><div class="k">CTR</div></div>
                    </div>
                    @if($c->status === 'pending_review')
                        <form method="post" action="{{ route('ads.admin.campaigns.review', $c) }}" class="d-flex gap-1">
                            @csrf
                            <button class="btn btn-pk btn-sm" name="decision" value="approve" @disabled(! $c->advertiser->isApproved()) title="{{ $c->advertiser->isApproved() ? 'Approve' : 'Approve the advertiser first' }}"><i class="bi bi-check-lg"></i><span class="d-none d-sm-inline ms-1">Approve</span></button>
                            <a class="btn btn-ghost btn-sm" href="{{ route('ads.admin.campaigns.show', $c) }}">Review</a>
                        </form>
                    @else
                        <a class="btn btn-ghost btn-sm" href="{{ route('ads.admin.campaigns.show', $c) }}" aria-label="Open {{ $c->name }}"><i class="bi bi-chevron-right"></i></a>
                    @endif
                </div>
            @empty
                <div class="text-center p-5">
                    <span class="pk-icon green mb-3"><i class="bi bi-inbox"></i></span>
                    <h2 class="h6">{{ $tab === 'review' ? 'Nothing to review' : 'No campaigns yet' }}</h2>
                    <p class="small pk-muted mb-0">{{ $tab === 'review' ? 'Submitted campaigns will appear here.' : 'Advertisers create campaigns from their portal.' }}</p>
                </div>
            @endforelse
        </div>
    @elseif($tab === 'advertisers')
        <div class="pk-card fade-up">
            @forelse($advertisers as $a)
                <div class="ads-row flex-wrap">
                    <span class="pk-icon {{ ['pending' => 'amber', 'approved' => 'green', 'suspended' => 'rose'][$a->status] ?? '' }}"><i class="bi bi-shop"></i></span>
                    <div class="flex-grow-1" style="min-width: 200px">
                        <div class="ads-name">{{ $a->name }} @if($a->is_demo)<span class="ad-demo ms-1">Demo</span>@endif</div>
                        <div class="ads-sub">{{ config('ads.categories.'.$a->category) }} · {{ $a->campaigns_count }} campaign{{ $a->campaigns_count === 1 ? '' : 's' }}@if($a->contact_email) · {{ $a->contact_email }}@endif</div>
                    </div>
                    <span class="pk-status {{ ['approved' => 'called', 'pending' => 'waiting'][$a->status] ?? 'done' }}">{{ ucfirst($a->status) }}</span>
                    <form method="post" action="{{ route('ads.admin.advertisers.status', $a) }}" class="d-flex gap-1">
                        @csrf
                        @if($a->status !== 'approved')<button class="btn btn-pk btn-sm" name="status" value="approved"><i class="bi bi-check-lg me-1"></i>Approve</button>@endif
                        @if($a->status !== 'suspended')<button class="btn btn-ghost btn-sm text-danger" name="status" value="suspended"><i class="bi bi-slash-circle me-1"></i>Suspend</button>@endif
                    </form>
                </div>
            @empty
                <div class="text-center p-5 pk-muted">No advertisers yet.</div>
            @endforelse
        </div>
    @else
        <div class="row g-3 fade-up">
            @foreach($placements as $key => $p)
                <div class="col-md-6 col-lg-4">
                    <div class="pk-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h2 class="h6 mb-0">{{ $p['label'] }}</h2>
                            <span class="chip">{{ $p['live'] }} live</span>
                        </div>
                        <div class="d-flex gap-4 small">
                            <div><div class="fw-bold fs-5">{{ number_format($p['impressions']) }}</div><div class="pk-muted">Views (7d)</div></div>
                            <div><div class="fw-bold fs-5">{{ number_format($p['clicks']) }}</div><div class="pk-muted">Clicks</div></div>
                            <div><div class="fw-bold fs-5">{{ $p['ctr'] === null ? '—' : $p['ctr'].'%' }}</div><div class="pk-muted">CTR</div></div>
                        </div>
                        <code class="d-block small mt-3 mb-3 text-break pk-muted">placement: '{{ $key }}'</code>
                        @php $previewAd = $campaigns->first(fn ($c) => $c->isLive() && $c->hasPlacement($key)); @endphp
                        @if($previewAd)
                            <div class="small pk-muted fw-semibold mb-2">Preview (not counted as an impression)</div>
                            @include('ads.partials.card', ['campaign' => $previewAd, 'placement' => $key, 'href' => null, 'token' => null, 'compact' => true])
                        @else
                            <div class="ads-demo-note"><i class="bi bi-dash-circle"></i><div>No live campaign — the slot renders nothing here.</div></div>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="col-12"><p class="small pk-muted mb-0"><i class="bi bi-info-circle me-1"></i>Slots never render on screens with critical queue alerts (called / now serving), and healthcare ads are not allowed on queue pages.</p></div>
        </div>
    @endif
</div>
@endsection
