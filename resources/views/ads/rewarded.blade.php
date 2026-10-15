@extends('layouts.app')
@section('title', 'Watch & unlock — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $ready = $enabled && $campaign && $remaining > 0;
    [$ca, $cb] = $campaign ? $campaign->themeColors() : config('ads.themes.slate');
    $sponsor = $campaign?->advertiser?->name;
@endphp
<header class="pk-page-head">
    <div class="container">
        @if($returnTo)
            <a href="{{ $returnTo }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Back</a>
        @else
            <a href="{{ route('profile') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Profile</a>
        @endif
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mt-2">
            <div>
                <span class="pk-eyebrow"><i class="bi bi-gift"></i>Optional · Rewarded</span>
                <h1 class="h2 mb-1 mt-1">Watch &amp; unlock</h1>
                <p class="pk-muted mb-0">Watch a short <strong>sponsored</strong> demo video to unlock a small reward. Entirely voluntary.</p>
            </div>
            <span class="chip"><i class="bi bi-calendar-check"></i>{{ $remaining }} of {{ $limit }} left today</span>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="pk-card p-3 p-md-4 reveal">
                @if(! $enabled)
                    <div class="text-center py-5">
                        <span class="pk-icon mb-3"><i class="bi bi-pause-circle"></i></span>
                        <h2 class="h5">Rewarded ads are switched off</h2>
                        <p class="pk-muted mb-0">Everything else in the app works as normal.</p>
                    </div>
                @elseif($remaining <= 0)
                    <div class="text-center py-5">
                        <span class="pk-icon green mb-3"><i class="bi bi-check2-all"></i></span>
                        <h2 class="h5">You've used today's rewarded videos</h2>
                        <p class="pk-muted mb-0">The limit keeps rewards fair. Come back tomorrow.</p>
                    </div>
                @elseif(! $campaign)
                    <div class="text-center py-5">
                        <span class="pk-icon mb-3"><i class="bi bi-camera-video-off"></i></span>
                        <h2 class="h5">No sponsored video available right now</h2>
                        <p class="pk-muted mb-0">There is no active sponsor you haven't watched today. Try again later.</p>
                    </div>
                @else
                    <div class="rw-stage idle mb-3" id="rwStage" style="--ad-a: {{ $ca }}; --ad-b: {{ $cb }};" role="img" aria-label="Demo sponsored video from {{ $sponsor }}">
                        <span class="rw-flag"><i class="bi bi-megaphone-fill"></i>Demo sponsored video</span>
                        <span class="rw-sponsor">Sponsored · {{ $sponsor }}</span>
                        <svg class="rw-rings" viewBox="0 0 400 225" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                            <circle cx="200" cy="112" r="70" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="1.5"/>
                            <circle cx="200" cy="112" r="70" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="1.5"/>
                            <circle cx="200" cy="112" r="70" fill="none" stroke="rgba(255,255,255,.18)" stroke-width="1.5"/>
                        </svg>
                        <div class="rw-scene">
                            <div class="rw-line l0">
                                <span class="rw-glyph"><i class="bi bi-play-fill"></i></span>
                                <h3>{{ $sponsor }}</h3>
                                <p>15-second sponsored demo · press play below</p>
                            </div>
                            <div class="rw-line l1">
                                <span class="rw-glyph"><i class="bi {{ $campaign->categoryIcon() }}"></i></span>
                                <h3>{{ $sponsor }}</h3>
                                <p>{{ config('ads.categories.'.$campaign->categoryKey()) }}</p>
                            </div>
                            <div class="rw-line l2">
                                <h3>{{ $campaign->headline }}</h3>
                                @if($campaign->body)<p>{{ $campaign->body }}</p>@endif
                            </div>
                            <div class="rw-line l3">
                                <span class="rw-glyph"><i class="bi bi-gift"></i></span>
                                <h3>Thanks for watching</h3>
                                <p>Your reward is being verified…</p>
                            </div>
                        </div>
                        <span class="rw-time" id="rwTime" aria-hidden="true">0:{{ str_pad($seconds, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="rw-bar" aria-hidden="true"><span id="rwBar"></span></div>
                    </div>
                    @if($campaign->is_healthcare)
                        <p class="ad-disclosure mb-3"><i class="bi bi-info-circle-fill"></i><span><strong>Healthcare ad.</strong> {{ $campaign->healthcare_disclosure }}</span></p>
                    @endif

                    <form id="rwForm" class="mb-0" data-start="{{ route('ads.rewarded.start') }}" data-placement="{{ $placement }}" data-campaign="{{ $campaign->id }}" data-seconds="{{ $seconds }}">
                        <fieldset id="rwChoices">
                            <legend class="form-label mb-2">Choose your reward</legend>
                            <div class="row g-2 mb-3">
                                @foreach($rewards as $key => $r)
                                    <div class="col-sm-4">
                                        <label class="rw-choice h-100">
                                            <input type="radio" name="reward_type" value="{{ $key }}" @checked($loop->first)>
                                            <span class="rw-opt">
                                                <span class="pk-icon {{ ['extra_round' => '', 'cosmetic' => 'violet', 'points' => 'amber'][$key] ?? '' }}" style="width:38px;height:38px;font-size:1.05rem"><i class="bi {{ $r['icon'] }}"></i></span>
                                                <span><span class="t d-block">{{ $key === 'points' ? ($r['amount'] ?? 10).' WaitPoints' : $r['label'] }}</span>
                                                <span class="s">{{ ['extra_round' => 'One more game round', 'cosmetic' => 'Shown on your profile', 'points' => 'Added to your balance'][$key] ?? '' }}</span></span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="submit" class="btn btn-pk" id="rwStart"><i class="bi bi-play-fill me-1"></i>Watch {{ $seconds }}-sec sponsored video</button>
                            <button type="button" class="btn btn-pk d-none" id="rwClaim" disabled><i class="bi bi-gift me-1"></i>Claim reward</button>
                            @if($returnTo)<a href="{{ $returnTo }}" class="btn btn-ghost" id="rwSkip">No thanks</a>@endif
                        </div>
                        <div class="mt-3 small fw-semibold" id="rwMsg" role="status" aria-live="polite"></div>
                    </form>
                    <div class="alert alert-success rw-success d-none mt-3 mb-0 d-flex align-items-center gap-2" id="rwDone" role="status">
                        <i class="bi bi-check-circle-fill fs-5"></i>
                        <div class="flex-grow-1"><strong id="rwDoneText">Reward unlocked.</strong><div class="small">Verified by the server. Your queue position is unchanged.</div></div>
                        @if($returnTo)<a href="{{ $returnTo }}" class="btn btn-sm btn-pk">Continue</a>@else<a href="{{ route('ads.rewarded') }}" class="btn btn-sm btn-ghost">Done</a>@endif
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ads-sticky d-flex flex-column gap-3">
                <div class="pk-card p-3 p-md-4 reveal" style="--d:.06s">
                    <h2 class="h6 mb-3">Your rewards</h2>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="pk-muted fw-semibold"><i class="bi bi-arrow-repeat me-1"></i>Extra rounds</span><strong id="rwExtra">{{ $extraRounds }}</strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="pk-muted fw-semibold"><i class="bi bi-stars me-1"></i>WaitPoints</span><strong>{{ number_format($balance) }}</strong></div>
                    <div class="pt-2">
                        <div class="pk-muted fw-semibold small mb-2"><i class="bi bi-patch-check me-1"></i>Supporter badges</div>
                        @forelse($badges as $b)
                            <span class="chip me-1 mb-1"><i class="bi bi-patch-check-fill"></i>{{ $b['sponsor'] }}</span>
                        @empty
                            <div class="small pk-muted">None yet.</div>
                        @endforelse
                    </div>
                </div>
                <div class="pk-card p-3 p-md-4 reveal" style="--d:.12s">
                    <h2 class="h6 mb-2">How this works</h2>
                    <ul class="small pk-muted ps-3 mb-0 d-grid gap-1">
                        <li>This is a <strong>demo</strong> ad: a local animation, not a real ad network. No revenue is generated.</li>
                        <li>The server starts the timer when you press play and only accepts completion after {{ $seconds }} seconds.</li>
                        <li>Up to {{ $limit }} rewarded videos a day, once per sponsor per day.</li>
                        <li>Ads and rewards <strong>never</strong> change your queue position, document checks or staff decisions.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/ads-rewarded.js') }}?v={{ @filemtime(public_path('js/modules/ads-rewarded.js')) }}"></script>
@endpush
