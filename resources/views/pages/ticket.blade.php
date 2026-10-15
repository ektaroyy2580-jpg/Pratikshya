@extends('layouts.app')
@section('title', 'Token '.$ticket->token().' — प्रतीक्षा')

@php
    $s = $ticket->service; $loc = $s->location;
    $places = $loc->places->map(fn($p) => [
        'name' => $p->name, 'category' => $p->category, 'emoji' => $p->emoji, 'tip' => $p->tip, 'lat' => $p->lat, 'lng' => $p->lng,
        'walk' => $p->walk_minutes, 'visit' => $p->visit_minutes, 'rating' => $p->rating,
    ])->values();
    [$docReady, $docTotal, $docPct] = $ticket->documentReadiness();
    $checklist = $ticket->doc_checklist ?? [];
    $lastCheck = $ticket->verifications->first();
@endphp

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<style>
    /* ---- Ticket card ---- */
    .ticket { display: grid; grid-template-columns: 260px 1fr; background: #fff; border: 1px solid var(--pk-line); border-radius: 22px; box-shadow: var(--pk-shadow-lg); overflow: hidden; }
    .ticket-stub { position: relative; background: var(--pk-grad); color: #fff; padding: 1.6rem 1.5rem; display: flex; flex-direction: column; justify-content: space-between; }
    .ticket-stub::after { content: ''; position: absolute; right: -1px; top: 14px; bottom: 14px; border-right: 2px dashed rgba(255,255,255,.45); }
    .token-big { font-size: clamp(3rem, 7vw, 4.2rem); font-weight: 800; letter-spacing: -.045em; line-height: 1; }
    .ticket-main { padding: 1.5rem 1.6rem; }
    .tstat { background: var(--pk-bg); border-radius: 14px; padding: .8rem .9rem; height: 100%; }
    .tstat .l { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; color: var(--pk-muted); }
    .tstat .v { font-size: 1.55rem; font-weight: 800; letter-spacing: -.03em; line-height: 1.15; }
    .tstat .s { font-size: .72rem; color: var(--pk-muted); font-weight: 600; }
    .qbar { height: 10px; border-radius: 999px; background: #eceef6; overflow: hidden; }
    .qbar > div { height: 100%; background: var(--pk-grad); border-radius: inherit; transition: width .9s var(--pk-ease); }
    .bump { animation: pk-bump .45s var(--pk-ease); }
    @keyframes pk-bump { 40% { transform: translateY(-3px); color: var(--pk-blue); } }
    @media (max-width: 767.98px) {
        .ticket { grid-template-columns: 1fr; }
        .ticket-stub { flex-direction: row; align-items: flex-end; }
        .ticket-stub::after { left: 14px; right: 14px; top: auto; bottom: -1px; border-right: 0; border-bottom: 2px dashed rgba(255,255,255,.45); }
    }

    /* ---- Call banner ---- */
    .call-banner { border-radius: 16px; background: var(--pk-success-tint); color: #116a35; border: 1px solid #bfe9cf; display: flex; align-items: center; gap: .75rem; padding: 1rem 1.2rem; font-weight: 700; animation: pk-rise .45s var(--pk-ease) both; }
    .call-banner .pk-icon { background: var(--pk-success); color: #fff; }

    /* ---- Activities ---- */
    .choice { display: flex; align-items: center; gap: .9rem; text-align: left; width: 100%; border: 1px solid var(--pk-line); background: #fff; border-radius: 16px; padding: 1rem 1.1rem; cursor: pointer; transition: border-color .2s, box-shadow .2s, transform .2s var(--pk-ease); }
    .choice:hover { border-color: #c7d0f3; transform: translateY(-2px); box-shadow: var(--pk-shadow); }
    .choice.active { border-color: var(--pk-blue); box-shadow: 0 0 0 4px rgba(47,91,234,.12); }
    .choice .t { font-weight: 800; }
    .choice .d { font-size: .8rem; color: var(--pk-muted); }

    /* ---- Quiz ---- */
    .q-timer { height: 6px; border-radius: 999px; background: #eceef6; overflow: hidden; } .q-timer > div { height: 100%; background: var(--pk-grad); transition: width 1s linear; }
    .ans { border: 1px solid var(--pk-line); border-radius: 14px; padding: .85rem 1rem; font-weight: 600; background: #fff; text-align: left; width: 100%; transition: border-color .15s, background .15s; }
    .ans:hover:not(:disabled) { border-color: #a9b8f5; background: var(--pk-tint); }
    .ans.right { background: var(--pk-success-tint); border-color: #86d9a6; } .ans.wrong { background: var(--pk-danger-tint); border-color: #f9a8bb; }
    .lb-row { display: flex; align-items: center; gap: .6rem; padding: .5rem .7rem; border-radius: 12px; transition: background .3s; }
    .lb-row.me { background: var(--pk-tint); font-weight: 700; }
    .avatar { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; color: #fff; font-size: .8rem; flex-shrink: 0; }
    .points-pop { display: inline-block; background: var(--pk-tint); color: var(--pk-blue); font-weight: 800; font-size: 1.3rem; border-radius: 14px; padding: .45rem 1.1rem; animation: pk-rise .5s .2s var(--pk-ease) both; }
    .confetti { position: fixed; top: -20px; width: 8px; height: 12px; border-radius: 2px; z-index: 3000; animation: fall linear forwards; pointer-events: none; }
    @keyframes fall { to { transform: translateY(110vh) rotate(540deg); } }

    /* ---- Explore ---- */
    #exploreMap { height: 360px; border-radius: 16px; border: 1px solid var(--pk-line); }
    .place { border: 1px solid var(--pk-line); border-radius: 14px; padding: .8rem .9rem; transition: border-color .2s, background .2s; cursor: pointer; background: #fff; }
    .place:hover { border-color: #b9c6f5; background: #fbfcff; }
    .place.nofit { opacity: .6; }
    #catFilter .btn { border-radius: 999px !important; margin: 0 .2rem .2rem 0; font-size: .8rem; padding: .3rem .8rem; border-color: var(--pk-line); color: var(--pk-ink-2); background: #fff; }
    #catFilter .btn.active { background: var(--pk-ink); border-color: var(--pk-ink); color: #fff; }
    .map-pin { background: var(--pk-ink); color: #fff; border-radius: 10px; padding: 4px 8px; font-weight: 700; white-space: nowrap; font-size: 12px; box-shadow: 0 6px 14px -6px rgba(15,21,53,.6); }

    /* ---- Side panel ---- */
    .side-sticky { position: sticky; top: 88px; }
    .doc-line { display: flex; align-items: center; gap: .6rem; font-size: .88rem; padding: .45rem 0; border-bottom: 1px dashed var(--pk-line); }
    .doc-line:last-child { border-bottom: 0; }

    /* ---- Overlays ---- */
    .overlay { position: fixed; inset: 0; z-index: 2500; display: none; place-items: center; background: rgba(15,21,53,.55); backdrop-filter: blur(6px); padding: 16px; }
    .overlay.show { display: grid; animation: pk-fade .25s ease both; }
    @keyframes pk-fade { from { opacity: 0; } to { opacity: 1; } }
    .modal-card { background: #fff; border-radius: 24px; max-width: 420px; width: 100%; padding: 2rem; text-align: center; box-shadow: var(--pk-shadow-lg); animation: pk-rise .4s var(--pk-ease) both; }
    .bell { width: 64px; height: 64px; border-radius: 50%; display: inline-grid; place-items: center; font-size: 1.7rem; background: var(--pk-tint); color: var(--pk-blue); animation: ring .9s ease-in-out 2; }
    @keyframes ring { 0%,100% { transform: rotate(0) } 25% { transform: rotate(14deg) } 50% { transform: rotate(-12deg) } 75% { transform: rotate(6deg) } }
    .counter-badge { display: inline-block; background: var(--pk-ink); color: #fff; border-radius: 14px; padding: .55rem 1.3rem; font-size: 1.4rem; font-weight: 800; letter-spacing: -.02em; }
    .done-card { max-width: 520px; }
    .done-card .big { font-size: clamp(1.7rem, 5vw, 2.4rem); line-height: 1.1; }

    /* ---- Presenter toolbar (staff only) ---- */
    .demo-bar { position: fixed; bottom: 16px; right: 16px; z-index: 1500; background: #fff; border: 1px solid var(--pk-line); border-radius: 16px; padding: .45rem; box-shadow: var(--pk-shadow-lg); font-size: .8rem; max-width: calc(100vw - 32px); }
    .demo-bar .btn { font-size: .78rem; padding: .35rem .7rem; }
    @media (max-width: 767.98px) { .demo-bar { bottom: 84px; } }
</style>
@endpush

@section('content')
{{-- ================= HEADER ================= --}}
<section class="pk-page-head">
    <div class="container">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <a class="pk-breadcrumb" href="{{ route('profile') }}"><i class="bi bi-arrow-left me-1"></i>My visits</a>
                <h1 class="h3 mb-1 mt-2">{{ $s->name }}</h1>
                <div class="pk-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $loc->name }} · {{ $loc->area }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="pk-status waiting" id="statusPill">Waiting</span>
                <span class="small pk-muted d-none d-sm-inline"><span class="live-dot me-1"></span>Live</span>
            </div>
        </div>

        {{-- Ticket --}}
        <div class="ticket fade-up">
            <div class="ticket-stub">
                <div>
                    <div class="small fw-semibold opacity-75 text-uppercase" style="letter-spacing:.06em">Your token</div>
                    <div class="token-big" id="tToken">{{ $ticket->token() }}</div>
                </div>
                <div class="text-end text-md-start">
                    <div class="small opacity-75">Go to</div>
                    <div class="fw-bold fs-5">Counter <span id="tCounter">{{ $ticket->counterNumber() }}</span></div>
                    <div class="small opacity-75 mt-1">{{ $ticket->name }}</div>
                </div>
            </div>
            <div class="ticket-main">
                <div class="row g-2 mb-3">
                    <div class="col-6 col-lg-3"><div class="tstat"><div class="l">Now serving</div><div class="v" id="tCurrent"><span class="skeleton">D-00</span></div></div></div>
                    <div class="col-6 col-lg-3"><div class="tstat"><div class="l">Ahead of you</div><div class="v" id="tAhead"><span class="skeleton">00</span></div></div></div>
                    <div class="col-6 col-lg-3"><div class="tstat"><div class="l">Estimated wait</div><div class="v"><span id="tWait"><span class="skeleton">00</span></span> <small class="fs-6 fw-semibold pk-muted">min</small></div><div class="s" id="tConf"></div></div></div>
                    <div class="col-6 col-lg-3"><div class="tstat"><div class="l">In queue</div><div class="v" id="tWaiting"><span class="skeleton">00</span></div></div></div>
                </div>
                <div class="qbar mb-2" role="progressbar" aria-label="Progress to your turn"><div id="tBar" style="width:2%"></div></div>
                <div class="d-flex justify-content-between small pk-muted fw-semibold mb-2"><span>Joined</span><span>Your turn</span></div>
                <div class="small pk-muted"><i class="bi bi-info-circle me-1"></i><span id="tExplain">Estimate, not a guarantee.</span></div>
                <div id="pausedNote" class="small fw-bold mt-2 d-none" style="color:var(--pk-warn)"><i class="bi bi-pause-circle-fill me-1"></i>Staff have paused this queue for now.</div>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div id="callBanner" class="call-banner mb-4 d-none"></div>
    {{-- Live office announcements and hospital emergency help come first. --}}
    @includeIf('ops.partials.announcement-banner', ['ticket' => $ticket])
    @includeIf('explore.partials.hospital-banner', ['ticket' => $ticket])

    <div class="row g-4">
        {{-- ================= ACTIVITIES ================= --}}
        <div class="col-lg-8" id="activities">
            <div class="pk-card p-4 mb-4 reveal">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                    <div>
                        <div class="pk-eyebrow mb-1">While you wait</div>
                        <h2 class="h4 mb-0">You have <span class="text-gradient" id="haveMins">{{ $ticket->initial_wait }} min</span></h2>
                    </div>
                    <span class="small pk-muted">Your place in line never depends on games or points.</span>
                </div>
                <div class="row g-2">
                    <div class="col-md-4"><button type="button" class="choice" data-panel="play"><span class="pk-icon violet"><i class="bi bi-controller"></i></span><span><span class="t d-block">Play</span><span class="d">Quiz, memory, chess, Ludo</span></span></button></div>
                    <div class="col-md-4"><button type="button" class="choice" data-panel="compete"><span class="pk-icon"><i class="bi bi-trophy"></i></span><span><span class="t d-block">Compete</span><span class="d">Quiz battle vs. people here</span></span></button></div>
                    <div class="col-md-4"><button type="button" class="choice" data-panel="explore"><span class="pk-icon green"><i class="bi bi-compass"></i></span><span><span class="t d-block">Explore nearby</span><span class="d">Places that fit your wait</span></span></button></div>
                </div>
            </div>

            {{-- GAME --}}
            <div class="pk-card p-4 mb-4 d-none" id="panel-game">
                <div id="picker" class="d-none"></div>
                <div id="arena" class="d-none"></div>
                <div id="lobby" class="text-center py-3 d-none">
                    <h3 class="h5">Finding players waiting near you…</h3>
                    <p class="small pk-muted">Practice opponents (simulated) for the demo.</p>
                    <div class="d-flex justify-content-center gap-3 my-4" id="lobbyPlayers"></div>
                    <div class="spinner-border text-primary" id="lobbySpin" role="status"><span class="visually-hidden">Loading</span></div>
                </div>
                <div id="quiz" class="d-none">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="chip" id="qNum">Question 1/6</span>
                                <span class="fw-bold small"><i class="bi bi-stopwatch me-1"></i><span id="qTime">10</span>s</span>
                                <span class="fw-bold small">Score <span id="qScore">0</span></span>
                            </div>
                            <div class="q-timer mb-3"><div id="qTimerBar" style="width:100%"></div></div>
                            <h3 class="h5 mb-3" id="qText"></h3>
                            <div class="d-grid gap-2" id="qAnswers"></div>
                        </div>
                        <div class="col-lg-4" id="lbCol">
                            <div class="small fw-bold text-uppercase pk-muted mb-2"><i class="bi bi-trophy-fill text-warning me-1"></i>Live leaderboard</div>
                            <div id="lb"></div>
                        </div>
                    </div>
                </div>
                <div id="result" class="text-center py-3 d-none"></div>
            </div>

            {{-- EXPLORE --}}
            <div class="pk-card p-4 mb-4 d-none" id="panel-explore">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h3 class="h5 mb-1">Explore near {{ $loc->name }}</h3>
                        <div class="small pk-muted">Round-trip walk + visit, matched to your <b><span class="haveMins2">{{ $ticket->initial_wait }}</span> min</b> wait with a 5-minute buffer. Walk times are estimates.</div>
                    </div>
                    <div id="catFilter"></div>
                </div>
                <div class="row g-3">
                    <div class="col-lg-7"><div id="exploreMap"></div></div>
                    <div class="col-lg-5"><div class="d-grid gap-2" id="placeList" style="max-height:360px;overflow:auto"></div></div>
                </div>
            </div>

            {{-- Module widgets: each renders nothing when it has nothing useful to show. --}}
            <div class="d-grid gap-4 mb-4">
                @includeIf('games.partials.ticket-widget', ['ticket' => $ticket])
                @includeIf('library.partials.ticket-widget', ['ticket' => $ticket])
                @includeIf('english.partials.ticket-widget', ['ticket' => $ticket])
                @includeIf('explore.partials.ticket-widget', ['ticket' => $ticket])
                @includeIf('preorder.partials.ticket-widget', ['ticket' => $ticket])
                @if($ticket->status === 'waiting')
                    @includeIf('ads.partials.slot', ['placement' => 'queue_page', 'ticket' => $ticket, 'context' => ['location_id' => $s->location_id]])
                @endif
            </div>
        </div>

        {{-- ================= SIDE PANEL ================= --}}
        <div class="col-lg-4">
            <div class="d-grid gap-3">
                @includeIf('rides.partials.ticket-widget', ['ticket' => $ticket])
                @includeIf('integrity.partials.ticket-widget', ['ticket' => $ticket])
                <div class="pk-card p-4 reveal" style="--d:.05s">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 class="h6 mb-0">Documents</h3>
                        <span class="chip">{{ $docReady }}/{{ $docTotal }} ready</span>
                    </div>
                    <div class="progress mb-3" style="height:8px"><div class="progress-bar {{ $docPct === 100 ? 'bg-success' : '' }}" style="width: {{ $docPct }}%"></div></div>
                    @foreach($s->documents ?? [] as $d)
                        @php $st = $checklist[$d] ?? 'missing'; @endphp
                        <div class="doc-line">
                            <i class="bi {{ $st === 'available' ? 'bi-check-circle-fill text-success' : ($st === 'needs_review' ? 'bi-question-circle-fill text-warning' : 'bi-x-circle text-danger') }}"></i>
                            <span class="flex-grow-1">{{ $d }}</span>
                        </div>
                    @endforeach
                    @if($lastCheck)
                        <div class="alert {{ $lastCheck->result === 'verified' ? 'alert-success' : 'alert-warning' }} small mt-3 mb-0 py-2">
                            <i class="bi bi-person-badge me-1"></i>Staff check: <b>{{ ucfirst($lastCheck->result) }}</b>@if($lastCheck->reason) · {{ $lastCheck->reason }}@endif
                        </div>
                    @else
                        <div class="small pk-muted mt-3"><i class="bi bi-info-circle me-1"></i>Staff verify originals at the counter.</div>
                    @endif
                </div>

                <div class="pk-card p-4 reveal" style="--d:.1s">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="pk-icon amber"><i class="bi bi-bell"></i></span>
                        <div class="small">We'll alert you at <b>3 people away</b> and when you're <b>next</b>. Keep this page open.</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="pk-icon violet"><i class="bi bi-stars"></i></span>
                        <div class="small">WaitPoints this visit: <b id="ptsNow">{{ 50 + $ticket->game_points }}</b><div class="pk-muted">Points never affect your position.</div></div>
                    </div>
                    @can('leave', $ticket)
                        <hr class="my-3">
                        <form method="POST" action="{{ route('ticket.leave', $ticket) }}" onsubmit="return confirm('Leave the queue and release token {{ $ticket->token() }}? This cannot be undone.')">
                            @csrf
                            <button class="btn btn-ghost w-100 text-danger"><i class="bi bi-box-arrow-left me-1"></i>Leave queue</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Notification modal --}}
<div class="overlay" id="notif" role="dialog" aria-modal="true" aria-labelledby="nTitle"><div class="modal-card">
    <div class="bell"><i class="bi bi-bell-fill"></i></div>
    <h3 class="h4 mt-3" id="nTitle"></h3>
    <p class="pk-muted" id="nBody"></p>
    <div id="nExtra" class="mb-3"></div>
    <button class="btn btn-pk w-100" onclick="closeNotif()">Got it, I'm on my way</button>
</div></div>

{{-- Completion modal --}}
<div class="overlay" id="doneScreen" role="dialog" aria-modal="true"><div class="modal-card done-card">
    <div class="pk-icon green mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;border-radius:50%"><i class="bi bi-check-lg"></i></div>
    <div class="small text-uppercase fw-bold pk-muted mb-1" style="letter-spacing:.06em">Service completed · {{ $ticket->token() }}</div>
    <div class="big fw-bold mb-2">You got your time back.</div>
    <p class="pk-muted">About <b>{{ $ticket->initial_wait }} minutes</b> you didn't spend standing in line.</p>
    <div class="points-pop mb-3">+<span id="donePts">50</span> WaitPoints</div>
    <div class="row g-2 my-3 small" id="doneStats"></div>
    <div class="d-flex justify-content-center gap-2 flex-wrap">
        <a href="{{ route('find') }}" class="btn btn-pk">Join another queue</a>
        <a href="{{ route('profile') }}" class="btn btn-ghost">My visits</a>
    </div>
</div></div>

{{-- Presenter toolbar: staff/admin of this location only; calls the real staff endpoints. --}}
@can('manage', $ticket)
<div class="demo-bar">
    <div class="d-flex align-items-center gap-1 flex-wrap">
        <span class="px-2 small fw-bold pk-muted"><i class="bi bi-sliders me-1"></i>Staff</span>
        <button class="btn btn-pk btn-sm" onclick="staff('next')"><i class="bi bi-megaphone me-1"></i>Call next</button>
        <button class="btn btn-ghost btn-sm" onclick="staff('verify')"><i class="bi bi-patch-check me-1"></i>Verify</button>
        <button class="btn btn-ghost btn-sm" onclick="staff('complete')"><i class="bi bi-flag me-1"></i>Complete</button>
        <a class="btn btn-ghost btn-sm" href="{{ route('staff', ['service' => $s->id]) }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>
    </div>
</div>
@endcan
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('vendor/chess/chess.min.js') }}"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const T = { id: {{ $ticket->id }}, serviceId: {{ $s->id }}, counter: {{ $ticket->counterNumber() }}, token: @json($ticket->token()), initial: {{ $ticket->initial_wait }} };
const URLS = {
    state: @json(route('ticket.state', $ticket)), points: @json(route('ticket.points', $ticket)),
    @can('manage', $ticket) next: @json(route('staff.next', $s)), verify: @json(route('staff.verify', $ticket)), complete: @json(route('staff.complete', $ticket)), @endcan
};
const LOC = { lat: {{ (float) $loc->lat }}, lng: {{ (float) $loc->lng }}, name: @json($loc->name) };
const PLACES = @json($places);
const post = (url, body = {}) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body) });

// ---------- Queue state ----------
// Alerts already shown for this ticket are remembered, so reloading the page does not re-open them.
const NKEY = 'pk-notified-' + T.id;
let state = null, gamePoints = 0;
let notified = (() => { try { return JSON.parse(localStorage.getItem(NKEY)) || {}; } catch (e) { return {}; } })();
const saveNotified = () => { try { localStorage.setItem(NKEY, JSON.stringify(notified)); } catch (e) {} };
function render(st) {
    const prev = state; state = st;
    const set = (id, v) => { const el = document.getElementById(id); if (el.textContent !== String(v)) { el.textContent = v; if (prev) { el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); } } };
    set('tCurrent', st.current_token); set('tAhead', st.ahead); set('tWait', st.status !== 'waiting' ? '0' : (st.ahead === 0 ? '<1' : `${st.wait_low}–${st.wait_high}`)); set('tCounter', st.counter);
    document.getElementById('tConf').textContent = st.status === 'waiting' ? st.wait_confidence + ' confidence' : '';
    document.getElementById('tExplain').textContent = 'Estimate, not a guarantee: ' + st.wait_explanation;
    document.getElementById('pausedNote').classList.toggle('d-none', !st.queue_paused); set('tWaiting', st.waiting);
    document.getElementById('tBar').style.width = Math.max(2, st.status === 'waiting' ? st.progress : 100) + '%';
    document.getElementById('haveMins').textContent = st.status === 'waiting' && st.ahead > 0 ? `${st.wait_low}–${st.wait_high} min` : 'a moment';
    document.querySelectorAll('.haveMins2').forEach(e => e.textContent = st.wait_minutes);
    document.getElementById('ptsNow').textContent = st.total_points;
    const pill = document.getElementById('statusPill'), banner = document.getElementById('callBanner');
    const map = { waiting: ['Waiting', 'waiting'], called: ['Called · Counter ' + st.counter, 'called'], serving: ['Being served', 'serving'], completed: ['Completed', 'done'], absent: ['Marked absent', 'done'], cancelled: ['Cancelled', 'done'], left: ['You left this queue', 'done'] };
    pill.textContent = map[st.status][0]; pill.className = 'pk-status ' + map[st.status][1];

    if (st.status === 'waiting' && st.ahead <= 3 && st.ahead > 0 && !notified.three) {
        notified.three = true;
        notify(`You are ${st.ahead} ${st.ahead === 1 ? 'person' : 'people'} away.`, `Please return to <b>Counter ${st.counter}</b>. Your estimated wait is now ${st.wait_minutes} minutes.`, `<span class="counter-badge">Counter ${st.counter}</span>`);
    }
    if (st.status === 'waiting' && st.ahead === 0 && !notified.next) {
        notified.next = true; notified.three = true;
        notify('You are next', 'Please wait near the counter — you will be called in moments.', `<div class="fs-5 mb-2">Token: <b>${st.token}</b></div><span class="counter-badge">Counter ${st.counter}</span>`);
        banner.classList.remove('d-none'); banner.innerHTML = `<span class="pk-icon"><i class="bi bi-bell-fill"></i></span><span>You are next. Token ${st.token}, please wait near Counter ${st.counter}.</span>`;
    }
    if (st.status === 'called' && !notified.called) {
        notified.called = true; notified.next = true;
        notify("It's your turn", `Token <b>${st.token}</b> — please go to the counter now with your documents.`, `<span class="counter-badge">→ Counter ${st.counter}</span>`);
        banner.classList.remove('d-none'); banner.innerHTML = `<span class="pk-icon"><i class="bi bi-megaphone-fill"></i></span><span>Now calling ${st.token}. Please go to Counter ${st.counter}.</span>`;
    }
    if (st.status === 'serving') { banner.classList.remove('d-none'); banner.innerHTML = `<span class="pk-icon"><i class="bi bi-person-check-fill"></i></span><span>You are being served at Counter ${st.counter}.</span>`; }
    if (st.status === 'completed' && !notified.done) { notified.done = true; finish(st); }
}
// Live updates by polling (read-only endpoint; never creates entries).
async function poll() { try { const r = await fetch(URLS.state, { headers: { Accept: 'application/json' } }); if (r.ok) render((await r.json()).data); } catch (e) {} }
async function staff(action) {
    if (!URLS[action]) return;
    const r = await post(URLS[action], action === 'verify' ? { result: 'verified' } : {});
    const j = await r.json().catch(() => ({}));
    if (!r.ok && j.message) alert(j.message);
    poll();
}
setInterval(() => { if (!document.hidden) poll(); }, 7000);
poll();

function beep() {
    // Shared bell chime (turn-alerts.js): softer at 3 away, brighter when next, repeated when called.
    if (window.pkChime) {
        const kind = !state ? 'next' : state.status === 'called' ? 'called' : (state.status === 'completed' ? 'test' : (state.ahead === 0 ? 'next' : 'three'));
        window.pkChime(kind);
        return;
    }
    try { const a = new AudioContext(); [0, .18].forEach(t => { const o = a.createOscillator(), g = a.createGain(); o.frequency.value = 880; o.connect(g); g.connect(a.destination); g.gain.setValueAtTime(.2, a.currentTime + t); g.gain.exponentialRampToValueAtTime(.001, a.currentTime + t + .15); o.start(a.currentTime + t); o.stop(a.currentTime + t + .16); }); } catch (e) {}
    navigator.vibrate && navigator.vibrate([200, 100, 200]);
}
function notify(title, body, extra) {
    saveNotified();
    document.getElementById('nTitle').innerHTML = title; document.getElementById('nBody').innerHTML = body; document.getElementById('nExtra').innerHTML = extra || '';
    document.getElementById('notif').classList.add('show'); beep();
}
function closeNotif() { document.getElementById('notif').classList.remove('show'); }
function confetti() {
    const cols = ['#2f5bea', '#7046e8', '#16a34a', '#f59e0b', '#94a9f6'];
    for (let i = 0; i < 60; i++) { const c = document.createElement('div'); c.className = 'confetti'; c.style.left = Math.random() * 100 + 'vw'; c.style.background = cols[i % cols.length]; c.style.animationDuration = 2 + Math.random() * 2 + 's'; c.style.animationDelay = Math.random() * .6 + 's'; document.body.appendChild(c); setTimeout(() => c.remove(), 5000); }
}
function finish(st) {
    saveNotified();
    closeNotif();
    document.getElementById('donePts').textContent = st.total_points;
    document.getElementById('doneStats').innerHTML = [
        ['⏳', st.initial_wait + ' min', 'reclaimed'], ['🎮', st.game_points, 'game points'], ['📄', st.docs_verified ? 'Verified' : '—', 'documents'],
    ].map(([e, v, l]) => `<div class="col-4"><div class="p-3 rounded-4" style="background:var(--pk-bg)"><div class="fs-4">${e}</div><div class="fw-bold fs-5">${v}</div><div class="pk-muted">${l}</div></div></div>`).join('');
    document.getElementById('doneScreen').classList.add('show'); confetti(); beep();
}

// ---------- Choices ----------
document.querySelectorAll('.choice').forEach(c => c.addEventListener('click', () => {
    document.querySelectorAll('.choice').forEach(x => x.classList.toggle('active', x === c));
    const p = c.dataset.panel;
    document.getElementById('panel-game').classList.toggle('d-none', p === 'explore');
    document.getElementById('panel-explore').classList.toggle('d-none', p !== 'explore');
    if (p === 'explore') openExplore(); else showPicker(p);
    (p === 'explore' ? document.getElementById('panel-explore') : document.getElementById('panel-game')).scrollIntoView({ behavior: 'smooth', block: 'start' });
}));

// ---------- Quiz game ----------
const QUESTIONS = [
    { q: 'What is the highest mountain in the world?', a: ['Mount Everest (Sagarmatha)', 'K2', 'Kangchenjunga', 'Lhotse'], c: 0 },
    { q: 'Nepal\'s flag is unique because it is…', a: ['Circular', 'Non-rectangular (two pennants)', 'Only one colour', 'Square'], c: 1 },
    { q: 'Which city is known as the "City of Lakes"?', a: ['Biratnagar', 'Bhaktapur', 'Pokhara', 'Butwal'], c: 2 },
    { q: 'Lumbini is famous as the birthplace of…', a: ['King Prithvi Narayan Shah', 'Gautama Buddha', 'Araniko', 'Bhanubhakta'], c: 1 },
    { q: 'What is the currency of Nepal?', a: ['Taka', 'Rupee', 'Rupiah', 'Ngultrum'], c: 1 },
    { q: 'How many people are typically ahead of you when प्रतीक्षा alerts you?', a: ['10', '7', '3', '0'], c: 2 },
    { q: 'Which festival is known as the festival of lights in Nepal?', a: ['Dashain', 'Tihar', 'Holi', 'Teej'], c: 1 },
    { q: 'Patan Durbar Square is located in which district?', a: ['Kathmandu', 'Bhaktapur', 'Lalitpur', 'Kavre'], c: 2 },
];
const BOTS = [
    { name: 'Sita', token: 'D-21', color: '#ff6b5b', skill: .75 },
    { name: 'Aarav', token: 'B-44', color: '#22c55e', skill: .6 },
    { name: 'Bikash', token: 'E-115', color: '#ffb020', skill: .5 },
];
let G = null;
function shuffle(a) { return a.map(v => [Math.random(), v]).sort((x, y) => x[0] - y[0]).map(v => v[1]); }
function startGame(compete) {
    clearInterval(G?.timer);
    G = { compete, qs: shuffle(QUESTIONS).slice(0, 6), i: 0, score: 0, correct: 0, players: compete ? BOTS.map(b => ({ ...b, score: 0 })) : [] };
    ['lobby', 'quiz', 'result'].forEach(id => document.getElementById(id).classList.add('d-none'));
    document.getElementById('lbCol').classList.toggle('d-none', !compete);
    if (compete) {
        const lob = document.getElementById('lobby'); lob.classList.remove('d-none');
        const lp = document.getElementById('lobbyPlayers'); lp.innerHTML = '';
        [{ name: 'You', token: T.token, color: '#4f3df5' }, ...BOTS].forEach((p, k) => setTimeout(() => {
            lp.insertAdjacentHTML('beforeend', `<div class="fade-up text-center"><div class="avatar mx-auto mb-1" style="width:52px;height:52px;background:${p.color}">${p.name[0]}</div><div class="small fw-bold">${p.name}</div><div class="small text-muted-ww">${p.token}</div></div>`);
        }, k * 450));
        setTimeout(() => { lob.classList.add('d-none'); showQ(); }, 2200);
    } else showQ();
}
function renderLB() {
    if (!G.compete) return;
    const rows = [{ name: 'You', token: T.token, color: '#4f3df5', score: G.score, me: true }, ...G.players].sort((a, b) => b.score - a.score);
    document.getElementById('lb').innerHTML = rows.map((p, k) => `<div class="lb-row ${p.me ? 'me' : ''}"><span class="fw-bold text-muted-ww" style="width:16px">${k + 1}</span><div class="avatar" style="background:${p.color}">${p.name[0]}</div><div class="flex-grow-1">${p.name}<div class="small text-muted-ww fw-normal">${p.token}</div></div><b>${p.score}</b></div>`).join('');
}
function showQ() {
    const q = G.qs[G.i]; let left = 10;
    document.getElementById('quiz').classList.remove('d-none');
    document.getElementById('qNum').textContent = `Question ${G.i + 1}/${G.qs.length}`;
    document.getElementById('qText').textContent = q.q;
    document.getElementById('qScore').textContent = G.score;
    document.getElementById('qTime').textContent = left;
    const bar = document.getElementById('qTimerBar'); bar.style.transition = 'none'; bar.style.width = '100%'; void bar.offsetWidth; bar.style.transition = 'width 1s linear';
    document.getElementById('qAnswers').innerHTML = q.a.map((a, k) => `<button class="ans fade-up" style="animation-delay:${k * .05}s" data-k="${k}"><span class="badge rounded-pill text-bg-light me-2">${'ABCD'[k]}</span>${a}</button>`).join('');
    document.querySelectorAll('.ans').forEach(b => b.onclick = () => answer(+b.dataset.k, left));
    renderLB();
    G.timer = setInterval(() => { left--; document.getElementById('qTime').textContent = left; bar.style.width = left * 10 + '%'; if (left <= 0) answer(-1, 0); }, 1000);
}
function answer(k, left) {
    clearInterval(G.timer);
    const q = G.qs[G.i];
    document.querySelectorAll('.ans').forEach(b => { b.disabled = true; if (+b.dataset.k === q.c) b.classList.add('right'); else if (+b.dataset.k === k) b.classList.add('wrong'); });
    if (k === q.c) { G.score += 100 + left * 10; G.correct++; }
    G.players.forEach(p => { if (Math.random() < p.skill) p.score += 100 + Math.floor(Math.random() * 8) * 10; });
    document.getElementById('qScore').textContent = G.score; renderLB();
    setTimeout(() => { G.i++; G.i < G.qs.length ? showQ() : endGame(); }, 1200);
}
async function endGame() {
    document.getElementById('quiz').classList.add('d-none');
    const rows = [{ name: 'You', score: G.score, me: true, color: '#4f3df5' }, ...G.players].sort((a, b) => b.score - a.score);
    const rank = rows.findIndex(r => r.me) + 1;
    const won = Math.round(G.score / 20) + (G.compete && rank === 1 ? 30 : 0);
    const r = document.getElementById('result'); r.classList.remove('d-none');
    r.innerHTML = `<div style="font-size:3.4rem">${!G.compete || rank === 1 ? '🏆' : rank === 2 ? '🥈' : '🎖️'}</div>
        <h3 class="h4">${G.compete ? (rank === 1 ? 'You won the hall!' : `You placed #${rank}`) : 'Quiz complete!'}</h3>
        <p class="text-muted-ww">${G.correct}/${G.qs.length} correct · ${G.score} score</p>
        ${G.compete ? `<div class="mx-auto mb-3" style="max-width:340px">${rows.map((p, k) => `<div class="lb-row ${p.me ? 'me' : ''}"><span>${['🥇', '🥈', '🥉', '4'][k]}</span><div class="avatar" style="background:${p.color}">${p.name[0]}</div><div class="flex-grow-1 text-start">${p.name}</div><b>${p.score}</b></div>`).join('')}</div>` : ''}
        <div class="points-pop mb-3" style="font-size:1.3rem">+${won} WaitPoints</div>
        <div class="d-flex justify-content-center gap-2 flex-wrap"><button class="btn btn-pk" onclick="startGame(${G.compete})">Play again</button><button class="btn btn-ghost" onclick="showPicker(G.compete ? 'compete' : 'play')">Other games</button><button class="btn btn-ghost" onclick="document.querySelector('[data-panel=explore]').click()">Explore nearby</button></div>`;
    if (!G.compete || rank === 1) confetti();
    { const r = await post(URLS.points, { points: won }); if (r.ok) render((await r.json()).data); }
}

// ---------- Explore ----------
let emap = null, markers = [], cat = 'All';
function openExplore() {
    if (!emap) {
        emap = L.map('exploreMap', { scrollWheelZoom: false }).setView([LOC.lat, LOC.lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(emap);
        L.marker([LOC.lat, LOC.lng], { icon: L.divIcon({ className: '', html: '<div class="map-pin">📍 Your queue</div>', iconSize: [100, 26], iconAnchor: [50, 13] }) }).addTo(emap);
        const cats = ['All', ...new Set(PLACES.map(p => p.category))];
        document.getElementById('catFilter').innerHTML = cats.map(c => `<button class="btn btn-outline-primary ${c === 'All' ? 'active' : ''}" data-c="${c}">${c}</button>`).join('');
        document.querySelectorAll('#catFilter button').forEach(b => b.onclick = () => { cat = b.dataset.c; document.querySelectorAll('#catFilter button').forEach(x => x.classList.toggle('active', x === b)); drawPlaces(); });
    }
    setTimeout(() => emap.invalidateSize(), 50);
    drawPlaces();
}
function drawPlaces() {
    const have = state ? state.wait_minutes : T.initial;
    markers.forEach(m => m.remove()); markers = [];
    const list = PLACES.filter(p => cat === 'All' || p.category === cat).map(p => ({ ...p, total: p.walk * 2 + p.visit })).map(p => ({ ...p, fits: p.total <= have - 5 }))
        .sort((a, b) => (b.fits - a.fits) || (b.rating - a.rating));
    document.getElementById('placeList').innerHTML = list.length ? list.map((p, k) => `
        <div class="place ${p.fits ? '' : 'nofit'}" data-k="${k}">
            <div class="d-flex gap-2 align-items-start">
                <div class="fs-3">${p.emoji}</div>
                <div class="flex-grow-1">
                    <div class="fw-bold">${p.name} ${k === 0 && p.fits ? '<span class="badge text-bg-primary">Top pick</span>' : ''}</div>
                    <div class="small text-muted-ww">${p.category} · ⭐ ${p.rating} · ${p.tip}</div>
                    <div class="small mt-1"><i class="bi bi-person-walking"></i> ${p.walk} min walk · <i class="bi bi-clock"></i> ${p.visit} min visit · <b>${p.total} min total</b></div>
                </div>
                <span class="pk-status ${p.fits ? 'called' : 'done'}">${p.fits ? 'Fits' : 'Too long'}</span>
            </div>
        </div>`).join('') : '<div class="text-center text-muted-ww py-4">No places in this category.</div>';
    list.forEach((p, k) => {
        const m = L.marker([p.lat, p.lng], { icon: L.divIcon({ className: '', html: `<div style="font-size:24px;filter:drop-shadow(0 2px 2px rgba(0,0,0,.3));${p.fits ? '' : 'opacity:.5'}">${p.emoji}</div>`, iconSize: [28, 28], iconAnchor: [14, 14] }) })
            .addTo(emap).bindPopup(`<b>${p.name}</b><br>${p.walk} min walk · ${p.visit} min visit<br>${p.fits ? '✅ Fits your wait' : '⏳ Not enough time'}`);
        markers.push(m);
    });
    document.querySelectorAll('.place').forEach(el => el.onclick = () => { const m = markers[+el.dataset.k]; emap.flyTo(m.getLatLng(), 17, { duration: .5 }); m.openPopup(); });
    if (markers.length) emap.fitBounds(L.featureGroup([...markers, L.marker([LOC.lat, LOC.lng])]).getBounds().pad(.2));
}
</script>
<script src="{{ asset('js/games.js') }}?v=2"></script>
@endpush
