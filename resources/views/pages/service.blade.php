@extends('layouts.app')
@section('title', $service->name.' — प्रतीक्षा')

@push('head')
<style>
    .min-w-0 { min-width: 0; }
    .svc-head h1 { font-size: clamp(1.8rem, 4.2vw, 2.7rem); line-height: 1.08; }
    .svc-head .meta { display: flex; flex-wrap: wrap; gap: .4rem 1.1rem; color: var(--pk-ink-2); font-weight: 600; font-size: .92rem; }
    .svc-head .meta i { color: var(--pk-muted); }
    .crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .crumbs .sep { color: #b6bcd3; font-size: .7rem; }

    .stat-tile { background: #fff; border: 1px solid var(--pk-line); border-radius: 16px; padding: 1rem 1.1rem; height: 100%; box-shadow: var(--pk-shadow-sm); }
    .stat-tile .l { font-size: .74rem; font-weight: 700; color: var(--pk-muted); text-transform: uppercase; letter-spacing: .05em; display: flex; align-items: center; gap: .4rem; }
    .stat-tile .v { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 800; letter-spacing: -.03em; line-height: 1.1; margin-top: .45rem; white-space: nowrap; }
    .stat-tile .v small { font-size: .9rem; font-weight: 700; color: var(--pk-muted); letter-spacing: 0; }
    .stat-tile.accent { background: var(--pk-grad); border-color: transparent; color: #fff; box-shadow: 0 14px 30px -16px rgba(47, 91, 234, .8); }
    .stat-tile.accent .l { color: rgba(255, 255, 255, .8); }

    .section-title { font-size: 1.05rem; font-weight: 800; letter-spacing: -.015em; margin: 0; }

    /* Queue strip */
    .q-strip { display: flex; flex-wrap: wrap; gap: 6px; }
    .q-tok { min-width: 40px; height: 34px; padding: 0 .4rem; border-radius: 10px; display: grid; place-items: center; background: var(--pk-bg); border: 1px solid var(--pk-line); color: var(--pk-muted); font-size: .72rem; font-weight: 800; }
    .q-tok.serving { background: var(--pk-success); border-color: var(--pk-success); color: #fff; }
    .q-tok.you { background: var(--pk-grad); border-color: transparent; color: #fff; box-shadow: 0 6px 14px -6px rgba(47, 91, 234, .7); }
    .q-tok.more { background: transparent; border-style: dashed; }
    .q-legend { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .78rem; color: var(--pk-muted); font-weight: 600; }
    .q-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; margin-right: .3rem; vertical-align: -1px; }

    /* Document checklist */
    .doc-row { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; padding: .85rem 1rem; border: 1px solid var(--pk-line); border-radius: 14px; background: #fff; transition: border-color .2s, background .2s; }
    .doc-row .doc-ico { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; background: var(--pk-danger-tint); color: var(--pk-danger); font-size: .95rem; flex-shrink: 0; transition: background .2s, color .2s; }
    .doc-row[data-state="available"] { border-color: #bfe6cd; background: #f6fcf8; }
    .doc-row[data-state="available"] .doc-ico { background: var(--pk-success-tint); color: var(--pk-success); }
    .doc-row[data-state="needs_review"] { border-color: #f6dca8; background: #fffcf4; }
    .doc-row[data-state="needs_review"] .doc-ico { background: var(--pk-warn-tint); color: var(--pk-warn); }
    .doc-name { font-weight: 700; flex: 1 1 180px; min-width: 0; display: flex; align-items: center; gap: .7rem; }
    .seg { display: inline-flex; background: #f1f3f9; border-radius: 11px; padding: 3px; gap: 2px; position: relative; }
    .seg input { position: absolute; opacity: 0; pointer-events: none; width: 1px; height: 1px; }
    .seg label { padding: .38rem .7rem; border-radius: 8px; font-size: .78rem; font-weight: 700; cursor: pointer; color: var(--pk-muted); transition: background .15s, color .15s, box-shadow .15s; white-space: nowrap; user-select: none; }
    .seg label:hover { color: var(--pk-ink); }
    .seg input:focus-visible + label { outline: 3px solid rgba(47, 91, 234, .45); outline-offset: 1px; }
    .seg input:checked + label { background: #fff; box-shadow: var(--pk-shadow-sm); }
    .seg input:checked + label.ok { color: var(--pk-success); }
    .seg input:checked + label.miss { color: var(--pk-danger); }
    .seg input:checked + label.rev { color: var(--pk-warn); }
    @media (max-width: 575.98px) { .seg { width: 100%; } .seg label { flex: 1; text-align: center; } }

    .ready-bar { height: 8px; }
    .ready-bar .progress-bar { transition: width .4s var(--pk-ease); }
    .doc-msg { border-radius: 12px; padding: .7rem .9rem; font-size: .85rem; font-weight: 600; }
    .doc-msg.ok { background: var(--pk-success-tint); color: #116a35; }
    .doc-msg.warn { background: var(--pk-warn-tint); color: #8a4b04; }

    /* Join card */
    @media (min-width: 992px) { .join-sticky { position: sticky; top: 88px; } }
    .join-token { font-size: 2.6rem; font-weight: 800; letter-spacing: -.04em; line-height: 1; }
    .kv { display: flex; justify-content: space-between; align-items: center; padding: .55rem 0; font-size: .9rem; }
    .kv + .kv { border-top: 1px solid #f0f1f7; }
    .kv .k { color: var(--pk-muted); font-weight: 600; }
    .kv .v { font-weight: 800; }
    .tip { display: flex; gap: .75rem; align-items: flex-start; background: var(--pk-bg); border-radius: 14px; padding: .85rem .95rem; font-size: .85rem; color: var(--pk-ink-2); }
</style>
@endpush

@section('content')
@php
    $ahead = $service->waitingCount();
    $myToken = $service->token($service->last_number + 1);
    $docs = $service->documents ?? [];
    $oldDocs = old('docs', []);
    $warn = session('warn_missing');
    $stripEnd = min($service->last_number + 1, $service->current_number + 40);
    $stripShown = min($stripEnd, $service->current_number + 22);
    $youNumber = $active ? null : $service->last_number + 1;
    $youTail = $youNumber && $youNumber > $stripShown && $youNumber == $stripEnd;
    $hidden = $stripEnd - $stripShown - ($youTail ? 1 : 0);
@endphp

<header class="pk-page-head svc-head">
    <div class="container">
        <nav class="crumbs mb-3 fade-up" aria-label="Breadcrumb">
            <a href="{{ route('find') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Find a service</a>
            <i class="bi bi-chevron-right sep" aria-hidden="true"></i>
            <span class="pk-breadcrumb text-truncate" style="max-width:60vw">{{ $service->location->name }}</span>
        </nav>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 fade-up" style="animation-delay:.04s">
            @if($service->is_paused)
                <span class="pk-status waiting">Paused</span>
            @else
                <span class="chip" style="background:var(--pk-success-tint);color:var(--pk-success)"><span class="live-dot"></span> Live queue</span>
            @endif
            <span class="chip"><i class="bi {{ $service->location->icon }}"></i> {{ $service->location->type }}</span>
        </div>
        <h1 class="mb-2 fade-up" style="animation-delay:.08s">{{ $service->name }}</h1>
        <div class="meta mb-4 fade-up" style="animation-delay:.12s">
            <span><i class="bi bi-geo-alt me-1"></i>{{ $service->location->name }}, {{ $service->location->area }}</span>
            <span><i class="bi bi-door-open me-1"></i>Counter {{ $service->counter }}</span>
            <span><i class="bi bi-stopwatch me-1"></i>~{{ $service->avg_minutes }} min per person</span>
        </div>

        @if($service->is_paused)
            <div class="alert alert-warning d-flex gap-2 align-items-start mb-4 fade-up" role="status">
                <i class="bi bi-pause-circle-fill fs-5"></i>
                <div><b>This queue is paused by staff right now.</b> You can still join; waits may be longer than shown.</div>
            </div>
        @endif

        <div class="row g-2 g-md-3">
            <div class="col-6 col-lg-3 reveal"><div class="stat-tile"><div class="l"><span class="live-dot"></span> Now serving</div><div class="v">{{ $service->token($service->current_number) }}</div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.06s"><div class="stat-tile"><div class="l"><i class="bi bi-people"></i> People waiting</div><div class="v">{{ $ahead }}</div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.12s"><div class="stat-tile accent"><div class="l"><i class="bi bi-ticket-perforated"></i> {{ $active ? 'Your token' : 'Your token would be' }}</div><div class="v">{{ $active ? $active->token() : $myToken }}</div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.18s"><div class="stat-tile"><div class="l"><i class="bi bi-hourglass-split"></i> Estimated wait</div><div class="v">{{ $estimate['low'] }}–{{ $estimate['high'] }} <small>min</small></div></div></div>
        </div>
        <p class="small pk-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Estimate, not a guarantee: {{ $estimate['explanation'] }} Confidence: <b>{{ $estimate['confidence'] }}</b>.</p>
    </div>
</header>

<div class="container py-4 py-lg-5">
    @includeIf('ops.partials.announcement-banner', ['service' => $service])
    @includeIf('explore.partials.hospital-banner', ['location' => $service->location])
    @if($active)
        <div class="pk-card d-flex flex-wrap align-items-center gap-3 p-3 p-md-4 mb-4 fade-up" style="border-color:#cdd6ff;background:var(--pk-tint)">
            <span class="pk-icon"><i class="bi bi-ticket-perforated-fill"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold">You already hold {{ $active->token() }} for this service.</div>
                <div class="small pk-muted">One active token per person keeps the queue fair.</div>
            </div>
            <a class="btn btn-pk" href="{{ route('ticket', $active) }}">Open my token <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    @endif
    @error('queue')<div class="alert alert-danger d-flex gap-2 align-items-start mb-4" role="alert"><i class="bi bi-x-octagon-fill"></i><div>{{ $message }}</div></div>@enderror

    <form method="POST" action="{{ route('join', $service) }}" id="joinForm">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

        <div class="row g-4">
            <div class="col-lg-7 col-xl-8 d-grid gap-4 align-content-start">
                {{-- Queue strip --}}
                <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="queueTitle">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h2 class="section-title" id="queueTitle">Current queue</h2>
                            <p class="small pk-muted mb-0">Each tile is a token, in the order it was issued.</p>
                        </div>
                        <span class="chip flex-shrink-0">{{ $ahead }} ahead</span>
                    </div>
                    <div class="q-strip mb-3" aria-label="Tokens from {{ $service->token($service->current_number) }} onward">
                        @for($n = $service->current_number; $n <= $stripShown; $n++)
                            <div class="q-tok {{ $n == $service->current_number ? 'serving' : '' }} {{ $youNumber === $n ? 'you' : '' }}" title="{{ $service->token($n) }}">{{ $youNumber === $n ? 'You' : $n }}</div>
                        @endfor
                        @if($hidden > 0)<div class="q-tok more" title="{{ $hidden }} more">+{{ $hidden }}</div>@endif
                        @if($youTail)<div class="q-tok you" title="{{ $myToken }}">You</div>@endif
                    </div>
                    <div class="q-legend">
                        <span><i style="background:var(--pk-success)"></i>At the counter</span>
                        <span><i style="background:var(--pk-bg);border:1px solid var(--pk-line)"></i>Waiting</span>
                        @unless($active)<span><i style="background:var(--pk-grad)"></i>Your place if you join now</span>@endunless
                    </div>
                </section>

                {{-- Document checklist --}}
                <section class="pk-card p-3 p-md-4 reveal" style="--d:.06s" aria-labelledby="docTitle">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-1">
                        <h2 class="section-title" id="docTitle">Document checklist</h2>
                        <span class="chip flex-shrink-0" id="docBadge">0 / {{ count($docs) }} ready</span>
                    </div>
                    <p class="small pk-muted mb-3">Mark each document honestly. Staff check the originals at the counter; marking it here does not verify it.</p>
                    <div class="progress ready-bar mb-4" role="progressbar" aria-label="Document readiness" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="docProgress"><div class="progress-bar" id="docBar" style="width:0%"></div></div>

                    <div class="d-grid gap-2 mb-3">
                        @forelse($docs as $i => $d)
                            @php $val = $oldDocs[$d] ?? 'missing'; @endphp
                            <div class="doc-row" data-state="{{ $val }}">
                                <div class="doc-name"><span class="doc-ico" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span><span>{{ $d }}</span></div>
                                <div class="seg" role="radiogroup" aria-label="{{ $d }}">
                                    <input type="radio" id="d{{ $i }}a" name="docs[{{ $d }}]" value="available" @checked($val === 'available')><label for="d{{ $i }}a" class="ok"><i class="bi bi-check-lg"></i> Have it</label>
                                    <input type="radio" id="d{{ $i }}m" name="docs[{{ $d }}]" value="missing" @checked($val === 'missing')><label for="d{{ $i }}m" class="miss">Missing</label>
                                    <input type="radio" id="d{{ $i }}r" name="docs[{{ $d }}]" value="needs_review" @checked($val === 'needs_review')><label for="d{{ $i }}r" class="rev">Not sure</label>
                                </div>
                            </div>
                        @empty
                            <div class="small pk-muted">No documents are needed for this service.</div>
                        @endforelse
                    </div>

                    <div class="doc-msg" id="docMsg" aria-live="polite"></div>

                    @if($service->requires_all_documents)
                        <p class="small text-danger fw-semibold mt-3 mb-0"><i class="bi bi-shield-exclamation"></i> This service requires every document before joining.</p>
                    @endif
                </section>
            </div>

            {{-- Join card --}}
            <div class="col-lg-5 col-xl-4">
                <aside class="join-sticky">
                    <div class="pk-card p-4 reveal" style="--d:.1s">
                        <div class="pk-eyebrow mb-2">{{ $active ? 'Your token' : 'Join remotely' }}</div>
                        <div class="d-flex align-items-end justify-content-between gap-2 mb-3">
                            <div class="join-token text-gradient">{{ $active ? $active->token() : $myToken }}</div>
                            <span class="small pk-muted fw-semibold text-end">Counter {{ $service->counter }}</span>
                        </div>
                        <div class="mb-3">
                            <div class="kv"><span class="k">People ahead</span><span class="v">{{ $ahead }}</span></div>
                            <div class="kv"><span class="k">Estimated wait</span><span class="v">{{ $estimate['low'] }}–{{ $estimate['high'] }} min</span></div>
                            <div class="kv"><span class="k">Documents ready</span><span class="v" data-doc-count>0 / {{ count($docs) }}</span></div>
                        </div>

                        @if($warn)
                            <div class="alert alert-warning small fade-up" role="alert">
                                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> {{ count($warn) }} document(s) not marked as available:</div>
                                <ul class="mb-2 ps-3">@foreach($warn as $w)<li>{{ $w }}</li>@endforeach</ul>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="confirm_missing" value="1" id="confirmMissing" required>
                                    <label class="form-check-label" for="confirmMissing">I understand the counter may not complete my service without these, and I want to join anyway.</label>
                                </div>
                            </div>
                        @endif

                        <div class="d-grid mb-3">
                            @if($active)
                                <a class="btn btn-pk btn-lg" href="{{ route('ticket', $active) }}"><i class="bi bi-ticket-perforated-fill me-1"></i> Open my token {{ $active->token() }}</a>
                            @else
                                <button class="btn btn-pk btn-lg" id="joinBtn"><i class="bi bi-ticket-perforated-fill me-1"></i> Join queue · get {{ $myToken }}</button>
                            @endif
                        </div>

                        <div class="tip">
                            <span class="pk-icon amber" style="width:34px;height:34px;font-size:1rem"><i class="bi bi-bell"></i></span>
                            <span>No need to stand here for {{ $estimate['low'] }}–{{ $estimate['high'] }} minutes. We'll alert you when you're <b>3 people away</b>.</span>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </form>
    <div class="mt-4">@includeIf('rides.partials.service-widget', ['service' => $service])</div>
</div>
@endsection

@push('scripts')
<script>
const rows = [...document.querySelectorAll('.doc-row')];
function refresh() {
    const total = rows.length;
    const on = rows.filter(r => r.dataset.state === 'available').length;
    const pct = total ? Math.round(on / total * 100) : 100;
    document.getElementById('docBar').style.width = pct + '%';
    document.getElementById('docProgress').setAttribute('aria-valuenow', pct);
    document.getElementById('docBadge').textContent = `${on} / ${total} ready`;
    document.querySelectorAll('[data-doc-count]').forEach(el => el.textContent = `${on} / ${total}`);
    const msg = document.getElementById('docMsg');
    if (on === total) {
        msg.className = 'doc-msg ok';
        msg.innerHTML = '<i class="bi bi-patch-check-fill"></i> All documents marked ready. Staff will verify the originals at the counter.';
    } else {
        msg.className = 'doc-msg warn';
        msg.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${total - on} document${total - on > 1 ? 's' : ''} not ready. You can still join and collect them while you wait.`;
    }
}
rows.forEach(r => r.addEventListener('change', e => { r.dataset.state = e.target.value; refresh(); }));
refresh();
document.getElementById('joinForm').addEventListener('submit', e => {
    const b = document.getElementById('joinBtn');
    if (!b || !e.target.checkValidity()) return;
    b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating your token…';
});
</script>
@endpush
