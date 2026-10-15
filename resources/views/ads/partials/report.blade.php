{{-- Campaign report. Vars: $campaign, $stats (AdReports row), $daily (7 day series). Built only from recorded events. --}}
@php
    $maxDay = max(1, collect($daily)->max('impressions'));
@endphp
@if($campaign->is_demo)
    <div class="ads-demo-note mb-3"><i class="bi bi-info-circle"></i><div><strong>Demo data.</strong> This is a fictional demo advertiser. Numbers below come only from real views and clicks in this local app — nothing is simulated, and no money is charged.</div></div>
@endif
<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3"><div class="ads-kpi"><div class="l"><i class="bi bi-eye"></i>Impressions</div><div class="v">{{ number_format($stats['impressions']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="ads-kpi"><div class="l"><i class="bi bi-cursor"></i>Clicks</div><div class="v">{{ number_format($stats['clicks']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="ads-kpi"><div class="l"><i class="bi bi-percent"></i>CTR</div><div class="v">{{ $stats['ctr'] === null ? '—' : $stats['ctr'].'%' }}</div></div></div>
    <div class="col-6 col-md-3"><div class="ads-kpi"><div class="l"><i class="bi bi-gift"></i>Rewarded views</div><div class="v">{{ number_format($stats['reward_completions']) }}<small> / {{ $stats['reward_starts'] }} started</small></div></div></div>
</div>
<div class="row g-3">
    <div class="col-md-7">
        <div class="pk-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center"><h3 class="h6 mb-0">Impressions, last 7 days</h3><span class="small pk-muted">clicks in brackets</span></div>
            <div class="ads-bars" role="img" aria-label="Daily impressions for the last 7 days">
                @foreach($daily as $i => $d)
                    <div class="b" title="{{ $d['date']->format('D j M') }}: {{ $d['impressions'] }} impressions, {{ $d['clicks'] }} clicks">
                        <span>{{ $d['impressions'] }}@if($d['clicks']) ({{ $d['clicks'] }})@endif</span>
                        <div class="bar {{ $d['impressions'] ? '' : 'zero' }}" style="height: {{ $d['impressions'] ? max(4, round($d['impressions'] / $maxDay * 70)) : 3 }}%; --d: {{ $i * 0.05 }}s"></div>
                        <span>{{ $d['date']->format('D') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="pk-card p-3 h-100">
            <h3 class="h6">Spend estimate <span class="chip ms-1" style="font-size:.68rem">{{ $campaign->is_demo ? 'Demo' : 'Estimate' }}</span></h3>
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span class="fs-4 fw-bold">{{ \App\Services\Ads\AdSpend::format($stats['spend_paisa']) }}</span>
                <span class="small pk-muted">of {{ \App\Services\Ads\AdSpend::format($stats['budget_paisa']) }}</span>
            </div>
            <div class="progress ads-budget mb-2" role="progressbar" aria-label="Budget used" aria-valuenow="{{ $stats['budget_used_pct'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $stats['budget_used_pct'] }}%"></div></div>
            <p class="small pk-muted mb-0">Impressions × NPR {{ number_format((float) $campaign->cpm_rate, 2) }} CPM + clicks × NPR {{ number_format((float) $campaign->cpc_rate, 2) }} CPC. Daily cap NPR {{ number_format((float) $campaign->daily_budget, 2) }}. This build does not bill anyone.</p>
        </div>
    </div>
</div>
