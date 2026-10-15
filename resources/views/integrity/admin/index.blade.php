@extends('layouts.app')
@section('title', 'Dalal AI review — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/integrity.css') }}?v={{ @filemtime(public_path('css/modules/integrity.css')) }}">
@endpush

@section('content')
@php
    $sevIcons = ['low' => 'bi-info-circle', 'medium' => 'bi-exclamation-triangle', 'high' => 'bi-exclamation-octagon'];
    $statusLabels = ['active' => 'Needs attention', 'open' => 'Open', 'reviewing' => 'Reviewing', 'escalated' => 'Escalated', 'confirmed' => 'Confirmed', 'dismissed' => 'Dismissed'];
    $activeCount = collect(\App\Models\FraudAlert::OPEN_STATUSES)->sum(fn ($s) => (int) ($counts[$s] ?? 0));
    $current = $filters['status'] ?? '';
    $kpis = [
        ['Open', (int) ($counts['open'] ?? 0), 'bi-inbox', ''],
        ['Escalated', (int) ($counts['escalated'] ?? 0), 'bi-arrow-up-right-circle', 'rose'],
        ['High severity open', (int) ($severityOpen['high'] ?? 0), 'bi-exclamation-octagon', 'amber'],
        ['Signals · 24h', $signals24h, 'bi-activity', 'violet'],
    ];
@endphp
<header class="pk-page-head ig-head">
    <div class="container">
        <a href="{{ route('admin') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Admin</a>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2">
            <div class="fade-up">
                <span class="pk-eyebrow mb-2"><i class="bi bi-shield-shaded"></i> Dalal AI · queue integrity</span>
                <h1 class="mb-1">Integrity review</h1>
                <div class="pk-muted" style="max-width: 600px">Rules and anomaly checks flag patterns for a person to review. Scores are internal signals, never verdicts, and nothing here is visible to visitors.</div>
            </div>
            <form method="POST" action="{{ route('integrity.admin.scan') }}" class="fade-up" style="animation-delay:.05s" data-ig-once>
                @csrf
                <button type="submit" class="btn btn-ghost" data-busy="Scanning…"><i class="bi bi-radar me-1"></i>Run scan now</button>
            </form>
        </div>
    </div>
</header>

<div class="container py-4 py-lg-5">
    <div class="row g-3 mb-4">
        @foreach($kpis as $i => [$label, $val, $icon, $cls])
            <div class="col-6 col-lg-3 reveal" style="--d:{{ $i * 0.04 }}s">
                <div class="pk-card ig-kpi">
                    <span class="pk-icon {{ $cls }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                    <div><div class="v">{{ number_format($val) }}</div><div class="l">{{ $label }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="pk-card ig-filters mb-3 reveal" aria-label="Filter alerts">
        <div class="ig-pills" role="group" aria-label="Status">
            <a href="{{ route('integrity.admin.index', array_filter(['severity' => $filters['severity'] ?? null, 'rule' => $filters['rule'] ?? null])) }}" class="ig-pill {{ $current === '' ? 'active' : '' }}">All <span class="n">{{ $counts->sum() }}</span></a>
            @foreach($statusLabels as $key => $label)
                @php $n = $key === 'active' ? $activeCount : (int) ($counts[$key] ?? 0); @endphp
                <a href="{{ route('integrity.admin.index', array_filter(['status' => $key, 'severity' => $filters['severity'] ?? null, 'rule' => $filters['rule'] ?? null])) }}"
                   class="ig-pill {{ $current === $key ? 'active' : '' }}" @if($current === $key) aria-current="true" @endif>{{ $label }} <span class="n">{{ $n }}</span></a>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2 ms-lg-auto">
            @if($current)<input type="hidden" name="status" value="{{ $current }}">@endif
            <label class="visually-hidden" for="fSev">Severity</label>
            <select id="fSev" name="severity" class="form-select" onchange="this.form.submit()">
                <option value="">Any severity</option>
                @foreach(\App\Models\FraudAlert::SEVERITIES as $s)<option value="{{ $s }}" @selected(($filters['severity'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <label class="visually-hidden" for="fRule">Rule</label>
            <select id="fRule" name="rule" class="form-select" onchange="this.form.submit()" style="max-width: 260px">
                <option value="">Any rule</option>
                @foreach(\App\Models\FraudAlert::RULE_TITLES as $k => $t)<option value="{{ $k }}" @selected(($filters['rule'] ?? '') === $k)>{{ $t }}</option>@endforeach
            </select>
            <noscript><button class="btn btn-ghost btn-sm">Apply</button></noscript>
        </div>
    </form>

    <section class="pk-card overflow-hidden reveal" style="--d:.06s" aria-label="Alerts">
        @forelse($alerts as $a)
            <a href="{{ route('integrity.admin.show', $a) }}" class="ig-alert">
                <span class="ig-ring {{ $a->severity }}" style="--v: {{ $a->risk_score }}" role="img" aria-label="Risk score {{ $a->risk_score }} of 100"><span>{{ $a->risk_score }}</span></span>
                <span class="ig-min-0">
                    <span class="t d-block text-truncate">{{ $a->title() }}</span>
                    <span class="m d-block text-truncate">
                        @if($a->subjectService)<i class="bi bi-building me-1"></i>{{ $a->subjectService->name }} · {{ $a->subjectService->location?->name }}@else<i class="bi bi-globe2 me-1"></i>Platform-wide @endif
                        @if($a->subject_user_id)<span class="mx-1">·</span>Account #{{ $a->subject_user_id }}@endif
                    </span>
                    <span class="m d-block"><i class="bi bi-clock me-1"></i>Last seen {{ $a->last_seen_at?->diffForHumans() ?? $a->created_at->diffForHumans() }} · {{ $a->signal_count }} {{ \Illuminate\Support\Str::plural('signal', $a->signal_count) }}@if(! empty($a->evidence['demo'])) · <span class="chip ig-muted-chip py-0 px-2">Demo</span>@endif</span>
                </span>
                <span class="ig-right d-flex flex-column align-items-end gap-1">
                    <span class="ig-sev {{ $a->severity }}"><i class="bi {{ $sevIcons[$a->severity] ?? 'bi-dot' }}"></i>{{ ucfirst($a->severity) }}</span>
                    <span class="ig-st {{ $a->status }}">{{ ucfirst($a->status) }}</span>
                    <i class="bi bi-chevron-right chev d-none d-sm-inline" aria-hidden="true"></i>
                </span>
            </a>
        @empty
            <div class="ig-empty">
                <span class="pk-icon green"><i class="bi bi-shield-check"></i></span>
                <div class="fw-bold mb-1">No alerts match</div>
                <div class="ig-note">Try another filter, or run a scan. A quiet list is the normal state.</div>
            </div>
        @endforelse
    </section>
    @if($alerts->hasPages())
        <div class="mt-3 d-flex justify-content-center">{{ $alerts->links('pagination::bootstrap-5') }}</div>
    @endif

    <div class="ig-principles mt-4 reveal">
        <i class="bi bi-info-circle me-1"></i><b>Review principles.</b> Shared networks, devices, surnames or locations are never treated as proof (families, offices and hospitals share them).
        Dismiss false positives and record why. No public lists, no automatic penalties, no biometrics. Signals are deleted after {{ config('integrity.retention_days') }} days.
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/integrity.js') }}?v={{ @filemtime(public_path('js/modules/integrity.js')) }}"></script>
@endpush
