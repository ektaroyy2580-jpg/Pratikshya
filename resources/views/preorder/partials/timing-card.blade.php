{{-- Timing check result. Needs $timing (array from TimingCheck::evaluate). --}}
@php
    $st = $timing['state'] ?? 'no_ticket';
    $icons = ['ok' => ['bi-check-circle-fill', 'green'], 'tight' => ['bi-hourglass-split', 'amber'], 'conflict' => ['bi-exclamation-triangle-fill', 'rose'], 'no_ticket' => ['bi-clock-history', '']];
    [$ic, $tone] = $icons[$st] ?? $icons['no_ticket'];
    $walk = (int) $timing['walk_minutes']; $prep = (int) $timing['prep_minutes']; $collect = (int) $timing['collect_minutes'];
    $atCafe = max($walk, $prep);
    $away = (int) $timing['away_minutes'];
    $turn = $timing['wait_low'];
    $scale = max(1, $away, (int) $turn) * 1.08;
    $pct = fn ($m) => round($m / $scale * 100, 2);
@endphp
<div class="po-timing {{ $st }}" aria-live="polite">
    <div class="head"><span class="pk-icon {{ $tone }}" style="width:36px;height:36px;font-size:1rem"><i class="bi {{ $ic }}"></i></span><span>{{ $timing['headline'] }}</span></div>
    @php $anchor = fn ($p) => $p > 70 ? 'right:'.max(0, 100 - $p).'%' : 'left:'.$p.'%'; @endphp
    <div class="po-lane-wrap">
        @if($turn !== null)<div class="po-lane-tag top" style="{{ $anchor(min(99, $pct($turn))) }}"><i class="bi bi-flag-fill me-1"></i>Earliest likely turn ~{{ $turn }} min</div>@endif
        <div class="po-lane" aria-hidden="true">
            <span class="seg-w" style="left:0;width:{{ $pct($walk) }}%">walk</span>
            <span class="seg-p" style="left:{{ $pct($walk) }}%;width:{{ $pct($atCafe - $walk) }}%">{{ $atCafe > $walk ? 'kitchen' : '' }}</span>
            <span class="seg-c" style="left:{{ $pct($atCafe) }}%;width:{{ $pct($collect) }}%"></span>
            <span class="seg-w" style="left:{{ $pct($atCafe + $collect) }}%;width:{{ $pct($walk) }}%">back</span>
            @if($turn !== null)<span class="turn" style="left:calc({{ min(99, $pct($turn)) }}% - 1px)"></span>@endif
        </div>
        <div class="po-lane-tag bottom" style="{{ $anchor($pct($away)) }}"><i class="bi bi-arrow-return-left me-1"></i>Back in ~{{ $away }} min</div>
        @if($pct($away) >= 14)<div class="po-lane-tag bottom" style="left:0">Now</div>@endif
    </div>
    <p class="small mb-0 mt-2" style="color:var(--pk-ink-2)">{{ $timing['explanation'] }}</p>
    <p class="small pk-muted mb-0 mt-2"><i class="bi bi-shield-check me-1"></i>Ordering food never changes, pauses or cancels your place in the queue.</p>
</div>
