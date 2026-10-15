{{-- One neutral recommendation. Needs $item (RecommendationService::present) and $remaining (int). --}}
@php
    $rem = max(1, (int) ($remaining ?? 1));
    $pw = fn ($m) => max(0, min(100, round($m / $rem * 100, 1)));
@endphp
<article class="ex-rec reveal" style="--c: {{ $item['color'] }}; --d: {{ ($loop->index ?? 0) * .06 }}s" data-place="{{ $item['id'] }}" tabindex="0" role="button" aria-label="Show {{ $item['name'] }} on the map">
    @if(! empty($item['image']))
        <span class="ico photo" aria-hidden="true"><i class="bi {{ $item['icon'] }}"></i><img src="{{ $item['image'] }}" alt="" loading="lazy" decoding="async" onload="this.classList.add('is-in')" onerror="this.remove()"></span>
    @else
        <span class="ico" aria-hidden="true"><i class="bi {{ $item['icon'] }}"></i></span>
    @endif
    <div class="flex-grow-1 min-w-0 pe-4">
        <div class="nm">{{ $item['name'] }}</div>
        <div class="meta">
            <span><i class="bi bi-person-walking"></i> {{ $item['walk_there'] }} min</span>
            <span><i class="bi bi-hourglass"></i> {{ $item['visit'] }} min visit</span>
            <span><i class="bi bi-star-fill" style="color:#f5b301"></i> {{ number_format($item['rating'], 1) }}</span>
            <span class="text-success"><i class="bi bi-check2-circle"></i> {{ $item['spare'] }} min spare</span>
        </div>
        <div class="ex-timebar" aria-hidden="true">
            <span class="w" style="width: {{ $pw($item['walk_there']) }}%"></span>
            <span class="v" style="width: {{ $pw($item['visit']) }}%"></span>
            <span class="w" style="width: {{ $pw($item['walk_back']) }}%"></span>
            <span class="b" style="width: {{ $pw((int) config('explore.safety_buffer_minutes', 8)) }}%"></span>
        </div>
        <div class="why">{{ $item['explanation'] }}</div>
        @if(! empty($item['is_cafe']))<a href="{{ $item['url'] }}" class="ex-open mt-2">Open café <i class="bi bi-arrow-right"></i></a>@endif
    </div>
    <button type="button" class="x" data-dismiss-place="{{ $item['id'] }}" aria-label="Dismiss {{ $item['name'] }}" title="Not interested"><i class="bi bi-x-lg"></i></button>
</article>
