@extends('platform.layouts.console')
@section('title', 'Analytics — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-graph-up"></i> Analytics</span>
        <h1>Platform analytics</h1>
        <p>Computed live from this installation's database. Waiting-time figures use the last 30 days.</p>
    </div>
    <span class="pf-demo"><i class="bi bi-database"></i> Local demo data — not real-world statistics</span>
</div>

@php
    $kpis = [
        ['Total users', number_format($o['users']), 'bi-people', '', null],
        ['Active queues', $o['active_queues'], 'bi-ticket-perforated', 'green', $o['paused_queues'] ? $o['paused_queues'].' paused' : 'none paused'],
        ['Waiting now', $o['waiting_now'], 'bi-hourglass-split', 'amber', $o['virtual_waiting'].' incl. walk-in tokens'],
        ['Avg wait (join → called)', $o['avg_wait'] !== null ? $o['avg_wait'].'<small> min</small>' : '—', 'bi-stopwatch', 'violet', $o['avg_wait_samples'] ? $o['avg_wait_samples'].' called tokens' : 'no called tokens yet'],
        ['Completed services', number_format($o['completed']), 'bi-check2-circle', 'green', $o['completed_today'].' today'],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach($kpis as $i => [$label, $value, $icon, $tone, $hint])
        <div class="col-6 col-lg reveal" style="--d: {{ $i * .04 }}s">
            <div class="pk-card pf-kpi">
                <span class="pk-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                <div><div class="v">{!! $value !!}</div><div class="l">{{ $label }}</div>@if($hint)<div class="small pk-muted mt-1" style="font-size:.72rem">{{ $hint }}</div>@endif</div>
            </div>
        </div>
    @endforeach
</div>

<section class="pk-card pf-panel mb-3 reveal" aria-labelledby="peakH">
    <div class="pf-panel-head"><h2 id="peakH"><i class="bi bi-bar-chart text-primary"></i> Peak periods</h2><span class="small pk-muted">Tokens issued by hour of day · last 30 days</span></div>
    <div class="pf-panel-body">@include('platform.partials.hour-chart', ['hours' => $o['hours'], 'chartId' => 'peakChart'])</div>
</section>

<section class="pk-card pf-panel mb-3 reveal" aria-labelledby="delH">
    <div class="pf-panel-head"><h2 id="delH"><i class="bi bi-clock-history text-primary"></i> Service delays</h2><span class="small pk-muted">Estimate at join vs actual wait</span></div>
    @if($o['delays']->isEmpty())
        <div class="pf-empty"><p class="pk-muted small mb-0">No services yet.</p></div>
    @else
        <div class="table-responsive">
            <table class="table pf-tbl pf-stack">
                <thead><tr><th>Service</th><th>Status</th><th>Waiting</th><th>Est. wait</th><th>Actual wait</th><th>Service time</th></tr></thead>
                <tbody>
                @foreach($o['delays'] as $d)
                    @php $late = $d['actual_wait'] !== null && $d['est_wait'] !== null ? round($d['actual_wait'] - $d['est_wait'], 1) : null; @endphp
                    <tr>
                        <td><div class="fw-bold">{{ $d['service']->name }}</div><div class="sub">{{ $d['service']->location?->name }}</div></td>
                        <td data-label="Status">
                            @if(! $d['active'])<span class="pf-badge muted">Inactive</span>
                            @elseif($d['paused'])<span class="pf-badge warn"><i class="bi bi-pause-fill"></i> Paused</span>
                            @else<span class="pf-badge ok">Live</span>@endif
                        </td>
                        <td data-label="Waiting">{{ $d['waiting'] }}</td>
                        <td data-label="Est. wait">{{ $d['est_wait'] !== null ? $d['est_wait'].' min' : '—' }}</td>
                        <td data-label="Actual wait">
                            @if($d['actual_wait'] === null)<span class="pk-muted">No data</span>
                            @else {{ $d['actual_wait'] }} min
                                @if($late !== null && $late > 2)<span class="pf-badge bad ms-1"><i class="bi bi-arrow-up"></i> {{ $late }}</span>
                                @elseif($late !== null && $late < -2)<span class="pf-badge ok ms-1"><i class="bi bi-arrow-down"></i> {{ abs($late) }}</span>@endif
                            @endif
                            <div class="sub">{{ $d['samples'] }} {{ \Illuminate\Support\Str::plural('sample', $d['samples']) }}</div>
                        </td>
                        <td data-label="Service time">{{ $d['actual_service'] !== null ? $d['actual_service'].' min' : '—' }} <span class="sub">/ {{ $d['avg_minutes'] }} planned</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section aria-labelledby="modH" class="reveal">
    <h2 id="modH" class="h6 mb-2 d-flex align-items-center gap-2"><i class="bi bi-grid-3x3-gap text-primary"></i> Across modules</h2>
    <div class="row g-3">
        @foreach($modules as $m)
            <div class="col-6 col-xl-4">
                <div class="pk-card pf-kpi {{ $m['installed'] ? '' : 'off' }}">
                    <span class="pk-icon {{ $m['installed'] ? 'violet' : '' }}"><i class="bi {{ $m['icon'] }}"></i></span>
                    <div>
                        <div class="v">{{ $m['installed'] ? ($m['value'] !== null ? number_format($m['value']) : '—') : 'Module not installed' }}</div>
                        <div class="l">{{ $m['label'] }}</div>
                        <div class="small pk-muted mt-1" style="font-size:.72rem">{{ $m['hint'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection
