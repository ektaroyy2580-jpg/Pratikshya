@extends('layouts.app')
@section('title', 'Dashboard — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/platform.css') }}?v={{ filemtime(public_path('css/modules/platform.css')) }}" rel="stylesheet">
<style>
    .db-hello h1 { font-size: clamp(1.6rem, 3.4vw, 2.2rem); }
    .tk-card { padding: 1.25rem; display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 1rem 1.1rem; align-items: center; }
    .tk-num { min-width: 92px; padding: .7rem .9rem; border-radius: 16px; text-align: center; background: var(--pk-grad); color: #fff; box-shadow: 0 14px 28px -16px rgba(47, 91, 234, .9); }
    .tk-num .t { font-size: 1.7rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
    .tk-num .l { font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .8; margin-top: .3rem; }
    .tk-stats { display: flex; flex-wrap: wrap; gap: .4rem 1.2rem; font-size: .84rem; color: var(--pk-ink-2); }
    .tk-stats b { font-variant-numeric: tabular-nums; }
    .tk-card .progress { height: 6px; grid-column: 1 / -1; }
    .tk-card.flash { animation: pk-rise .5s var(--pk-ease); }
    .qa { padding: 1rem; display: flex; align-items: center; gap: .85rem; text-decoration: none; color: inherit; height: 100%; }
    .qa .pk-icon { width: 40px; height: 40px; font-size: 1.05rem; }
    .qa .t { font-weight: 700; font-size: .92rem; } .qa .d { font-size: .76rem; color: var(--pk-muted); }
    .pt-card { padding: 1.25rem; color: #fff; border: 0; background: radial-gradient(300px 160px at 100% 0%, rgba(112, 70, 232, .55), transparent 70%), #0f1535; }
    .pt-card .v { font-size: 2.2rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
    .pt-card a { color: #c9d4ff; }
    .n-row { display: flex; align-items: flex-start; gap: .8rem; padding: .8rem 1.15rem; }
    .n-row + .n-row { border-top: 1px solid #f0f1f7; }
    .n-row .pk-icon { width: 34px; height: 34px; font-size: .9rem; border-radius: 10px; }
    .n-row.unread .m { font-weight: 700; }
    @media (max-width: 575.98px) { .tk-card { grid-template-columns: minmax(0, 1fr); } .tk-num { justify-self: start; } }
</style>
@endpush

@section('content')
@php
    $statusMap = ['waiting' => ['waiting', 'Waiting'], 'called' => ['called', 'Called — go to counter'], 'serving' => ['serving', 'Being served']];
    $hour = (int) now()->format('G');
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<header class="pk-page-head pf-head db-hello">
    <div class="container d-flex flex-wrap justify-content-between align-items-end gap-3 fade-up">
        <div>
            <span class="pk-eyebrow"><i class="bi bi-sun"></i> {{ $greet }}</span>
            <h1>Namaste, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h1>
            <div class="pk-muted small">{{ $active->count() ? 'You have '.$active->count().' active '.\Illuminate\Support\Str::plural('token', $active->count()).'.' : 'No active tokens right now.' }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($isManager)<a href="{{ route('platform.manage.index') }}" class="btn btn-ghost btn-sm"><i class="bi bi-grid-1x2 me-1"></i> Management console</a>@endif
            @if(Route::has('find'))<a href="{{ route('find') }}" class="btn btn-pk btn-sm"><i class="bi bi-plus-lg me-1"></i> Join a queue</a>@endif
        </div>
    </div>
</header>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            {{-- Active tokens --}}
            <section aria-labelledby="tkH">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 id="tkH" class="h5 mb-0 d-flex align-items-center gap-2">Your tokens @if($active->isNotEmpty())<span class="live-dot" aria-hidden="true"></span><span class="small pk-muted fw-semibold">Live</span>@endif</h2>
                </div>
                @forelse($active as $t)
                    @php [$cls, $lbl] = $statusMap[$t->status] ?? ['done', ucfirst($t->status)]; $st = $t->stateArray(); @endphp
                    <a href="{{ route('ticket', $t) }}" class="pk-card lift tk-card text-decoration-none text-reset mb-3 reveal" data-state-url="{{ route('ticket.state', $t) }}" data-token="{{ $t->token() }}" data-status="{{ $t->status }}">
                        <div class="tk-num"><div class="t">{{ $t->token() }}</div><div class="l">Token</div></div>
                        <div class="min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-bold text-truncate">{{ $t->service->name }}</span>
                                <span class="pk-status {{ $cls }}" data-f="status">{{ $lbl }}</span>
                                @if($t->service->is_paused)<span class="pf-badge warn"><i class="bi bi-pause-fill"></i> Queue paused</span>@endif
                            </div>
                            <div class="small pk-muted mb-2 text-truncate"><i class="bi bi-geo-alt me-1"></i>{{ $t->service->location?->name }}</div>
                            <div class="tk-stats">
                                <span><i class="bi bi-people me-1"></i><b data-f="ahead">{{ $st['ahead'] }}</b> ahead</span>
                                <span><i class="bi bi-clock me-1"></i><b data-f="wait">{{ $t->status === 'waiting' ? $st['wait_low'].'–'.$st['wait_high'].' min' : 'Now' }}</b></span>
                                <span><i class="bi bi-door-open me-1"></i>Counter <b data-f="counter">{{ $st['counter'] }}</b></span>
                                <span><i class="bi bi-megaphone me-1"></i>Now serving <b data-f="current">{{ $st['current_token'] }}</b></span>
                            </div>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Queue progress" aria-valuenow="{{ $st['progress'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" data-f="progress" style="width: {{ $st['progress'] }}%"></div></div>
                    </a>
                @empty
                    <div class="pk-card pf-empty reveal">
                        <span class="pk-icon mb-2"><i class="bi bi-ticket-perforated"></i></span>
                        <h3 class="h6 mb-1">No active tokens</h3>
                        <p class="pk-muted small mb-3">Find an office, check your documents and join from anywhere.</p>
                        @if(Route::has('find'))<a href="{{ route('find') }}" class="btn btn-pk btn-sm">Find a service</a>@endif
                    </div>
                @endforelse
            </section>

            {{-- Quick actions --}}
            <section aria-labelledby="qaH">
                <h2 id="qaH" class="h5 mb-2">Quick actions</h2>
                <div class="row g-3">
                    @foreach($shortcuts as $i => [$label, $desc, $icon, $route, $tone])
                        <div class="col-6 col-md-4 reveal" style="--d: {{ $i * .04 }}s">
                            <a href="{{ route($route) }}" class="pk-card lift qa">
                                <span class="pk-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                                <div class="min-w-0"><div class="t">{{ $label }}</div><div class="d">{{ $desc }}</div></div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Other modules' widgets (only when installed; failures never break the dashboard) --}}
            @if($partials->isNotEmpty())
                <section aria-label="Continue where you left off" class="d-grid gap-3">
                    @foreach($partials as $partial)
                        @php
                            try { $html = view($partial, ['ticket' => $active->first(), 'user' => $user])->render(); }
                            catch (\Throwable $e) { report($e); $html = ''; }
                        @endphp
                        {!! $html !!}
                    @endforeach
                </section>
            @endif

            @if($recentTickets->isNotEmpty())
                <section class="pk-card pf-panel reveal" aria-labelledby="hisH">
                    <div class="pf-panel-head"><h2 id="hisH"><i class="bi bi-clock-history text-primary"></i> Recent visits</h2>@if(Route::has('profile'))<a class="small fw-semibold" href="{{ route('profile') }}">All history</a>@endif</div>
                    @foreach($recentTickets as $t)
                        <div class="n-row">
                            <span class="pk-icon {{ $t->status === 'completed' ? 'green' : '' }}"><i class="bi {{ $t->status === 'completed' ? 'bi-check2' : 'bi-dash' }}"></i></span>
                            <div class="min-w-0 flex-grow-1"><div class="fw-semibold small text-truncate">{{ $t->token() }} · {{ $t->service->name }}</div><div class="small pk-muted">{{ ucfirst($t->status) }} · {{ $t->created_at?->diffForHumans() }}</div></div>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>

        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <section class="pk-card pt-card reveal" aria-label="Rewards">
                <div class="small fw-bold text-uppercase" style="letter-spacing:.08em;opacity:.75"><i class="bi bi-stars me-1"></i> WaitPoints</div>
                <div class="v mt-2">{{ number_format($balance) }}</div>
                <div class="small mt-1" style="opacity:.8">Never affects queue order.</div>
                <a href="{{ route('platform.rewards') }}" class="small fw-bold d-inline-block mt-3">Open wallet <i class="bi bi-arrow-right"></i></a>
            </section>

            <section class="pk-card pf-panel reveal" style="--d:.05s" aria-labelledby="ntH">
                <div class="pf-panel-head"><h2 id="ntH"><i class="bi bi-bell text-primary"></i> Notifications @if($unread)<span class="badge bg-danger" id="dbUnread">{{ $unread }}</span>@endif</h2><a class="small fw-semibold" href="{{ route('platform.notifications') }}">See all</a></div>
                @forelse($notifications as $n)
                    <div class="n-row {{ $n['read'] ? '' : 'unread' }}">
                        <span class="pk-icon {{ $n['tone'] }}" aria-hidden="true"><i class="bi {{ $n['icon'] }}"></i></span>
                        <div class="min-w-0"><div class="m small">{{ $n['message'] }}</div><div class="small pk-muted">{{ $n['when']?->diffForHumans() }}</div></div>
                    </div>
                @empty
                    <div class="pf-empty py-4"><p class="pk-muted small mb-0">No notifications yet.</p></div>
                @endforelse
            </section>

            <section class="pk-card p-3 reveal" style="--d:.1s" aria-label="Privacy shortcut">
                <div class="d-flex gap-3 align-items-center">
                    <span class="pk-icon green"><i class="bi bi-shield-lock"></i></span>
                    <div class="min-w-0"><div class="fw-bold small">Your privacy, your choice</div><div class="small pk-muted">Notification channels, offers & data export.</div></div>
                </div>
                <a href="{{ route('platform.settings') }}" class="btn btn-ghost btn-sm w-100 mt-3">Open settings</a>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/platform-notify.js') }}?v={{ filemtime(public_path('js/modules/platform-notify.js')) }}"></script>
<script>
// Live token cards: poll each ticket's state every 10s (pauses when the tab is hidden).
(() => {
    const cards = [...document.querySelectorAll('[data-state-url]')];
    if (!cards.length) return;
    const labels = { waiting: 'Waiting', called: 'Called — go to counter', serving: 'Being served', completed: 'Completed', absent: 'Marked absent', cancelled: 'Cancelled', left: 'Left queue' };
    const cls = { waiting: 'waiting', called: 'called', serving: 'serving' };
    const tick = async () => {
        if (document.hidden) return;
        for (const card of cards) {
            try {
                const res = await fetch(card.dataset.stateUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) continue;
                const d = (await res.json()).data;
                const set = (f, v) => { const el = card.querySelector(`[data-f="${f}"]`); if (el && el.textContent !== String(v)) el.textContent = v; };
                set('ahead', d.ahead);
                set('wait', d.status === 'waiting' ? `${d.wait_low}–${d.wait_high} min` : 'Now');
                set('counter', d.counter);
                set('current', d.current_token);
                const bar = card.querySelector('[data-f="progress"]'); if (bar) bar.style.width = d.progress + '%';
                if (d.status !== card.dataset.status) {
                    const st = card.querySelector('[data-f="status"]');
                    st.className = 'pk-status ' + (cls[d.status] || 'done');
                    st.textContent = labels[d.status] || d.status;
                    card.classList.remove('flash'); void card.offsetWidth; card.classList.add('flash');
                    if (d.status === 'called') window.pkNotify?.(`${card.dataset.token} — it's your turn`, `Please go to counter ${d.counter}.`, { tag: `tk-${d.id}-called` });
                    card.dataset.status = d.status;
                }
            } catch (e) { /* keep last known state */ }
        }
    };
    setInterval(tick, 10000);
})();
</script>
@endpush
