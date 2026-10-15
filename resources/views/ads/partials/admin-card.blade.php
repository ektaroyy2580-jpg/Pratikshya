{{-- Admin dashboard card for the advertising module. No variables required. Safe if tables are missing. --}}
@php
    $adsCard = null;
    try {
        $adsCard = [
            'live' => \App\Models\AdCampaign::live()->count(),
            'pending' => \App\Models\AdCampaign::where('status', 'pending_review')->count(),
            'advertisers' => \App\Models\Advertiser::where('status', 'pending')->count(),
            'impressions' => \App\Models\AdEvent::where('type', 'impression')->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    } catch (\Throwable $e) {
        report($e);
    }
@endphp
@if($adsCard)
<div class="pk-card p-3 p-md-4 h-100">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="pk-icon amber"><i class="bi bi-megaphone"></i></span>
        <div>
            <div class="fw-bold">Advertising</div>
            <div class="small pk-muted">Sponsored placements &amp; campaign review</div>
        </div>
    </div>
    <div class="row g-2 text-center small mb-3">
        <div class="col-3"><div class="fw-bold fs-5">{{ $adsCard['live'] }}</div><div class="pk-muted">Live</div></div>
        <div class="col-3"><div class="fw-bold fs-5 {{ $adsCard['pending'] ? 'text-warning' : '' }}">{{ $adsCard['pending'] }}</div><div class="pk-muted">To review</div></div>
        <div class="col-3"><div class="fw-bold fs-5">{{ $adsCard['advertisers'] }}</div><div class="pk-muted">New biz</div></div>
        <div class="col-3"><div class="fw-bold fs-5">{{ number_format($adsCard['impressions']) }}</div><div class="pk-muted">Views 7d</div></div>
    </div>
    <a href="{{ route('ads.admin') }}" class="btn btn-ghost btn-sm w-100">Open ads admin <i class="bi bi-arrow-right"></i></a>
</div>
@endif
