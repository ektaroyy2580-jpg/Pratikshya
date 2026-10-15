{{--
    Admin dashboard card for Dalal AI. Usage: @include('integrity.partials.admin-card')
    Admin-only; renders nothing for anyone else.
--}}
@php
    $igUser = auth()->user();
    $igShow = $igUser && $igUser->isAdmin();
    if ($igShow) {
        $igOpen = \App\Models\FraudAlert::whereIn('status', \App\Models\FraudAlert::OPEN_STATUSES)->selectRaw('severity, count(*) c')->groupBy('severity')->pluck('c', 'severity');
        $igLatest = \App\Models\FraudAlert::with('subjectService')->whereIn('status', \App\Models\FraudAlert::OPEN_STATUSES)
            ->orderByDesc('risk_score')->latest('last_seen_at')->take(3)->get();
    }
@endphp
@if($igShow)
    @once
        @push('head')
            <link rel="stylesheet" href="{{ asset('css/modules/integrity.css') }}?v={{ @filemtime(public_path('css/modules/integrity.css')) }}">
        @endpush
    @endonce
    <section class="pk-card ig-widget h-100" aria-labelledby="igAdminCardHead">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <h3 id="igAdminCardHead" class="d-flex align-items-center gap-2">
                <span class="pk-icon violet" style="width:32px;height:32px;font-size:.9rem"><i class="bi bi-shield-shaded"></i></span> Dalal AI · integrity
            </h3>
            <a href="{{ route('integrity.admin.index', ['status' => 'active']) }}" class="small fw-semibold text-decoration-none">Review <i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="ig-sev high"><i class="bi bi-exclamation-octagon"></i>{{ (int) ($igOpen['high'] ?? 0) }} high</span>
            <span class="ig-sev medium"><i class="bi bi-exclamation-triangle"></i>{{ (int) ($igOpen['medium'] ?? 0) }} medium</span>
            <span class="ig-sev low"><i class="bi bi-info-circle"></i>{{ (int) ($igOpen['low'] ?? 0) }} low</span>
        </div>
        @forelse($igLatest as $a)
            <a href="{{ route('integrity.admin.show', $a) }}" class="d-flex align-items-center gap-2 py-2 border-top text-decoration-none" style="color:inherit">
                <span class="ig-ring {{ $a->severity }}" style="--v: {{ $a->risk_score }}; width:36px; height:36px"><span style="font-size:.72rem">{{ $a->risk_score }}</span></span>
                <span class="ig-min-0 flex-grow-1">
                    <span class="d-block fw-semibold small text-truncate">{{ $a->title() }}</span>
                    <span class="d-block ig-note text-truncate">{{ $a->subjectService?->name ?? 'Platform-wide' }} · {{ $a->last_seen_at?->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <div class="ig-note"><i class="bi bi-check2-circle text-success me-1"></i>No open alerts. Signals are reviewed by people, never acted on automatically.</div>
        @endforelse
    </section>
@endif
