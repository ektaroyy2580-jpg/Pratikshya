@extends('layouts.app')
@section('title', 'English practice — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/english.css') }}?v={{ filemtime(public_path('css/modules/english.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $p = $progress;
    $s = $suggestion;
@endphp
<header class="pk-page-head en-head">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 fade-up">
            <span class="pk-eyebrow"><i class="bi bi-mic"></i> English speaking practice</span>
            <span class="chip muted"><i class="bi bi-cpu"></i> {{ $aiLabel }}</span>
        </div>
        <h1 class="mb-2 fade-up" style="animation-delay:.05s">Speak with confidence — <span class="text-gradient">while you wait</span></h1>
        <p class="lead mb-4 fade-up" style="animation-delay:.1s">Short, friendly conversations for interviews, offices, cafés and everyday life. Speak or type; get supportive feedback that explains mistakes and suggests better phrasing.</p>

        <div class="row g-2 g-md-3">
            <div class="col-6 col-lg-3 reveal"><div class="en-stat accent"><div class="l"><i class="bi bi-fire"></i> Day streak</div><div class="v">{{ $p['streak'] }} <small>{{ $p['streak'] === 1 ? 'day' : 'days' }}</small></div><div class="s">{{ $p['practised_today'] ? 'Practised today — nice!' : 'Practise today to keep it going' }}</div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.06s"><div class="en-stat"><div class="l"><i class="bi bi-check2-circle"></i> Sessions</div><div class="v">{{ $p['sessions'] }}</div><div class="s">completed</div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.12s"><div class="en-stat"><div class="l"><i class="bi bi-clock-history"></i> Minutes</div><div class="v">{{ rtrim(rtrim(number_format($p['minutes'], 1), '0'), '.') }} <small>min</small></div><div class="s">practised in total</div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.18s"><div class="en-stat"><div class="l"><i class="bi bi-chat-square-text"></i> Answers</div><div class="v">{{ $p['answers'] }}</div><div class="s">with feedback</div></div></div>
        </div>
    </div>
</header>

<div class="container py-4 py-lg-5">
    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            @if($active)
                <div class="pk-card p-3 p-md-4 d-flex flex-wrap align-items-center gap-3 reveal" style="border-color:#cdd6ff;background:var(--pk-tint)">
                    <span class="pk-icon"><i class="bi {{ $active->scenario->icon }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold">Continue: {{ $active->scenario->title }}</div>
                        <div class="small pk-muted">{{ $active->turns_count }} of {{ $active->plannedCount() }} prompts answered · {{ $active->duration_target_minutes }}-minute session</div>
                    </div>
                    <a class="btn btn-pk" href="{{ route('english.room', $active) }}">Resume <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
            @endif

            <section class="pk-card p-4 reveal" aria-labelledby="fitTitle">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <div class="pk-eyebrow mb-1"><i class="bi bi-hourglass-split"></i> Fits your wait</div>
                        <h2 class="h5 mb-1" id="fitTitle">How long do you have?</h2>
                        <p class="small pk-muted mb-0">{{ $s['message'] }}</p>
                    </div>
                    @if($s['ticket'])
                        <a href="{{ route('ticket', $s['ticket']) }}" class="chip text-decoration-none"><i class="bi bi-ticket-perforated"></i> {{ $s['ticket']->token() }}</a>
                    @endif
                </div>
                <div class="dur-group">
                    @foreach($s['options'] as $mins => $fits)
                        <a class="dur-opt {{ $mins === $s['suggested'] ? 'active' : '' }}" href="{{ route('english.scenarios', ['minutes' => $mins]) }}">
                            <span class="n">{{ $mins }} <small>min</small></span>
                            <span class="t">{{ config('english.prompts_per_duration.'.$mins) }} prompts</span>
                            @if($s['ticket'])
                                <span class="t {{ $fits ? 'fits' : 'long' }}"><i class="bi {{ $fits ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i> {{ $fits ? 'Fits your wait' : 'May be longer than your wait' }}</span>
                            @elseif($mins === $s['suggested'])
                                <span class="t fits"><i class="bi bi-stars"></i> Suggested</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>

            <section aria-labelledby="recTitle">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <h2 class="en-section-title" id="recTitle">Recommended for you</h2>
                    <a href="{{ route('english.scenarios') }}" class="small fw-bold text-decoration-none">All scenarios <i class="bi bi-arrow-right"></i></a>
                </div>
                @if($recommended->isEmpty())
                    <div class="pk-card p-4 text-center pk-muted">No scenarios yet. An admin can load them with the English seeder.</div>
                @else
                    <div class="row g-3">
                        @foreach($recommended as $i => $sc)
                            <div class="col-md-4 reveal" style="--d:{{ $i * .06 }}s">
                                <a href="{{ route('english.scenarios', ['minutes' => $s['suggested'], 'type' => $sc->type]) }}#sc-{{ $sc->id }}" class="pk-card lift sc-card text-decoration-none text-reset">
                                    <span class="pk-icon {{ $sc->type === 'pronunciation' ? 'violet' : ($sc->type === 'interview' ? '' : 'green') }}"><i class="bi {{ $sc->icon }}"></i></span>
                                    <span class="sc-title">{{ $sc->title }}</span>
                                    <span class="sc-desc">{{ \Illuminate\Support\Str::limit($sc->description, 90) }}</span>
                                    <span class="sc-meta mt-3"><span class="chip muted">{{ $sc->typeLabel() }}</span><span class="chip lvl-{{ $sc->level }}">{{ ucfirst($sc->level) }}</span></span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="pk-card p-4 reveal" aria-labelledby="histTitle">
                <h2 class="en-section-title mb-3" id="histTitle">Recent sessions</h2>
                @forelse($p['recent_sessions'] as $rs)
                    <div class="d-flex align-items-center gap-3 py-2 {{ !$loop->first ? 'border-top' : '' }}" style="border-color:#f0f1f7 !important">
                        <span class="pk-icon" style="width:38px;height:38px;font-size:1.05rem"><i class="bi {{ $rs->scenario->icon ?? 'bi-chat-dots' }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-truncate">{{ $rs->scenario->title ?? 'Practice' }}</div>
                            <div class="small pk-muted">{{ $rs->created_at->diffForHumans() }} · {{ $rs->duration_target_minutes }} min · {{ $rs->mode === 'voice' ? 'Voice' : 'Text' }}</div>
                        </div>
                        @if($rs->status === 'completed')
                            <a href="{{ route('english.summary', $rs) }}" class="btn btn-ghost btn-sm">Summary</a>
                        @else
                            <a href="{{ route('english.room', $rs) }}" class="btn btn-pk btn-sm">Resume</a>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-3">
                        <span class="pk-icon violet mb-2"><i class="bi bi-chat-square-dots"></i></span>
                        <p class="fw-bold mb-1">No sessions yet</p>
                        <p class="small pk-muted mb-3">Your first session takes just 3 minutes.</p>
                        <a href="{{ route('english.scenarios', ['minutes' => 3]) }}" class="btn btn-pk btn-sm">Start a 3-minute session</a>
                    </div>
                @endforelse
            </section>
        </div>

        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            <section class="pk-card p-4 reveal" aria-labelledby="fbTitle">
                <h2 class="en-section-title mb-2" id="fbTitle">Recent feedback</h2>
                @forelse($p['recent_feedback'] as $t)
                    <div class="rf-item">
                        <span class="pk-icon {{ empty($t->feedback['corrections']) ? 'green' : 'amber' }}" style="width:32px;height:32px;font-size:.9rem;border-radius:10px"><i class="bi {{ empty($t->feedback['corrections']) ? 'bi-check2' : 'bi-pencil' }}"></i></span>
                        <div class="min-w-0">
                            <div class="q text-truncate">{{ $t->session->scenario->title ?? '' }}</div>
                            <div class="s">{{ \Illuminate\Support\Str::limit($t->feedback['summary'] ?? '', 140) }}</div>
                        </div>
                    </div>
                @empty
                    <p class="small pk-muted mb-0">Feedback from your answers will appear here.</p>
                @endforelse
            </section>

            <section class="pk-card p-4 privacy-card reveal" aria-labelledby="privTitle">
                <div class="d-flex align-items-center gap-2 mb-2"><span class="pk-icon green" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-shield-lock"></i></span><h2 class="en-section-title" id="privTitle">Your voice stays private</h2></div>
                <ul class="small pk-muted ps-3 mb-0">
                    <li><b>No recordings are stored.</b> Speech is turned into text in your browser — on-device by default in Brave and Firefox — and only the text is sent.</li>
                    <li>The microphone switches on only after you confirm a notice, and only while you press the mic button.</li>
                    <li>Feedback is {{ $aiLabel === 'Local rule-based coach' ? 'generated on this server by a rule-based coach — no AI provider is configured.' : 'generated by the configured AI provider from your transcript (emails and phone numbers removed), with local checks.' }}</li>
                    <li>Pronunciation drills show what the speech recogniser heard — not a pronunciation score.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
@endsection
