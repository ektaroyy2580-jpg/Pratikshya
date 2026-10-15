{{--
  Platform: staff analytics card for the signed-in staff member's approved locations.
  Self-contained: no variables required (optional $user). Safe if the user has no locations.
--}}
@php
    $pfStaffUser = $user ?? auth()->user();
    $pfLocIds = $pfStaffUser ? $pfStaffUser->staffLocationIds() : [];
    $pfStats = null;
    if ($pfLocIds) {
        try { $pfStats = app(\App\Services\Platform\PlatformAnalytics::class)->overview(array_map('intval', $pfLocIds)); } catch (\Throwable $e) { report($e); }
    }
@endphp
<section class="pk-card p-3 p-md-4" aria-label="Your locations today">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <h2 class="h6 mb-0 d-flex align-items-center gap-2"><i class="bi bi-graph-up text-primary"></i> Your locations at a glance</h2>
        <span class="small pk-muted" style="font-size:.72rem">Local demo data</span>
    </div>
    @if(! $pfStats)
        <p class="small pk-muted mb-0">{{ $pfLocIds ? 'Analytics are unavailable right now.' : 'You are not assigned to any location yet.' }}</p>
    @else
        <div class="row g-2 text-center">
            @foreach([['Waiting', $pfStats['waiting_now'], 'bi-hourglass-split'], ['Avg wait', $pfStats['avg_wait'] !== null ? $pfStats['avg_wait'].'m' : '—', 'bi-stopwatch'], ['Done today', $pfStats['completed_today'], 'bi-check2-circle'], ['Paused', $pfStats['paused_queues'], 'bi-pause-circle']] as [$l, $v, $ic])
                <div class="col-6 col-md-3">
                    <div class="rounded-3 p-2" style="background:var(--pk-bg)">
                        <div class="fw-bold fs-5" style="font-variant-numeric:tabular-nums">{{ $v }}</div>
                        <div class="small pk-muted" style="font-size:.72rem"><i class="bi {{ $ic }} me-1"></i>{{ $l }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        @php $pfBusy = array_sum($pfStats['hours']) ? \Carbon\Carbon::createFromTime(array_search(max($pfStats['hours']), $pfStats['hours'], true))->format('ga') : null; @endphp
        @if($pfBusy)<p class="small pk-muted mt-3 mb-0"><i class="bi bi-bar-chart me-1"></i>Busiest hour over 30 days: <strong class="text-body">{{ $pfBusy }}</strong>. Plan counter cover around it.</p>@endif
    @endif
</section>
