@extends('layouts.app')
@section('title', $campaign->name.' — Advertiser portal')

@push('head')
<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">
@endpush

@section('content')
<header class="pk-page-head">
    <div class="container">
        <a href="{{ route('ads.portal') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Advertiser portal</a>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mt-2">
            <div>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                    <span class="pk-status {{ $campaign->statusTone() }}">{{ $campaign->statusLabel() }}</span>
                    @if($campaign->is_demo)<span class="ad-demo">Demo data</span>@endif
                    @if($campaign->is_healthcare)<span class="chip" style="background:var(--pk-danger-tint);color:var(--pk-danger)"><i class="bi bi-heart-pulse"></i>Healthcare</span>@endif
                </div>
                <h1 class="h2 mb-1">{{ $campaign->name }}</h1>
                <div class="pk-muted small fw-semibold">{{ $campaign->advertiser->name }} · {{ $campaign->starts_on->format('j M') }} – {{ $campaign->ends_on->format('j M Y') }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($canEdit)
                    <a href="{{ route('ads.campaigns.edit', $campaign) }}" class="btn btn-ghost btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                    @if(in_array($campaign->status, ['draft', 'rejected']))
                        <form method="post" action="{{ route('ads.campaigns.submit', $campaign) }}">@csrf<button class="btn btn-pk btn-sm"><i class="bi bi-send-check me-1"></i>Submit for review</button></form>
                    @elseif($campaign->status === 'active')
                        <form method="post" action="{{ route('ads.campaigns.pause', $campaign) }}">@csrf<button class="btn btn-ghost btn-sm"><i class="bi bi-pause-fill me-1"></i>Pause</button></form>
                    @elseif($campaign->status === 'paused' && $campaign->approved_at)
                        <form method="post" action="{{ route('ads.campaigns.resume', $campaign) }}">@csrf<button class="btn btn-pk btn-sm"><i class="bi bi-play-fill me-1"></i>Resume</button></form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    @include('ads.partials.errors')
    @if($campaign->status === 'rejected' && $campaign->review_note)
        <div class="alert alert-danger d-flex gap-2"><i class="bi bi-x-octagon mt-1"></i><div><strong>Not approved:</strong> {{ $campaign->review_note }} Edit the campaign and submit it again.</div></div>
    @elseif($campaign->status === 'pending_review')
        <div class="alert alert-warning d-flex gap-2"><i class="bi bi-hourglass-split mt-1"></i><div>Waiting for admin review. It will start running on approval (within its dates).</div></div>
    @elseif($campaign->status === 'draft')
        <div class="ads-demo-note mb-3"><i class="bi bi-pencil-square"></i><div>Draft — not visible to anyone. Submit it for review when ready.</div></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5 order-lg-2">
            <div class="ads-sticky d-flex flex-column gap-3">
                <div class="pk-card p-3 p-md-4 reveal">
                    <h2 class="h6 mb-3">Creative preview</h2>
                    @include('ads.partials.card', ['campaign' => $campaign, 'placement' => 'preview', 'href' => null, 'token' => null])
                    <dl class="row small mt-3 mb-0 gy-1">
                        <dt class="col-5 pk-muted fw-semibold">Placements</dt><dd class="col-7 mb-0">@foreach($campaign->placements as $p)<span class="ads-place-chip me-1 mb-1">{{ config('ads.placements.'.$p) }}</span>@endforeach</dd>
                        <dt class="col-5 pk-muted fw-semibold">Location</dt><dd class="col-7 mb-0">{{ $campaign->targetLocation?->name ?? 'All locations' }}</dd>
                        <dt class="col-5 pk-muted fw-semibold">Category</dt><dd class="col-7 mb-0">{{ $campaign->target_category ? config('ads.categories.'.$campaign->target_category) : 'Any' }}</dd>
                        <dt class="col-5 pk-muted fw-semibold">Rewarded video</dt><dd class="col-7 mb-0">{{ $campaign->allows_rewarded ? 'Offered' : 'No' }}</dd>
                        <dt class="col-5 pk-muted fw-semibold">Link</dt><dd class="col-7 mb-0 text-break"><code>{{ $campaign->cta_url }}</code></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7 order-lg-1 reveal">
            <h2 class="h5 mb-3">Performance</h2>
            @include('ads.partials.report', ['campaign' => $campaign, 'stats' => $stats, 'daily' => $daily])
            @include('ads.partials.policy', ['class' => 'mt-4'])
        </div>
    </div>
</div>
@endsection
