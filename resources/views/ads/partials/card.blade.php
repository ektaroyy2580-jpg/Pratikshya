{{--
    Sponsored card markup. Used by the live slot and by portal/admin previews.
    Vars: $campaign (AdCampaign), $placement, optional $href (signed click URL; null = preview), $token (impression token),
          $compact (bool), $why (explanation text).
--}}
@php
    [$adA, $adB] = $campaign->themeColors();
    $adImg = $campaign->imageUrl();
    $adHref = $href ?? null;
    $adExternal = $adHref && ! str_starts_with((string) $campaign->cta_url, '/');
    $adWhy = $why ?? 'Shown because of the place and page you are viewing. It is not based on your activity or personal data.';
    $adName = $campaign->advertiser?->name ?? 'Sponsor';
@endphp
@once
    @push('head')<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">@endpush
@endonce
<aside class="ad-slot" aria-label="Sponsored content from {{ $adName }}"
       @if(! empty($token)) data-ad-token="{{ $token }}" data-ad-endpoint="{{ route('ads.impression') }}" @endif>
    <div class="ad-card {{ ! empty($compact) ? 'compact' : '' }}">
        <div class="ad-media {{ $adImg ? 'has-img' : '' }}" style="--ad-a: {{ $adA }}; --ad-b: {{ $adB }};" aria-hidden="true">
            @if($adImg)
                <img src="{{ $adImg }}" alt="" loading="lazy" decoding="async">
            @else
                <span class="ad-glyph"><i class="bi {{ $campaign->categoryIcon() }}"></i></span>
                <span class="ad-mark">{{ $adName }}</span>
            @endif
        </div>
        <div class="ad-body">
            <div class="ad-meta">
                <span class="ad-label"><i class="bi bi-megaphone-fill" aria-hidden="true"></i>Sponsored</span>
                @if($campaign->is_demo)<span class="ad-demo" title="Fictional demo advertiser">Demo ad</span>@endif
                <span class="ad-advertiser">{{ $adName }}</span>
            </div>
            <h3 class="ad-headline">{{ $campaign->headline }}</h3>
            @if($campaign->body)<p class="ad-text">{{ $campaign->body }}</p>@endif
            @if($campaign->is_healthcare)
                <p class="ad-disclosure"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span><strong>Healthcare ad.</strong> {{ $campaign->healthcare_disclosure ?: 'Paid promotion. Not medical advice.' }}</span></p>
            @endif
            <div class="ad-actions">
                @if($adHref)
                    <a class="ad-cta" href="{{ $adHref }}" rel="sponsored nofollow noopener" @if($adExternal) target="_blank" @endif>
                        {{ $campaign->cta_label }} <i class="bi {{ $adExternal ? 'bi-box-arrow-up-right' : 'bi-arrow-right' }}" aria-hidden="true"></i>
                        @if($adExternal)<span class="visually-hidden">(opens an external site)</span>@endif
                    </a>
                @else
                    <span class="ad-cta" aria-disabled="true">{{ $campaign->cta_label }} <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                @endif
                <details class="ad-why">
                    <summary><i class="bi bi-question-circle" aria-hidden="true"></i>Why this ad?</summary>
                    <p>{{ $adWhy }} Sponsored content never changes your place in a queue.</p>
                </details>
            </div>
        </div>
    </div>
</aside>
