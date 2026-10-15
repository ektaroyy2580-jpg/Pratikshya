@extends('layouts.app')
@section('title', 'Session summary — English practice')

@push('head')
<link href="{{ asset('css/modules/english.css') }}?v={{ filemtime(public_path('css/modules/english.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $sc = $scores;
    $fmt = fn ($v, $suffix = '') => $v === null ? '—' : $v.$suffix;
    $mins = $summary['minutes_practised'] ?? 0;
@endphp
<div class="container py-4 py-lg-5">
    <a href="{{ route('english.home') }}" class="pk-breadcrumb d-inline-block mb-3"><i class="bi bi-arrow-left me-1"></i>English practice</a>

    <section class="sum-hero mb-4 fade-up">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div class="min-w-0">
                <div class="pk-eyebrow mb-1"><i class="bi bi-flag-fill"></i> Session complete · {{ $scenario->title }}</div>
                <h1 class="h3 mb-2">{{ $summary['headline'] ?? 'Session complete.' }}</h1>
                <div class="d-flex flex-wrap gap-2 small fw-semibold" style="color:rgba(255,255,255,.9)">
                    <span><i class="bi bi-clock me-1"></i>{{ rtrim(rtrim(number_format($mins, 1), '0'), '.') ?: '0' }} min practised</span>
                    <span>·</span>
                    <span><i class="bi bi-chat-square-text me-1"></i>{{ $sc['turns_answered'] ?? 0 }} answers</span>
                    <span>·</span>
                    <span><i class="bi bi-fire me-1"></i>{{ $progress['streak'] }}-day streak</span>
                    @if(($summary['points_awarded'] ?? 0) > 0)
                        <span>·</span><span><i class="bi bi-stars me-1"></i>+{{ $summary['points_awarded'] }} WaitPoints</span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('english.scenarios', ['type' => $scenario->type]) }}" class="btn btn-light fw-bold" style="border-radius:var(--pk-radius-sm)"><i class="bi bi-arrow-repeat me-1"></i>Practise again</a>
            </div>
        </div>
    </section>

    <div class="row g-2 g-md-3 mb-2">
        <div class="col-6 col-lg-3 reveal"><div class="en-stat"><div class="l"><i class="bi bi-fonts"></i> Words</div><div class="v">{{ $sc['words_total'] ?? 0 }}</div><div class="s">~{{ $sc['avg_words_per_answer'] ?? 0 }} per answer</div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.05s"><div class="en-stat"><div class="l"><i class="bi bi-shuffle"></i> Word variety</div><div class="v">{{ isset($sc['vocabulary_variety_ttr']) ? round($sc['vocabulary_variety_ttr'] * 100).'%' : '—' }}</div><div class="s">unique ÷ total words</div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.1s"><div class="en-stat"><div class="l"><i class="bi bi-chat-left-dots"></i> Filler words</div><div class="v">{{ $sc['filler_words_total'] ?? 0 }}</div><div class="s">{{ $sc['filler_words_per_100'] ?? 0 }} per 100 words</div></div></div>
        <div class="col-6 col-lg-3 reveal" style="--d:.15s">
            @if(($sc['browser_transcript_match_pct'] ?? null) !== null)
                <div class="en-stat"><div class="l"><i class="bi bi-soundwave"></i> Transcript match</div><div class="v">{{ $sc['browser_transcript_match_pct'] }}%</div><div class="s">what the browser heard — not a pronunciation score</div></div>
            @else
                <div class="en-stat"><div class="l"><i class="bi bi-speedometer2"></i> Speaking pace</div><div class="v">{{ $fmt($sc['avg_speaking_pace_wpm'] ?? null) }} @if($sc['avg_speaking_pace_wpm'] ?? null)<small>wpm</small>@endif</div><div class="s">{{ ($sc['avg_speaking_pace_wpm'] ?? null) ? 'from transcript length ÷ time — not a score' : 'measured in voice mode only' }}</div></div>
            @endif
        </div>
    </div>
    <p class="small pk-muted mb-4"><i class="bi bi-info-circle me-1"></i>These are simple heuristic indicators to help you notice habits — not grades or official test scores. Feedback source: <b>{{ ['ai' => 'AI provider', 'rules' => 'local rule-based coach', 'mixed' => 'AI provider + local coach'][$summary['feedback_source'] ?? 'rules'] ?? 'local coach' }}</b>.</p>

    <div class="row g-4">
        <div class="col-lg-7 d-grid gap-4 align-content-start">
            <section class="pk-card p-4 reveal" aria-labelledby="strTitle">
                <h2 class="en-section-title mb-3" id="strTitle"><i class="bi bi-hand-thumbs-up me-1 text-success"></i> What went well</h2>
                @if(!empty($summary['strengths']))
                    <ul class="check-list">
                        @foreach($summary['strengths'] as $s)<li><i class="bi bi-check-circle-fill"></i><span>{{ $s }}</span></li>@endforeach
                    </ul>
                @else
                    <p class="small pk-muted mb-0">Every session builds confidence — answering even one prompt counts. Try a longer answer next time.</p>
                @endif
            </section>

            <section class="pk-card p-4 reveal" aria-labelledby="focusTitle">
                <h2 class="en-section-title mb-3" id="focusTitle"><i class="bi bi-bullseye me-1 text-primary"></i> Practise next</h2>
                @forelse($summary['focus_next'] ?? [] as $f)
                    <div class="focus-item">
                        <div class="d-flex justify-content-between gap-2"><b>{{ $f['label'] }}</b><span class="chip muted">{{ $f['count'] }}×</span></div>
                        @if($f['example'])<div class="small mt-1"><span class="pk-muted">Example:</span> {{ $f['example'] }}</div>@endif
                        @if($f['tip'])<div class="small pk-muted mt-1">{{ $f['tip'] }}</div>@endif
                    </div>
                @empty
                    <p class="small pk-muted mb-0">Nothing flagged by the checker this time. Challenge yourself with a harder scenario!</p>
                @endforelse
            </section>

            <section class="turn-log reveal" aria-labelledby="logTitle">
                <h2 class="en-section-title mb-3" id="logTitle">Your answers</h2>
                @forelse($session->turns as $t)
                    @php $fb = $t->feedback ?? []; @endphp
                    <details @if($loop->first) open @endif>
                        <summary><span class="text-truncate">{{ $loop->iteration }}. {{ \Illuminate\Support\Str::limit($t->prompt, 80) }}</span><i class="bi bi-chevron-down"></i></summary>
                        <div class="tl-body d-grid gap-2">
                            <div><span class="small pk-muted">{{ $t->input_mode === 'voice' ? 'Browser transcript' : 'You typed' }}:</span><div class="heard mt-1">{{ $t->user_text }}</div></div>
                            <div class="fw-semibold">{{ $fb['summary'] ?? '' }}</div>
                            @if(!empty($fb['pronunciation']))
                                <div class="small">{{ $fb['pronunciation']['hint'] }} <span class="pk-muted">({{ $fb['pronunciation']['matched_words'] }}/{{ $fb['pronunciation']['total_words'] }} words matched — not a pronunciation score)</span></div>
                            @endif
                            @foreach($fb['corrections'] ?? [] as $c)
                                <div class="corr"><span class="from">{{ $c['original'] }}</span> → <span class="to">{{ $c['suggestion'] }}</span><span class="why">{{ $c['explanation'] }}</span></div>
                            @endforeach
                            @foreach($fb['vocabulary'] ?? [] as $v)
                                <div class="small"><i class="bi bi-journal-text text-primary me-1"></i><b>{{ $v['suggestion'] }}</b>@if(!empty($v['note'])) — {{ $v['note'] }}@endif</div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="pk-card p-4 text-center pk-muted small">No answers were submitted in this session.</div>
                @endforelse
            </section>
        </div>

        <aside class="col-lg-5 d-grid gap-4 align-content-start">
            <section class="pk-card p-4 reveal" aria-labelledby="vocTitle">
                <h2 class="en-section-title mb-3" id="vocTitle">Vocabulary</h2>
                <div class="small fw-bold pk-muted mb-2">Used ({{ count($summary['vocabulary_used'] ?? []) }})</div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @forelse($summary['vocabulary_used'] ?? [] as $w)<span class="vocab-chip used"><i class="bi bi-check2"></i>{{ $w }}</span>@empty<span class="small pk-muted">None yet</span>@endforelse
                </div>
                <div class="small fw-bold pk-muted mb-2">Try next time</div>
                <div class="d-flex flex-wrap gap-2">
                    @forelse($summary['vocabulary_to_try'] ?? [] as $w)<span class="vocab-chip">{{ $w }}</span>@empty<span class="small pk-muted">You used them all!</span>@endforelse
                </div>
            </section>
            <section class="pk-card p-4 reveal" aria-labelledby="indTitle">
                <h2 class="en-section-title mb-3" id="indTitle">All indicators</h2>
                @php
                    $rows = [
                        ['Answers', $sc['turns_answered'] ?? 0],
                        ['Average sentence length', $fmt($sc['avg_sentence_length_words'] ?? null, ' words')],
                        ['Grammar points flagged', $sc['grammar_points_flagged'] ?? 0],
                        ['Target words used', ($sc['target_vocabulary_used'] ?? 0).' of '.($sc['target_vocabulary_total'] ?? 0)],
                        ['Speaking pace (voice only)', $fmt($sc['avg_speaking_pace_wpm'] ?? null, ' wpm')],
                    ];
                @endphp
                @foreach($rows as [$k, $v])
                    <div class="d-flex justify-content-between py-2 small {{ !$loop->first ? 'border-top' : '' }}" style="border-color:#f0f1f7 !important"><span class="pk-muted fw-semibold">{{ $k }}</span><b>{{ $v }}</b></div>
                @endforeach
            </section>
            <div class="d-grid gap-2">
                <a href="{{ route('english.scenarios') }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i>New session</a>
                @if($session->ticket && $session->ticket->isActive())
                    <a href="{{ route('ticket', $session->ticket) }}" class="btn btn-ghost"><i class="bi bi-ticket-perforated me-1"></i>Back to my token</a>
                @else
                    <a href="{{ route('english.home') }}" class="btn btn-ghost">Practice home</a>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
