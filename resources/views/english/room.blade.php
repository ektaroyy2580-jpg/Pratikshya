@extends('layouts.app')
@section('title', $scenario->title.' — English practice')

@push('head')
<link href="{{ asset('css/modules/english.css') }}?v={{ filemtime(public_path('css/modules/english.css')) }}" rel="stylesheet">
@endpush

@section('content')
<header class="pk-page-head room-head">
    <div class="container">
        <a href="{{ route('english.home') }}" class="pk-breadcrumb d-inline-block mb-2"><i class="bi bi-arrow-left me-1"></i>English practice</a>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span class="pk-icon d-none d-sm-grid {{ $scenario->isPronunciation() ? 'violet' : '' }}"><i class="bi {{ $scenario->icon }}"></i></span>
                <div class="min-w-0">
                    <h1 class="mb-1 text-truncate">{{ $scenario->title }}</h1>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="chip muted">{{ $scenario->typeLabel() }}</span>
                        <span class="chip lvl-{{ $scenario->level }}">{{ ucfirst($scenario->level) }}</span>
                        <span class="chip muted"><i class="bi bi-cpu"></i> {{ $aiEnabled ? 'AI tutor' : 'Rule-based coach' }}</span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="timer-pill" id="timer" aria-live="off" title="Time practised / target"><i class="bi bi-stopwatch"></i> <span id="timerText">0:00</span> / {{ $session->duration_target_minutes }}:00</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 mt-3">
            <div class="progress room-progress flex-grow-1" role="progressbar" aria-label="Prompts answered" aria-valuemin="0" aria-valuemax="{{ $session->plannedCount() }}" aria-valuenow="{{ $session->turns->count() }}" id="progWrap">
                <div class="progress-bar" id="progBar" style="width: {{ $session->plannedCount() ? round($session->turns->count() / $session->plannedCount() * 100) : 0 }}%"></div>
            </div>
            <span class="small fw-bold text-nowrap" id="progText">{{ $session->turns->count() }} / {{ $session->plannedCount() }}</span>
        </div>
    </div>
</header>

<div class="container py-4">
    <div class="alert alert-warning d-none align-items-start gap-2 mb-3" id="compatBanner" role="status">
        <i class="bi bi-info-circle-fill"></i>
        <div><b>Speech recognition isn’t available in this browser.</b> You can still practise fully by typing. Voice practice needs a recent Brave, Chrome, Edge, Firefox or Safari with microphone access.</div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="pk-card chat-card" aria-label="Conversation">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 px-md-4 pt-3 pb-2 border-bottom" style="border-color:#f0f1f7 !important">
                    <div class="mode-switch" role="group" aria-label="Answer mode">
                        <button type="button" data-mode="text" id="modeText"><i class="bi bi-keyboard me-1"></i>Type</button>
                        <button type="button" data-mode="voice" id="modeVoice"><i class="bi bi-mic me-1"></i>Speak</button>
                    </div>
                    <div class="form-check form-switch mb-0 small fw-semibold">
                        <input class="form-check-input" type="checkbox" role="switch" id="readAloud">
                        <label class="form-check-label" for="readAloud"><i class="bi bi-volume-up me-1"></i>Read prompts aloud</label>
                    </div>
                </div>

                <div class="chat-thread" id="thread" aria-live="polite" aria-relevant="additions"></div>

                <form class="composer" id="composer" autocomplete="off" novalidate>
                    <div class="voice-bar d-none" id="voiceBar">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div class="engine-switch" role="group" aria-label="Speech recognition engine">
                                <button type="button" id="engOnDevice" data-engine="ondevice"><i class="bi bi-shield-lock me-1"></i>On-device (offline)</button>
                                <button type="button" id="engBrowser" data-engine="browser"><i class="bi bi-globe2 me-1"></i>Browser</button>
                            </div>
                            <span class="engine-note" id="engineNote"></span>
                        </div>
                        <div class="model-progress d-none" id="modelProgress">
                            <div class="d-flex justify-content-between gap-2 small fw-semibold mb-1"><span id="modelProgressLabel">Loading the offline speech engine…</span><span id="modelProgressPct" class="text-nowrap">0%</span></div>
                            <div class="progress" role="progressbar" aria-label="Offline speech model" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="modelProgressWrap"><div class="progress-bar" id="modelProgressBar" style="width:0%"></div></div>
                        </div>
                        <div class="mic-help d-none" id="micHelp" role="alert"></div>
                        <div class="d-flex align-items-center gap-2 mb-2 mic-line">
                            <span class="level-meter" id="levelMeter" hidden aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                            <div class="mic-status" id="micStatus" aria-live="polite"></div>
                        </div>
                    </div>
                    <div class="cmp-row">
                        <button type="button" class="mic-btn d-none" id="micBtn" data-state="idle" aria-label="Start speaking"><i class="bi bi-mic-fill"></i></button>
                        <div class="flex-grow-1">
                            <label for="answer" class="visually-hidden">Your answer</label>
                            <textarea class="form-control" id="answer" rows="1" maxlength="{{ config('english.max_turn_chars', 1200) }}" placeholder="Type your answer in English…"></textarea>
                        </div>
                        <button type="submit" class="btn btn-pk send-btn" id="sendBtn" aria-label="Send answer"><i class="bi bi-send-fill"></i><span class="d-none d-sm-inline ms-1">Send</span></button>
                    </div>
                    <div class="invalid-feedback d-block small mt-1" id="answerError" role="alert"></div>
                </form>
            </section>
        </div>

        <aside class="col-lg-4 d-grid gap-3 align-content-start">
            <div class="pk-card p-4">
                <h2 class="en-section-title mb-2">Target vocabulary</h2>
                <p class="small pk-muted mb-2">Try to use these words. They turn green when you do.</p>
                <div class="d-flex flex-wrap gap-2" id="vocab">
                    @forelse($scenario->target_vocabulary ?? [] as $w)
                        <span class="vocab-chip" data-word="{{ mb_strtolower($w) }}">{{ $w }}</span>
                    @empty
                        <span class="small pk-muted">None for this scenario.</span>
                    @endforelse
                </div>
            </div>
            <div class="pk-card p-4">
                <h2 class="en-section-title mb-2">Finish when you’re ready</h2>
                <p class="small pk-muted mb-3">You can end early — your summary includes everything you answered.</p>
                <form method="POST" action="{{ route('english.complete', $session) }}" id="finishForm">
                    @csrf
                    <button class="btn btn-ghost w-100" type="submit"><i class="bi bi-flag me-1"></i>Finish &amp; see summary</button>
                </form>
            </div>
            <div class="pk-card p-4 privacy-card small">
                <div class="fw-bold mb-1"><i class="bi bi-shield-lock text-success me-1"></i>Privacy</div>
                <p class="pk-muted mb-0">Audio is never recorded or stored. In voice mode speech becomes text in your browser — on-device (offline) or via the browser’s own speech service — and only that text is sent. {{ $aiEnabled ? 'The configured AI provider processes the text for feedback.' : 'Feedback is generated on this server; no AI provider is configured.' }}</p>
            </div>
        </aside>
    </div>
</div>

{{-- Microphone consent (shown before the mic is enabled for the first time) --}}
<div class="modal fade" id="consentModal" tabindex="-1" aria-labelledby="consentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:var(--pk-radius)">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="consentTitle"><i class="bi bi-mic me-1"></i> Use your microphone?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="consent-box">
                    <ul class="mb-2">
                        <li><b>Your audio is never recorded or stored</b>, and never sent to Pratikshya. Speech becomes text in your browser; only that text is sent, after you check it.</li>
                        <li><b>On-device (offline)</b>: recognised on your device; audio isn’t stored or uploaded. The speech model (~39 MB) downloads once from this site.</li>
                        <li><b>Browser</b>: some browsers (for example Chrome and Edge) send audio to their maker’s online speech service. You can switch to on-device any time.</li>
                        <li>{{ $aiEnabled ? 'Transcripts are processed by the configured AI provider to generate feedback (emails and phone numbers are removed first).' : 'Transcripts are checked on this server by a rule-based coach; no AI provider is configured.' }}</li>
                        <li>Your browser will also ask for microphone permission.</li>
                    </ul>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="consentCheck">
                        <label class="form-check-label fw-semibold" for="consentCheck">I understand and want to use the microphone</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Keep typing</button>
                <button type="button" class="btn btn-pk" id="consentOk" disabled>Turn on microphone</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>window.EN_ROOM = @json($boot);</script>
<script src="{{ asset('js/modules/english-speech.js') }}?v={{ filemtime(public_path('js/modules/english-speech.js')) }}"></script>
<script src="{{ asset('js/modules/english-room.js') }}?v={{ filemtime(public_path('js/modules/english-room.js')) }}"></script>
@endpush
