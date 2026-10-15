@extends('layouts.app')
@section('title', 'Review: '.$campaign->name.' — Admin')

@push('head')
<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">
@endpush

@section('content')
<header class="pk-page-head">
    <div class="container">
        <a href="{{ route('ads.admin') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Ads admin</a>
        <div class="d-flex flex-wrap gap-2 align-items-center mt-2 mb-1">
            <span class="pk-status {{ $campaign->statusTone() }}">{{ $campaign->statusLabel() }}</span>
            @if($campaign->is_demo)<span class="ad-demo">Demo data</span>@endif
            @if($campaign->is_healthcare)<span class="chip" style="background:var(--pk-danger-tint);color:var(--pk-danger)"><i class="bi bi-heart-pulse"></i>Healthcare</span>@endif
        </div>
        <h1 class="h2 mb-1">{{ $campaign->name }}</h1>
        <div class="pk-muted small fw-semibold">{{ $campaign->advertiser->name }} ({{ $campaign->advertiser->status }}) · {{ $campaign->starts_on->format('j M') }} – {{ $campaign->ends_on->format('j M Y') }}</div>
    </div>
</header>

<div class="container py-4 py-md-5">
    @include('ads.partials.errors')
    @include('ads.partials.policy', ['class' => 'mb-4'])
    <div class="row g-4">
        <div class="col-lg-5 order-lg-2">
            <div class="ads-sticky d-flex flex-column gap-3">
                <div class="pk-card p-3 p-md-4">
                    <h2 class="h6 mb-3">Creative</h2>
                    @include('ads.partials.card', ['campaign' => $campaign, 'placement' => 'preview', 'href' => null, 'token' => null])
                    <dl class="row small mt-3 mb-0 gy-1">
                        <dt class="col-5 pk-muted fw-semibold">Placements</dt><dd class="col-7 mb-0">@foreach($campaign->placements as $p)<span class="ads-place-chip me-1 mb-1">{{ config('ads.placements.'.$p) }}</span>@endforeach</dd>
                        <dt class="col-5 pk-muted fw-semibold">Location</dt><dd class="col-7 mb-0">{{ $campaign->targetLocation?->name ?? 'All locations' }}</dd>
                        <dt class="col-5 pk-muted fw-semibold">Category</dt><dd class="col-7 mb-0">{{ $campaign->target_category ? config('ads.categories.'.$campaign->target_category) : 'Any' }}</dd>
                        <dt class="col-5 pk-muted fw-semibold">Destination</dt><dd class="col-7 mb-0 text-break"><code>{{ $campaign->cta_url }}</code></dd>
                        <dt class="col-5 pk-muted fw-semibold">Budget</dt><dd class="col-7 mb-0">NPR {{ number_format((float) $campaign->budget_total, 2) }} · {{ number_format((float) $campaign->daily_budget, 2) }}/day</dd>
                    </dl>
                </div>
                <div class="pk-card p-3 p-md-4">
                    <h2 class="h6 mb-3">Decision</h2>
                    <form method="post" action="{{ route('ads.admin.campaigns.review', $campaign) }}" class="d-grid gap-2">
                        @csrf
                        <label class="form-label small mb-0" for="note">Note to advertiser <span class="pk-muted fw-normal">(required to reject)</span></label>
                        <textarea class="form-control" id="note" name="note" rows="2" maxlength="255">{{ old('note') }}</textarea>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            @if(in_array($campaign->status, ['pending_review', 'paused']))<button class="btn btn-pk btn-sm" name="decision" value="approve"><i class="bi bi-check-lg me-1"></i>Approve</button>@endif
                            @if($campaign->status === 'pending_review')<button class="btn btn-ghost btn-sm text-danger" name="decision" value="reject"><i class="bi bi-x-lg me-1"></i>Reject</button>@endif
                            @if($campaign->status === 'active')<button class="btn btn-ghost btn-sm" name="decision" value="pause"><i class="bi bi-pause-fill me-1"></i>Pause</button>@endif
                            @if(in_array($campaign->status, ['active', 'paused', 'pending_review']))<button class="btn btn-ghost btn-sm" name="decision" value="end"><i class="bi bi-stop-fill me-1"></i>End</button>@endif
                        </div>
                        @unless(in_array($campaign->status, ['pending_review', 'paused', 'active']))<p class="small pk-muted mb-0">No decision needed for a {{ strtolower($campaign->statusLabel()) }} campaign.</p>@endunless
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7 order-lg-1">
            <h2 class="h5 mb-3">Report</h2>
            @include('ads.partials.report', ['campaign' => $campaign, 'stats' => $stats, 'daily' => $daily])
        </div>
    </div>
</div>
@endsection
