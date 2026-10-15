@extends('layouts.app')
@section('title', 'Staff Portal — प्रतीक्षा')

@push('head')
<style>
    /* ---------- Head ---------- */
    .console-head { padding: 2rem 0 1.5rem; }
    .console-head h1 { font-size: clamp(1.5rem, 2.6vw, 2rem); }
    .refresh-ind { display: inline-flex; align-items: center; gap: .5rem; padding: .4rem .8rem; border-radius: 999px; font-size: .78rem; font-weight: 700;
        background: #fff; border: 1px solid var(--pk-line); color: var(--pk-ink-2); box-shadow: var(--pk-shadow-sm); }
    .refresh-ind.off .live-dot { background: #b6bcd3; animation: none; box-shadow: none; }
    .switcher { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; margin-top: 1.25rem; }
    .switcher .form-select { min-width: 0; width: 100%; max-width: 340px; padding-top: .55rem; padding-bottom: .55rem; font-weight: 600; font-size: .9rem; }
    .svc-pills { display: flex; gap: .4rem; overflow-x: auto; scrollbar-width: none; padding: 2px; margin: -2px; }
    .svc-pills::-webkit-scrollbar { display: none; }
    .svc-pills a { flex: 0 0 auto; text-decoration: none; padding: .45rem .85rem; border-radius: 999px; font-size: .84rem; font-weight: 700; white-space: nowrap;
        border: 1px solid var(--pk-line); background: #fff; color: var(--pk-ink-2); transition: border-color .2s, color .2s, background .2s; }
    .svc-pills a:hover { border-color: #cdd3ea; color: var(--pk-blue); }
    .svc-pills a.active { background: var(--pk-ink); border-color: var(--pk-ink); color: #fff; }
    .svc-pills a .pfx { opacity: .55; margin-right: .3rem; }

    /* ---------- Console grid: mobile order = now, stats/queue, recent ---------- */
    .console-grid { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); grid-template-areas: "now" "main" "recent"; }
    .area-now { grid-area: now; } .area-main { grid-area: main; } .area-recent { grid-area: recent; }
    @media (min-width: 992px) {
        .console-grid { grid-template-columns: minmax(0, 4fr) minmax(0, 8fr); grid-template-rows: auto 1fr; grid-template-areas: "now main" "recent main"; }
        .area-recent { align-self: start; }
    }

    /* ---------- Now serving + controls ---------- */
    .now-tile { overflow: hidden; padding: 0; }
    .now-top { position: relative; color: #fff; padding: 1.4rem 1.5rem 1.5rem; background:
            radial-gradient(420px 220px at 100% 0%, rgba(112, 70, 232, .5), transparent 70%),
            radial-gradient(380px 220px at 0% 100%, rgba(47, 91, 234, .45), transparent 70%), #0f1535; }
    .now-top .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: rgba(226, 231, 255, .7); }
    .now-top .tok { font-size: clamp(3.2rem, 7vw, 4.4rem); font-weight: 800; letter-spacing: -.045em; line-height: 1; margin: .55rem 0 .7rem; }
    .now-top .meta { font-size: .84rem; color: rgba(226, 231, 255, .72); }
    .now-top .meta b { color: #fff; font-weight: 700; }
    .now-body { padding: 1.25rem 1.5rem 1.5rem; }
    .btn-call { padding: .95rem 1.2rem !important; font-size: 1.05rem; }
    .btn-pause { background: #fff; border: 1px solid var(--pk-line); color: var(--pk-ink-2); font-weight: 700; }
    .btn-pause:hover { background: var(--pk-warn-tint); border-color: #fde3b0; color: #8a4b04; }
    .btn-resume { background: var(--pk-success); color: #fff; border: 0; }
    .btn-resume:hover { background: #12873e; color: #fff; }
    .paused-banner { background: var(--pk-warn-tint); border: 1px solid #fde3b0; color: #8a4b04; border-radius: var(--pk-radius-sm); }

    /* ---------- Stat tiles ---------- */
    .stat { padding: 1rem 1.1rem; height: 100%; display: flex; flex-direction: column; gap: .7rem; }
    .stat .pk-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 10px; }
    .stat .v { font-size: 1.65rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
    .stat .v small { font-size: .85rem; font-weight: 700; color: var(--pk-muted); letter-spacing: 0; }
    .stat .l { font-size: .78rem; font-weight: 600; color: var(--pk-muted); margin-top: .3rem; }
    .conf { font-size: .68rem; font-weight: 700; padding: .18rem .5rem; border-radius: 999px; }
    .conf-low { background: var(--pk-warn-tint); color: var(--pk-warn); }
    .conf-medium { background: var(--pk-tint); color: var(--pk-blue); }
    .conf-high { background: var(--pk-success-tint); color: var(--pk-success); }

    /* ---------- Queue rows ---------- */
    .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.35rem; border-bottom: 1px solid var(--pk-line); }
    .panel-head h2 { font-size: 1.05rem; margin: 0; display: flex; align-items: center; gap: .55rem; }
    .count-pill { font-size: .75rem; font-weight: 800; background: var(--pk-bg); border: 1px solid var(--pk-line); color: var(--pk-ink-2); padding: .1rem .55rem; border-radius: 999px; }
    .q-row { padding: 1.1rem 1.35rem; border-bottom: 1px solid var(--pk-line); position: relative; transition: background .2s; }
    .q-row:last-child { border-bottom: 0; }
    .q-row:hover { background: #fbfcff; }
    .q-row::before { content: ''; position: absolute; left: 0; top: 14px; bottom: 14px; width: 3px; border-radius: 0 3px 3px 0; background: transparent; }
    .q-row.st-called::before { background: var(--pk-success); }
    .q-row.st-serving::before { background: var(--pk-blue); }
    .q-main { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .35rem 1rem; align-items: center; }
    .q-tok { width: 64px; height: 52px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; font-size: 1.05rem; letter-spacing: -.02em;
        background: var(--pk-bg); border: 1px solid var(--pk-line); color: var(--pk-ink); }
    .st-called .q-tok { background: var(--pk-success-tint); border-color: #c9eed6; color: #116a35; }
    .st-serving .q-tok { background: var(--pk-tint); border-color: #d5ddff; color: var(--pk-blue); }
    .q-name { font-weight: 700; }
    .min-w-0 { min-width: 0; }
    .q-meta { font-size: .8rem; color: var(--pk-muted); }
    .q-meta .sep { margin: 0 .35rem; opacity: .5; }
    .q-detail { margin-left: calc(64px + 1rem); margin-top: .75rem; display: grid; gap: .55rem; }
    .docbar { height: 6px; border-radius: 999px; background: #eceef6; overflow: hidden; width: 100%; max-width: 180px; }
    .docbar > span { display: block; height: 100%; border-radius: inherit; background: var(--pk-grad); }
    .docbar.full > span { background: var(--pk-success); }
    .doc-ic { font-size: .78rem; display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: 999px; background: var(--pk-bg); border: 1px solid var(--pk-line); color: var(--pk-ink-2); font-weight: 600; }
    .v-badge { display: inline-flex; align-items: center; gap: .35rem; font-size: .76rem; font-weight: 700; padding: .22rem .6rem; border-radius: 999px; }
    .v-verified { background: var(--pk-success-tint); color: var(--pk-success); }
    .v-incomplete { background: var(--pk-warn-tint); color: var(--pk-warn); }
    .v-rejected { background: var(--pk-danger-tint); color: var(--pk-danger); }
    .v-self { background: var(--pk-tint); color: var(--pk-blue); }
    .v-none { background: #eef0f6; color: var(--pk-muted); }
    .q-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .q-actions .btn { font-size: .84rem; padding: .48rem .9rem; }
    .q-actions .btn-ghost { box-shadow: none; }
    .btn-complete { background: var(--pk-success); color: #fff; border: 0; }
    .btn-complete:hover { background: #12873e; color: #fff; transform: translateY(-1px); }
    .btn-icon { width: 38px; padding-left: 0 !important; padding-right: 0 !important; }
    .dropdown-menu { border: 1px solid var(--pk-line); border-radius: var(--pk-radius-sm); box-shadow: var(--pk-shadow-lg); padding: .35rem; font-size: .88rem; }
    .dropdown-item { border-radius: 8px; padding: .5rem .7rem; font-weight: 600; color: var(--pk-ink-2); display: flex; align-items: center; gap: .55rem; }
    .dropdown-item:hover { background: var(--pk-bg); }
    .dropdown-item.danger { color: var(--pk-danger); }
    .dropdown-item.danger:hover { background: var(--pk-danger-tint); }
    .sub-panel { background: var(--pk-bg); border: 1px solid var(--pk-line); border-radius: var(--pk-radius-sm); padding: 1rem; }
    .sub-panel .legend { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--pk-muted); margin-bottom: .5rem; }
    .seg { display: inline-flex; flex-wrap: wrap; gap: .4rem; }
    .seg .btn { font-size: .82rem; padding: .4rem .8rem; background: #fff; border: 1px solid var(--pk-line); color: var(--pk-ink-2); }
    .seg .btn-check:checked + .r-verified { background: var(--pk-success-tint); border-color: #9fdcb5; color: #116a35; }
    .seg .btn-check:checked + .r-incomplete { background: var(--pk-warn-tint); border-color: #f6cf85; color: #8a4b04; }
    .seg .btn-check:checked + .r-rejected { background: var(--pk-danger-tint); border-color: #ffb3c4; color: #a3153a; }
    .miss-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .25rem 1rem; }
    .sub-panel .form-control, .sub-panel .form-select { background-color: #fff; }

    /* ---------- Empty / finished ---------- */
    .empty { text-align: center; padding: 3.5rem 1.5rem; }
    .empty .pk-icon { width: 56px; height: 56px; font-size: 1.5rem; border-radius: 16px; }
    .done-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem 0; border-bottom: 1px solid var(--pk-line); font-size: .86rem; }
    .done-row:last-child { border-bottom: 0; }
    .done-row .t { font-weight: 800; min-width: 3.4rem; display: inline-block; }
    .pk-status.sm { font-size: .72rem; padding: .22rem .6rem; }
    .pk-status.sm::before { width: 6px; height: 6px; }
    .pk-status.absent { background: var(--pk-danger-tint); color: var(--pk-danger); }

    @media (max-width: 575.98px) {
        .q-row, .panel-head { padding-left: 1rem; padding-right: 1rem; }
        .q-main { grid-template-columns: auto minmax(0, 1fr); }
        .q-main .pk-status { grid-column: 1 / -1; justify-self: start; }
        .q-tok { width: 56px; height: 46px; font-size: .95rem; }
        .q-detail { margin-left: 0; }
        .now-top, .now-body { padding-left: 1.2rem; padding-right: 1.2rem; }
        .switcher .form-select { max-width: none; }
    }
</style>
@endpush

@section('content')
@php
    $waiting = $service->waitingCount();
    $openCounters = $counters->where('is_open', true)->count();
    $conf = $estimate['confidence'] ?? 'low';
    $statusIcons = ['available' => ['bi-check-circle-fill', 'text-success', 'Available'], 'missing' => ['bi-x-circle-fill', 'text-danger', 'Missing'], 'needs_review' => ['bi-exclamation-circle-fill', 'text-warning', 'Needs review']];
    $statusLabel = ['waiting' => 'Waiting', 'called' => 'Called', 'serving' => 'Serving'];
    $doneClass = ['completed' => 'done', 'absent' => 'absent', 'cancelled' => 'done', 'left' => 'done'];
    $byLocation = $services->groupBy(fn ($s) => $s->location->name);
    $siblings = $services->where('location_id', $service->location_id);
@endphp

{{-- ================= Page head ================= --}}
<header class="pk-page-head console-head">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="fade-up">
                <span class="pk-eyebrow mb-2"><i class="bi bi-person-badge"></i> Staff console</span>
                <h1 class="mb-1">{{ $service->location->name }}</h1>
                <div class="pk-muted">
                    {{ $service->name }} <span class="mx-1 opacity-50">·</span> prefix <b class="text-body">{{ $service->prefix }}</b>
                    @if($service->location->area)<span class="mx-1 opacity-50">·</span><i class="bi bi-geo-alt"></i> {{ $service->location->area }}@endif
                </div>
            </div>
            <div class="refresh-ind fade-up" id="refreshInd" aria-live="polite" style="animation-delay:.05s">
                <span class="live-dot" aria-hidden="true"></span><span id="refreshText">Auto-refresh on (20s)</span>
            </div>
        </div>

        @if($services->count() > 1)
            <nav class="switcher fade-up" aria-label="Switch service" style="animation-delay:.08s">
                <form method="GET" action="{{ route('staff') }}" class="d-flex gap-2 flex-grow-1 flex-md-grow-0" id="svcForm" style="max-width: 100%;">
                    <label for="svcSelect" class="visually-hidden">Location and service</label>
                    <select id="svcSelect" name="service" class="form-select">
                        @foreach($byLocation as $locName => $group)
                            <optgroup label="{{ $locName }}">
                                @foreach($group as $s)
                                    <option value="{{ $s->id }}" @selected($s->id === $service->id)>{{ $s->prefix }} · {{ $s->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-ghost btn-sm">Go</button></noscript>
                </form>
                @if($siblings->count() > 1)
                    <div class="svc-pills" aria-label="Services at this location">
                        @foreach($siblings as $s)
                            <a href="{{ route('staff', ['service' => $s->id]) }}" class="{{ $s->id === $service->id ? 'active' : '' }}" @if($s->id === $service->id) aria-current="page" @endif>
                                <span class="pfx">{{ $s->prefix }}</span>{{ $s->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </nav>
        @endif
    </div>
</header>

<div class="container py-4 py-lg-5">
    {{-- Success and queue errors are shown by the layout toast; other validation errors appear here. --}}
    @if($errors->any() && ! $errors->has('queue'))
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    @if($service->is_paused)
        <div class="paused-banner px-3 py-2 mb-4 d-flex align-items-center gap-2 fade-up" role="status">
            <i class="bi bi-pause-circle-fill fs-5"></i>
            <div><b>Queue paused.</b> New customers cannot join {{ $service->name }} until you resume.</div>
        </div>
    @endif

    <div class="console-grid">
        {{-- ================= Left: now serving + controls ================= --}}
        <div class="area-now">
            <div class="pk-card now-tile reveal">
                <div class="now-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="lbl">Now serving</span>
                        @if($service->is_paused)
                            <span class="chip" style="background: rgba(245,158,11,.18); color: #fcd34d;"><i class="bi bi-pause-fill"></i> Paused</span>
                        @else
                            <span class="chip" style="background: rgba(34,197,94,.16); color: #86efac;"><span class="live-dot" style="width:6px;height:6px;background:#4ade80"></span> Open</span>
                        @endif
                    </div>
                    <div class="tok" aria-live="polite">{{ $service->current_number > 0 ? $service->token($service->current_number) : '—' }}</div>
                    <div class="meta">Last issued <b>{{ $service->last_number > 0 ? $service->token($service->last_number) : 'none yet' }}</b> <span class="mx-1 opacity-50">·</span> avg <b>{{ $service->avg_minutes }} min</b> / person</div>
                </div>
                <div class="now-body">
                    <form method="POST" action="{{ route('staff.next', $service) }}">
                        @csrf
                        <label for="nextCounter" class="form-label small">Call to counter <span class="pk-muted fw-normal">(optional)</span></label>
                        <select id="nextCounter" name="counter_id" class="form-select mb-3">
                            <option value="">Keep ticket's counter</option>
                            @foreach($counters as $c)
                                <option value="{{ $c->id }}">Counter {{ $c->number }}@if($c->label) — {{ $c->label }}@endif{{ $c->is_open ? '' : ' (closed)' }}</option>
                            @endforeach
                        </select>
                        <div class="d-grid">
                            <button class="btn btn-pk btn-call" @disabled($waiting < 1)><i class="bi bi-megaphone-fill me-1"></i> Call next</button>
                        </div>
                        @if($waiting < 1)<div class="small pk-muted text-center mt-2">No one is waiting yet.</div>@endif
                    </form>
                    <form method="POST" action="{{ route('staff.pause', $service) }}" class="d-grid mt-2">
                        @csrf
                        @if($service->is_paused)
                            <button class="btn btn-resume"><i class="bi bi-play-fill"></i> Resume queue</button>
                        @else
                            <button class="btn btn-pause"><i class="bi bi-pause-fill"></i> Pause queue</button>
                        @endif
                    </form>
                </div>
            </div>

        </div>
        <div class="area-recent">
            {{-- Module tools for this service's location. --}}
            <div class="d-grid gap-3 mb-3">
                @includeIf('ops.partials.staff-card', ['service' => $service])
                @includeIf('integrity.partials.staff-card', ['service' => $service])
                @includeIf('platform.partials.staff-card', ['service' => $service])
            </div>
            <div class="pk-card p-4 reveal" style="--d:.08s">
                <h2 class="h6 fw-bold mb-2 d-flex align-items-center gap-2"><i class="bi bi-clock-history pk-muted"></i> Recently finished</h2>
                @forelse($done as $d)
                    <div class="done-row">
                        <span class="text-truncate"><span class="t">{{ $d->token() }}</span> <span class="pk-muted">{{ $d->name ?: ($d->user->name ?? 'Walk-in') }}@if($d->counter) · C{{ $d->counter->number }}@endif</span></span>
                        <span class="pk-status sm {{ $doneClass[$d->status] ?? 'done' }} flex-shrink-0">{{ ucfirst($d->status) }}</span>
                    </div>
                @empty
                    <div class="small pk-muted py-2">Nothing finished yet.</div>
                @endforelse
            </div>
        </div>

        {{-- ================= Right: stats + active queue ================= --}}
        <div class="area-main">
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3 reveal">
                    <div class="pk-card stat">
                        <span class="pk-icon amber"><i class="bi bi-people"></i></span>
                        <div><div class="v">{{ $waiting }}</div><div class="l">Waiting</div></div>
                    </div>
                </div>
                <div class="col-6 col-xl-3 reveal" style="--d:.05s">
                    <div class="pk-card stat" title="{{ $estimate['explanation'] ?? '' }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="pk-icon violet"><i class="bi bi-hourglass-split"></i></span>
                            <span class="conf conf-{{ $conf }}">{{ ucfirst($conf) }}</span>
                        </div>
                        <div><div class="v">{{ $estimate['low'] ?? 0 }}–{{ $estimate['high'] ?? 0 }}<small> min</small></div><div class="l">New joiner wait</div></div>
                    </div>
                </div>
                <div class="col-6 col-xl-3 reveal" style="--d:.1s">
                    <div class="pk-card stat">
                        <span class="pk-icon green"><i class="bi bi-check2-circle"></i></span>
                        <div><div class="v">{{ $completedToday }}</div><div class="l">Completed today</div></div>
                    </div>
                </div>
                <div class="col-6 col-xl-3 reveal" style="--d:.15s">
                    <div class="pk-card stat">
                        <span class="pk-icon"><i class="bi bi-door-open"></i></span>
                        <div><div class="v">{{ $openCounters }}<small>/{{ $counters->count() }}</small></div><div class="l">Open counters</div></div>
                    </div>
                </div>
            </div>
            @if(!empty($estimate['explanation']))
                <p class="small pk-muted mb-4 d-flex gap-2"><i class="bi bi-info-circle"></i>
                    <span>{{ $estimate['explanation'] }}
                    @isset($estimate['samples']) Based on {{ $estimate['samples'] }} recent samples · {{ $estimate['counters'] ?? $openCounters }} counter(s) · avg {{ $estimate['avg'] ?? $service->avg_minutes }} min.@endisset</span>
                </p>
            @endif

            <section class="pk-card reveal" style="--d:.1s" aria-labelledby="activeHead">
                <div class="panel-head">
                    <h2 id="activeHead"><i class="bi bi-list-ol pk-muted"></i> Active queue <span class="count-pill">{{ $active->count() }}</span></h2>
                    <span class="small pk-muted d-none d-sm-inline"><i class="bi bi-database me-1"></i>Local demo database</span>
                </div>

                @forelse($active as $t)
                    @php
                        [$rdy, $tot, $pct] = $t->documentReadiness();
                        $ver = $t->verifications->first();
                        $checklist = $t->doc_checklist ?? [];
                        $who = $t->name ?: ($t->user->name ?? 'Walk-in');
                    @endphp
                    <article class="q-row st-{{ $t->status }}">
                        <div class="q-main">
                            <span class="q-tok">{{ $t->token() }}</span>
                            <div class="min-w-0">
                                <div class="q-name text-truncate">{{ $who }}</div>
                                <div class="q-meta">
                                    @if($t->counterNumber())Counter {{ $t->counterNumber() }}<span class="sep">·</span>@endif
                                    @if($t->status === 'waiting'){{ $t->ahead() }} ahead<span class="sep">·</span>@endif
                                    joined {{ $t->created_at?->diffForHumans() }}
                                    @if($t->called_at)<span class="sep">·</span>called {{ $t->called_at->diffForHumans() }}@endif
                                </div>
                            </div>
                            <span class="pk-status {{ $t->status }}">{{ $statusLabel[$t->status] ?? ucfirst($t->status) }}</span>
                        </div>

                        <div class="q-detail">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @if($ver)
                                    <span class="v-badge v-{{ $ver->result }}"><i class="bi bi-shield-check"></i> Docs {{ $ver->result }}</span>
                                    <span class="small pk-muted">
                                        @if($ver->verified_at){{ $ver->verified_at->diffForHumans() }}@endif
                                        @if(!empty($ver->missing)) · missing: {{ implode(', ', (array) $ver->missing) }}@endif
                                        @if($ver->reason) · “{{ $ver->reason }}”@endif
                                    </span>
                                @elseif($t->docs_ready)
                                    <span class="v-badge v-self"><i class="bi bi-person-check"></i> Self-checked ready</span>
                                    <span class="small pk-muted">not yet verified by staff</span>
                                @else
                                    <span class="v-badge v-none"><i class="bi bi-hourglass"></i> Not verified</span>
                                @endif
                                @if($tot > 0)
                                    <span class="d-inline-flex align-items-center gap-2 small pk-muted ms-sm-2">
                                        Docs {{ $rdy }}/{{ $tot }}
                                        <span class="docbar {{ $pct >= 100 ? 'full' : '' }}" style="width: 110px" role="progressbar" aria-label="Document readiness" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $pct }}%"></span></span>
                                    </span>
                                @endif
                            </div>
                            @if($tot > 0 && $checklist)
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($checklist as $doc => $st)
                                        @php $ic = $statusIcons[$st] ?? ['bi-question-circle', 'text-muted', 'Unknown']; @endphp
                                        <span class="doc-ic"><i class="bi {{ $ic[0] }} {{ $ic[1] }}" aria-hidden="true"></i><span>{{ $doc }}</span><span class="visually-hidden"> — {{ $ic[2] }}</span></span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="q-actions mt-1">
                                @if($t->status === 'called')
                                    <form method="POST" action="{{ route('staff.start', $t) }}">@csrf<button class="btn btn-pk btn-sm"><i class="bi bi-play-fill"></i> Start service</button></form>
                                @endif
                                @if(in_array($t->status, ['called', 'serving']))
                                    <form method="POST" action="{{ route('staff.complete', $t) }}">@csrf<button class="btn btn-sm {{ $t->status === 'serving' ? 'btn-complete' : 'btn-ghost' }}"><i class="bi bi-check2-circle"></i> Complete</button></form>
                                @endif
                                <button class="btn btn-ghost btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#verify-{{ $t->id }}" aria-expanded="false" aria-controls="verify-{{ $t->id }}"><i class="bi bi-file-earmark-check"></i> Verify docs</button>
                                <div class="dropdown">
                                    <button class="btn btn-ghost btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for {{ $t->token() }}"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if($t->status === 'called')
                                            <li><form method="POST" action="{{ route('staff.absent', $t) }}">@csrf<button class="dropdown-item"><i class="bi bi-person-x"></i> Mark absent</button></form></li>
                                        @endif
                                        <li><button class="dropdown-item" type="button" data-bs-toggle="collapse" data-bs-target="#more-{{ $t->id }}" aria-controls="more-{{ $t->id }}"><i class="bi bi-arrow-left-right"></i> Move to counter…</button></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><button class="dropdown-item danger" type="button" data-bs-toggle="collapse" data-bs-target="#more-{{ $t->id }}" aria-controls="more-{{ $t->id }}"><i class="bi bi-x-circle"></i> Cancel ticket…</button></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="collapse" id="more-{{ $t->id }}">
                                <div class="sub-panel mt-2">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="legend">Move to counter</div>
                                            <form method="POST" action="{{ route('staff.move', $t) }}" class="d-flex gap-2">
                                                @csrf
                                                <label for="move-{{ $t->id }}" class="visually-hidden">Move {{ $t->token() }} to counter</label>
                                                <select id="move-{{ $t->id }}" name="counter_id" class="form-select form-select-sm" required>
                                                    <option value="" disabled selected>Choose counter…</option>
                                                    @foreach($counters as $c)
                                                        <option value="{{ $c->id }}">Counter {{ $c->number }}@if($c->label) — {{ $c->label }}@endif{{ $c->is_open ? '' : ' (closed)' }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-ghost btn-sm flex-shrink-0">Move</button>
                                            </form>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="legend">Cancel ticket</div>
                                            <form method="POST" action="{{ route('staff.cancel', $t) }}" class="d-flex gap-2 js-confirm" data-confirm="Cancel ticket {{ $t->token() }}? The customer will lose their place.">
                                                @csrf
                                                <label for="reason-{{ $t->id }}" class="visually-hidden">Cancel reason (optional)</label>
                                                <input id="reason-{{ $t->id }}" type="text" name="reason" maxlength="255" class="form-control form-control-sm" placeholder="Reason (optional)">
                                                <button class="btn btn-sm btn-outline-danger flex-shrink-0">Cancel</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="collapse" id="verify-{{ $t->id }}">
                                <form method="POST" action="{{ route('staff.verify', $t) }}" class="sub-panel mt-2">
                                    @csrf
                                    <fieldset class="mb-3">
                                        <legend class="legend">Result <span class="text-danger">*</span></legend>
                                        <div class="seg">
                                            @foreach(['verified' => ['Verified', 'bi-check-circle'], 'incomplete' => ['Incomplete', 'bi-exclamation-circle'], 'rejected' => ['Rejected', 'bi-x-circle']] as $val => [$lbl, $ico])
                                                <input class="btn-check js-result" type="radio" name="result" id="res-{{ $t->id }}-{{ $val }}" value="{{ $val }}" required data-ticket="{{ $t->id }}">
                                                <label class="btn r-{{ $val }}" for="res-{{ $t->id }}-{{ $val }}"><i class="bi {{ $ico }}"></i> {{ $lbl }}</label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    @if(!empty($service->documents))
                                        <fieldset class="mb-3">
                                            <legend class="legend">Missing documents</legend>
                                            <div class="miss-grid">
                                                @foreach($service->documents as $i => $doc)
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="missing[]" value="{{ $doc }}" id="miss-{{ $t->id }}-{{ $i }}" @checked(($checklist[$doc] ?? null) === 'missing')>
                                                        <label class="form-check-label small" for="miss-{{ $t->id }}-{{ $i }}">{{ $doc }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @endif
                                    <label for="vreason-{{ $t->id }}" class="legend d-block">Reason <span class="text-lowercase fw-normal" style="letter-spacing:0">(required if rejected)</span></label>
                                    <input id="vreason-{{ $t->id }}" type="text" name="reason" maxlength="255" class="form-control form-control-sm mb-3" placeholder="e.g. citizenship copy unclear">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <p class="small pk-muted mb-0" style="max-width: 32rem"><i class="bi bi-info-circle"></i> Physical check only: this records what staff saw; it does not certify document authenticity.</p>
                                        <button class="btn btn-pk btn-sm"><i class="bi bi-shield-check"></i> Save verification</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="empty">
                        <span class="pk-icon violet mb-3"><i class="bi bi-cup-hot"></i></span>
                        <div class="fw-bold fs-5">No one in the queue right now</div>
                        <div class="small pk-muted mt-1">Time for chiya. New joiners appear here automatically.</div>
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Service switcher: navigate as soon as a service is picked.
    var sel = document.getElementById('svcSelect');
    if (sel) sel.addEventListener('change', function () { sel.form.submit(); });

    // Confirm before cancelling a ticket.
    document.querySelectorAll('form.js-confirm').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // Reason becomes required when "rejected" is chosen.
    document.querySelectorAll('.js-result').forEach(function (r) {
        r.addEventListener('change', function () {
            var input = document.getElementById('vreason-' + r.dataset.ticket);
            if (input) input.required = (r.value === 'rejected' && r.checked);
        });
    });

    // Auto-refresh every 20s, but never interrupt staff mid-action.
    var ind = document.getElementById('refreshInd'), txt = document.getElementById('refreshText');
    function busy() {
        var a = document.activeElement;
        if (a && a.closest && a.closest('form') && /^(INPUT|SELECT|TEXTAREA|BUTTON)$/.test(a.tagName)) return true;
        if (document.querySelector('.collapse.show, .collapsing')) return true;
        if (document.querySelector('.modal.show, .dropdown-menu.show')) return true;
        return false;
    }
    setInterval(function () {
        if (busy()) {
            ind && ind.classList.add('off');
            if (txt) txt.textContent = 'Auto-refresh paused while you work';
            return;
        }
        location.reload();
    }, 20000);
    function sync() {
        setTimeout(function () {
            var b = busy();
            if (ind) ind.classList.toggle('off', b);
            if (txt) txt.textContent = b ? 'Auto-refresh paused while you work' : 'Auto-refresh on (20s)';
        }, 50);
    }
    ['focusin', 'focusout', 'shown.bs.collapse', 'hidden.bs.collapse', 'shown.bs.dropdown', 'hidden.bs.dropdown'].forEach(function (ev) { document.addEventListener(ev, sync); });
})();
</script>
@endpush
