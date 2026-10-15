{{-- Photo with a fade-in and a CSS fallback underneath. Needs: $url (nullable). Optional: $alt, $art (preorder food-art key), $icon (bootstrap icon), $class, $eager. --}}
<div class="cf-photo {{ $class ?? '' }}">
    <div class="cf-fallback" aria-hidden="true">
        @if(! empty($art))
            @include('preorder.partials.food-art', ['art' => $art, 'label' => ''])
        @else
            <i class="bi {{ $icon ?? 'bi-cup-hot-fill' }}"></i>
        @endif
    </div>
    @if(! empty($url))
        <img class="cf-img" src="{{ $url }}" alt="{{ $alt ?? '' }}" @if(empty($eager)) loading="lazy" @else fetchpriority="high" @endif decoding="async" onload="this.classList.add('is-in')" onerror="this.remove()">
    @endif
</div>
