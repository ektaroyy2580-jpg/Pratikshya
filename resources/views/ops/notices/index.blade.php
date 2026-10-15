@extends('layouts.app')
@section('title', 'Public notices — Office operations — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $sevIcon = ['info' => 'bi-info-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'critical' => 'bi-exclamation-octagon-fill'];
    $tabs = ['live' => 'Live', 'scheduled' => 'Scheduled', 'draft' => 'Drafts', 'expired' => 'Expired'];
    $oldLoc = (int) old('location_id', $prefill['location_id']);
    $tree = $locations->mapWithKeys(fn ($l) => [$l->id => [
        'wide' => (bool) ($locationWide[$l->id] ?? false),
        'services' => $l->services->map(fn ($s) => ['id' => $s->id, 'name' => $s->prefix.' · '.$s->name])->values(),
        'counters' => $l->counters->sortBy('number')->map(fn ($c) => ['id' => $c->id, 'service_id' => $c->service_id, 'name' => 'Counter '.$c->number])->values(),
    ]]);
    $startIso = old('starts_at');
    $endIso = old('ends_at', now()->addHours(4)->toIso8601String());
@endphp
@include('ops.partials.console-head', ['active' => 'notices', 'title' => 'Public notices', 'subtitle' => 'Short, plain-language updates customers see live on their ticket and service pages.'])

<div class="container py-4 py-lg-5">
    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i>
            <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7 order-2 order-lg-1">
            <nav class="seg-tabs mb-3" aria-label="Notice status">
                @foreach($tabs as $k => $label)
                    <a href="{{ route('ops.notices.index', ['tab' => $k]) }}" class="{{ $tab === $k ? 'active' : '' }}" @if($tab === $k) aria-current="true" @endif>{{ $label }}<span class="n">{{ $grouped->get($k, collect())->count() }}</span></a>
                @endforeach
            </nav>

            <div class="d-grid gap-3">
                @forelse($notices as $a)
                    <article class="pk-card notice-card reveal" style="--d:{{ min($loop->index, 6) * .04 }}s">
                        <div class="notice-preview sev-{{ $a->severity }}">
                            <span class="ic"><i class="bi {{ $sevIcon[$a->severity] ?? 'bi-info-circle-fill' }}"></i></span>
                            <div class="min-w-0"><div class="tt">{{ $a->title }}</div><div class="mm">{{ $a->public_message }}</div></div>
                        </div>
                        <div class="meta">
                            <span><i class="bi bi-geo-alt me-1"></i>{{ $a->location->name }}</span>
                            <span><i class="bi {{ $a->isLocationWide() ? 'bi-building' : 'bi-bullseye' }} me-1"></i>{{ $a->scopeLabel() }}</span>
                            @if($a->starts_at)<span><i class="bi bi-play-circle me-1"></i><time class="js-local" datetime="{{ $a->starts_at->toIso8601String() }}">{{ $a->starts_at->format('d M H:i') }} UTC</time></span>@endif
                            <span><i class="bi bi-stop-circle me-1"></i>@if($a->ends_at)<time class="js-local" datetime="{{ $a->ends_at->toIso8601String() }}">{{ $a->ends_at->format('d M H:i') }} UTC</time>@else Until taken down @endif</span>
                            @if($a->publisher)<span><i class="bi bi-person me-1"></i>{{ $a->publisher->name }}</span>@endif
                            @if($a->operational_incident_id)<a href="{{ route('ops.incidents.show', $a->operational_incident_id) }}"><i class="bi bi-link-45deg"></i>INC-{{ str_pad($a->operational_incident_id, 4, '0', STR_PAD_LEFT) }}</a>@endif
                        </div>
                        @can('manage', $a)
                            <div class="d-flex flex-wrap gap-2">
                                @if($a->status === 'draft')
                                    <form method="POST" action="{{ route('ops.notices.publish', $a) }}">@csrf<button class="btn btn-pk btn-sm"><i class="bi bi-broadcast me-1"></i>Publish now</button></form>
                                @endif
                                @if(in_array($a->displayStatus(), ['live', 'scheduled', 'draft'], true))
                                    <form method="POST" action="{{ route('ops.notices.expire', $a) }}" class="js-confirm" data-confirm="Take down “{{ $a->title }}”? Customers will stop seeing it.">@csrf<button class="btn btn-ghost btn-sm"><i class="bi bi-eye-slash me-1"></i>{{ $a->status === 'draft' ? 'Discard' : 'Take down' }}</button></form>
                                @endif
                            </div>
                        @endcan
                    </article>
                @empty
                    <div class="pk-card ops-empty">
                        <span class="pk-icon mb-3"><i class="bi bi-megaphone"></i></span>
                        <h2 class="h5 mb-1">No {{ strtolower($tabs[$tab]) }} notices</h2>
                        <p class="pk-muted mb-0">{{ $tab === 'live' ? 'Customers aren’t seeing any notices right now.' : 'Nothing in this list.' }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-lg-5 order-1 order-lg-2">
            <section class="pk-card p-4 reveal" aria-labelledby="newHead">
                <h2 id="newHead" class="h6 fw-bold mb-1 d-flex align-items-center gap-2"><i class="bi bi-pencil-square pk-muted"></i> New notice</h2>
                <p class="small pk-muted mb-3">Plain text only, max 280 characters. Never include personal data.</p>
                @if($locations->isEmpty())
                    <div class="small pk-muted">You have no assigned locations yet.</div>
                @else
                <form method="POST" action="{{ route('ops.notices.store') }}" class="js-notice-form" id="noticeForm">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label" for="nLoc">Location</label>
                        <select id="nLoc" name="location_id" class="form-select">
                            @foreach($locations as $l)<option value="{{ $l->id }}" @selected($oldLoc === $l->id)>{{ $l->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-7">
                            <label class="form-label" for="nSvc">Queue</label>
                            <select id="nSvc" name="service_id" class="form-select" data-selected="{{ old('service_id', $prefill['service_id']) }}"></select>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="nCtr">Counter</label>
                            <select id="nCtr" name="counter_id" class="form-select" data-selected="{{ old('counter_id') }}"></select>
                        </div>
                    </div>
                    <div class="small pk-muted mb-3 d-none" id="wideHint"><i class="bi bi-lock me-1"></i>Whole-location notices need an admin or the location-notice permission.</div>
                    <div class="mb-2">
                        <label class="form-label" for="nTitle">Title</label>
                        <input id="nTitle" name="title" class="form-control js-n-title" maxlength="80" required value="{{ old('title') }}" placeholder="Counter 3 unavailable">
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-end"><label class="form-label" for="nMsg">Message</label><span class="char-count js-n-count" aria-live="polite"></span></div>
                        <textarea id="nMsg" name="public_message" rows="3" class="form-control js-n-msg" maxlength="280" required placeholder="Counter 3 is temporarily unavailable. Please proceed to Counter 2.">{{ old('public_message') }}</textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label" for="nSev">Severity</label>
                            <select id="nSev" name="severity" class="form-select js-n-sev">
                                @foreach(\App\Models\ServiceAnnouncement::SEVERITIES as $s)<option value="{{ $s }}" @selected(old('severity', 'warning') === $s)>{{ ucfirst($s) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="nStart">Starts <span class="pk-muted fw-normal">(blank = now)</span></label>
                            <input id="nStart" type="datetime-local" class="form-control js-local-input" data-target="nStartIso" data-iso="{{ $startIso }}">
                            <input type="hidden" name="starts_at" id="nStartIso" value="{{ $startIso }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="nEnd">Ends</label>
                            <input id="nEnd" type="datetime-local" class="form-control js-local-input" data-target="nEndIso" data-iso="{{ $endIso }}">
                            <input type="hidden" name="ends_at" id="nEndIso" value="{{ $endIso }}">
                        </div>
                    </div>
                    <div class="small fw-bold pk-muted mb-2">Preview</div>
                    <div class="notice-preview sev-warning mb-3 js-n-preview" aria-hidden="true">
                        <span class="ic"><i class="bi bi-exclamation-triangle-fill"></i></span>
                        <div class="min-w-0"><div class="tt js-p-title"></div><div class="mm js-p-msg"></div></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-pk flex-grow-1" name="publish" value="1"><i class="bi bi-broadcast me-1"></i>Publish</button>
                        <button class="btn btn-ghost" name="publish" value="0">Save draft</button>
                    </div>
                    <div class="small pk-muted mt-2"><i class="bi bi-bell me-1"></i>Publishing notifies customers who currently hold a token in the affected queue (once each).</div>
                </form>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const tree = @json($tree);
    const loc = document.getElementById('nLoc'), svc = document.getElementById('nSvc'), ctr = document.getElementById('nCtr'), hint = document.getElementById('wideHint');
    if (!loc) return;
    const fill = () => {
        const t = tree[loc.value] || { services: [], counters: [], wide: false };
        const want = svc.dataset.selected, wantC = ctr.dataset.selected;
        svc.innerHTML = ''; ctr.innerHTML = '';
        const whole = new Option(t.wide ? 'Whole location (all queues)' : 'Whole location — needs permission', '');
        whole.disabled = !t.wide; svc.add(whole);
        t.services.forEach(s => svc.add(new Option(s.name, s.id, false, String(s.id) === want)));
        if (!svc.value && !t.wide && t.services.length) svc.value = t.services[0].id;
        ctr.add(new Option('Any counter', ''));
        t.counters.forEach(c => ctr.add(new Option(c.name, c.id, false, String(c.id) === wantC)));
        hint.classList.toggle('d-none', t.wide);
    };
    loc.addEventListener('change', () => { svc.dataset.selected = ''; ctr.dataset.selected = ''; fill(); });
    ctr.addEventListener('change', () => {
        const c = (tree[loc.value]?.counters || []).find(c => String(c.id) === ctr.value);
        if (c && c.service_id) svc.value = c.service_id;
    });
    fill();
})();
</script>
<script src="{{ asset('js/modules/ops-console.js') }}?v={{ filemtime(public_path('js/modules/ops-console.js')) }}"></script>
@endpush
