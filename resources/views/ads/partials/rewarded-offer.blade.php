{{--
    Optional rewarded-ad offer for the game hub / post-game screen.
    @include('ads.partials.rewarded-offer', ['placement' => 'post_game_reward', 'return' => request()->getRequestUri()])
    Renders nothing for guests, when rewarded ads are off, when today's limit is used, or when no sponsor is available.
--}}
@php
    $rwOffer = null;
    try {
        if (auth()->check()) {
            $rwSvc = app(\App\Services\Ads\RewardedAdService::class);
            $rwPlacement = in_array($placement ?? 'game_hub', \App\Services\Ads\RewardedAdService::PLACEMENTS, true) ? ($placement ?? 'game_hub') : 'game_hub';
            if ($rwSvc->enabled() && $rwSvc->remainingToday(auth()->user()) > 0) {
                $rwOffer = $rwSvc->campaignFor(auth()->user(), $rwPlacement);
            }
        }
    } catch (\Throwable $e) {
        report($e);
        $rwOffer = null;
    }
@endphp
@if($rwOffer)
    @once
        @push('head')<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">@endpush
    @endonce
    <div class="pk-card p-3 d-flex align-items-center gap-3 flex-wrap {{ $class ?? '' }}">
        <span class="pk-icon violet"><i class="bi bi-play-btn"></i></span>
        <div class="flex-grow-1" style="min-width: 180px">
            <div class="fw-bold">Want an extra round?</div>
            <div class="small pk-muted">Optional: watch a 15-second <strong>sponsored</strong> demo video from {{ $rwOffer->advertiser?->name }}. Never affects your queue position.</div>
        </div>
        <a class="btn btn-ghost btn-sm" href="{{ route('ads.rewarded', array_filter(['placement' => $rwPlacement, 'return' => $return ?? null])) }}"><i class="bi bi-gift me-1"></i>Watch &amp; unlock</a>
    </div>
@endif
