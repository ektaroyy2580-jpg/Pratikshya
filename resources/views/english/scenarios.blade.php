@extends('layouts.app')
@section('title', 'Choose a scenario — English practice')

@push('head')
<link href="{{ asset('css/modules/english.css') }}?v={{ filemtime(public_path('css/modules/english.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php $s = $suggestion; @endphp
<header class="pk-page-head en-head">
    <div class="container">
        <a href="{{ route('english.home') }}" class="pk-breadcrumb d-inline-block mb-3 fade-up"><i class="bi bi-arrow-left me-1"></i>English practice</a>
        <h1 class="mb-2 fade-up" style="animation-delay:.05s">Choose a <span class="text-gradient">scenario</span></h1>
        <p class="lead mb-0 fade-up" style="animation-delay:.1s">{{ $s['message'] }}</p>
    </div>
</header>

<div class="container py-4 py-lg-5">
    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start mb-4" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <section class="pk-card p-4 mb-4 reveal" aria-labelledby="lenTitle">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="en-section-title" id="lenTitle"><i class="bi bi-stopwatch me-1"></i> Session length</h2>
            @if($s['ticket'])<span class="chip"><i class="bi bi-ticket-perforated"></i> {{ $s['ticket']->token() }} · at least {{ $s['wait_low'] }} min wait</span>@else<span class="small pk-muted">Not in a queue? You can still practise any time.</span>@endif
        </div>
        <div class="dur-group" role="radiogroup" aria-label="Session length">
            @foreach($s['options'] as $mins => $fits)
                <div class="dur-opt">
                    <input type="radio" name="dur_pick" id="dur{{ $mins }}" value="{{ $mins }}" @checked($mins === $minutes)>
                    <label for="dur{{ $mins }}">
                        <span class="n">{{ $mins }} <small>min</small></span>
                        <span class="t">Up to {{ config('english.prompts_per_duration.'.$mins) }} prompts</span>
                        @if($s['ticket'])
                            <span class="t {{ $fits ? 'fits' : 'long' }}"><i class="bi {{ $fits ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i> {{ $fits ? 'Fits your wait' : 'Longer than your wait' }}</span>
                        @elseif($mins === $s['suggested'])
                            <span class="t fits"><i class="bi bi-stars"></i> Suggested</span>
                        @endif
                    </label>
                </div>
            @endforeach
        </div>
    </section>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="filter-chips" role="toolbar" aria-label="Filter by type">
            <button type="button" data-filter="all" class="{{ $type === 'all' ? 'active' : '' }}" aria-pressed="{{ $type === 'all' ? 'true' : 'false' }}">All</button>
            @foreach(\App\Models\EnglishScenario::TYPES as $key => [$label, $icon])
                <button type="button" data-filter="{{ $key }}" class="{{ $type === $key ? 'active' : '' }}" aria-pressed="{{ $type === $key ? 'true' : 'false' }}"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</button>
            @endforeach
        </div>
        <span class="small pk-muted" id="scCount" aria-live="polite"></span>
    </div>

    @if($scenarios->isEmpty())
        <div class="pk-card p-5 text-center">
            <span class="pk-icon violet mb-2"><i class="bi bi-journal-x"></i></span>
            <p class="fw-bold mb-1">No scenarios available</p>
            <p class="small pk-muted mb-0">Run the English seeder to load the demo scenarios.</p>
        </div>
    @else
        <div class="row g-3" id="scGrid">
            @foreach($scenarios as $i => $sc)
                <div class="col-sm-6 col-lg-4 sc-item reveal" style="--d:{{ min($i, 6) * .04 }}s" data-type="{{ $sc->type }}" id="sc-{{ $sc->id }}">
                    <article class="pk-card lift sc-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="pk-icon {{ $sc->type === 'pronunciation' ? 'violet' : ($sc->type === 'interview' ? '' : ($sc->type === 'speaking_prompt' ? 'amber' : 'green')) }}"><i class="bi {{ $sc->icon }}"></i></span>
                            <span class="chip lvl-{{ $sc->level }}">{{ ucfirst($sc->level) }}</span>
                        </div>
                        <h3 class="sc-title">{{ $sc->title }}</h3>
                        <p class="sc-desc mb-3">{{ $sc->description }}</p>
                        <div class="sc-meta mb-3">
                            <span class="chip muted"><i class="bi {{ \App\Models\EnglishScenario::TYPES[$sc->type][1] ?? 'bi-chat' }}"></i> {{ $sc->typeLabel() }}</span>
                            <span class="chip muted">{{ $sc->promptCount() }} prompts</span>
                        </div>
                        <button type="button" class="btn btn-ghost w-100 js-start" data-id="{{ $sc->id }}" data-title="{{ $sc->title }}" data-type="{{ $sc->type }}" data-bs-toggle="modal" data-bs-target="#startModal">
                            Start practice <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </article>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Start modal: choose mode; voice requires the privacy notice --}}
<div class="modal fade" id="startModal" tabindex="-1" aria-labelledby="startTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0" style="border-radius:var(--pk-radius)" method="POST" action="{{ route('english.start') }}" id="startForm">
            @csrf
            <input type="hidden" name="scenario_id" id="fScenario" value="{{ old('scenario_id') }}">
            <input type="hidden" name="minutes" id="fMinutes" value="{{ $minutes }}">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="pk-eyebrow mb-1">Start practice</div>
                    <h2 class="modal-title h5" id="startTitle">Scenario</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small pk-muted mb-3"><i class="bi bi-stopwatch me-1"></i><span id="fMinLabel">{{ $minutes }}</span>-minute session. How would you like to answer?</p>
                <div class="row g-2 mb-3">
                    <div class="col-6 mode-opt">
                        <input type="radio" name="mode" id="mText" value="text" checked>
                        <label for="mText"><span class="pk-icon" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-keyboard"></i></span><span><b class="d-block">Type</b><span class="small pk-muted">Works everywhere</span></span></label>
                    </div>
                    <div class="col-6 mode-opt">
                        <input type="radio" name="mode" id="mVoice" value="voice">
                        <label for="mVoice"><span class="pk-icon violet" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-mic"></i></span><span><b class="d-block">Speak</b><span class="small pk-muted" id="voiceSupport">On-device speech recognition</span></span></label>
                    </div>
                </div>
                <div class="consent-box d-none" id="consentBox">
                    <div class="fw-bold"><i class="bi bi-shield-lock me-1 text-success"></i> Before we turn on the microphone</div>
                    <ul>
                        <li><b>Your audio is never recorded or stored</b>, and never sent to Pratikshya. Speech becomes text in your browser; only that text is sent, after you check it.</li>
                        <li><b>On-device (offline)</b>: recognised on your device; audio isn’t stored or uploaded. The speech model (~39 MB) downloads once from this site.</li>
                        <li>If you choose the <b>Browser</b> engine instead, some browsers (for example Chrome and Edge) send audio to their maker’s online speech service.</li>
                        <li>{{ $aiEnabled ? 'Transcripts are processed by the configured AI provider to generate feedback (emails and phone numbers are removed first).' : 'Transcripts are checked on this server by a rule-based coach; no AI provider is configured.' }}</li>
                        <li>You can switch back to typing at any time.</li>
                    </ul>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="consent_audio" value="1" id="consentAudio">
                        <label class="form-check-label fw-semibold" for="consentAudio">I understand and want to use the microphone</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-pk" id="startBtn">Start session <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    // On-device recognition (Vosk/WebAssembly) works without SR — e.g. in Brave and Firefox.
    const offline = !!(window.WebAssembly && (window.AudioContext || window.webkitAudioContext) && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.Worker);
    const voice = document.getElementById('mVoice');
    const consentBox = document.getElementById('consentBox');
    const consent = document.getElementById('consentAudio');
    const startBtn = document.getElementById('startBtn');
    if (!SR && !offline) {
        voice.disabled = true;
        document.getElementById('voiceSupport').textContent = 'Not supported in this browser';
    }

    // Duration picker → hidden field + URL (so a refresh keeps the choice).
    document.querySelectorAll('input[name="dur_pick"]').forEach(r => r.addEventListener('change', () => {
        document.getElementById('fMinutes').value = r.value;
        document.getElementById('fMinLabel').textContent = r.value;
        const u = new URL(location.href); u.searchParams.set('minutes', r.value); history.replaceState(null, '', u);
    }));

    // Type filter chips.
    const items = [...document.querySelectorAll('.sc-item')];
    const count = document.getElementById('scCount');
    const apply = type => {
        let n = 0;
        items.forEach(i => { const show = type === 'all' || i.dataset.type === type; i.classList.toggle('is-hidden', !show); if (show) { n++; i.classList.add('in'); } });
        if (count) count.textContent = n + ' scenario' + (n === 1 ? '' : 's');
        document.querySelectorAll('[data-filter]').forEach(b => { const on = b.dataset.filter === type; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on); });
    };
    document.querySelectorAll('[data-filter]').forEach(b => b.addEventListener('click', () => {
        apply(b.dataset.filter);
        const u = new URL(location.href); b.dataset.filter === 'all' ? u.searchParams.delete('type') : u.searchParams.set('type', b.dataset.filter); history.replaceState(null, '', u);
    }));
    apply(@json($type));

    // Modal setup.
    document.querySelectorAll('.js-start').forEach(b => b.addEventListener('click', () => {
        document.getElementById('fScenario').value = b.dataset.id;
        document.getElementById('startTitle').textContent = b.dataset.title;
    }));
    const sync = () => {
        const isVoice = voice.checked;
        consentBox.classList.toggle('d-none', !isVoice);
        startBtn.disabled = isVoice && !consent.checked;
    };
    document.querySelectorAll('input[name="mode"]').forEach(r => r.addEventListener('change', sync));
    consent.addEventListener('change', sync);
    document.getElementById('startForm').addEventListener('submit', () => { startBtn.disabled = true; startBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Starting…'; });
    sync();
})();
</script>
@endpush
