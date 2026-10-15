@extends('layouts.app')
@section('title', 'Battle results — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/games.css') }}?v={{ filemtime(public_path('css/modules/games.css')) }}">
@endpush

@php
    $ranked = $competition->players->filter(fn ($p) => $p->rank !== null)->sortBy('rank')->values();
    $podium = $ranked->take(3);
    $order = [1 => 'p2', 0 => 'p1', 2 => 'p3']; // visual order: 2nd, 1st, 3rd
    $won = $me && $me->rank === 1 && $ranked->count() > 1;
@endphp

@section('content')
<header class="pk-page-head gm-head">
    <div class="container">
        <nav class="gm-crumbs mb-3 fade-up" aria-label="Breadcrumb">
            <a href="{{ route('games.hub') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Game hub</a>
            <i class="bi bi-chevron-right sep" aria-hidden="true"></i>
            <span class="pk-breadcrumb">Battle #{{ $competition->id }}</span>
        </nav>
        <span class="pk-eyebrow fade-up"><i class="bi bi-flag"></i> Results</span>
        @if($competition->status === 'cancelled')
            <h1 class="mt-2 mb-1 fade-up">Battle cancelled</h1>
            <p class="lead mb-0 fade-up">No questions were played, so no points were awarded.</p>
        @else
            <h1 class="mt-2 mb-1 fade-up">{{ $won ? 'You won!' : ($me?->rank ? 'You placed #'.$me->rank : 'Battle complete') }}</h1>
            <p class="lead mb-0 fade-up">{{ $competition->categoryLabel() }} · {{ $competition->question_count }} questions · {{ $competition->service->name }}</p>
        @endif
    </div>
</header>

<div class="container py-4 py-md-5">
    @if($competition->status === 'completed')
        <div class="row g-4">
            <div class="col-lg-7">
                <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="rankTitle">
                    <h2 class="gm-section-title mb-4" id="rankTitle">Final standings</h2>
                    @if($podium->count() >= 2)
                        <div class="gm-podium mb-4 {{ $podium->count() === 2 ? 'two' : '' }}" aria-hidden="true">
                            @foreach([1, 0, 2] as $idx)
                                @if($p = $podium->get($idx))
                                    <div class="col {{ $order[$idx] }}">
                                        <div class="fw-bold mb-1">{{ $p->user_id === auth()->id() ? 'You' : $p->token_label }}</div>
                                        <div class="gm-sub small mb-2">{{ $p->score }} pts</div>
                                        <div class="blk">{{ $p->rank }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <ol class="gm-lb">
                        @foreach($ranked as $p)
                            <li class="{{ $p->user_id === auth()->id() ? 'you' : '' }}">
                                <span class="gm-pos {{ $p->rank <= 3 ? 'p'.$p->rank : '' }}">{{ $p->rank }}</span>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="fw-bold">{{ $p->user_id === auth()->id() ? 'You ('.$p->token_label.')' : $p->token_label }}</span>
                                    @if($p->status === 'paused')<span class="gm-badge paused ms-1">Called to counter</span>@endif
                                    @if($p->status === 'left')<span class="gm-badge ms-1">Left early</span>@endif
                                    <span class="d-block gm-sub small">{{ $p->correct_count }}/{{ $competition->question_count }} correct</span>
                                </span>
                                <span class="text-end"><span class="fw-bold d-block">{{ number_format($p->score) }}</span><span class="gm-sub small">+{{ (int) $p->reward_points }} WP</span></span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>
            <div class="col-lg-5">
                <section class="gm-points reveal mb-4" style="--d:.06s">
                    <div class="l"><i class="bi bi-stars me-1"></i>Your reward</div>
                    <div class="v">+{{ (int) ($me?->reward_points ?? 0) }} WaitPoints</div>
                    <div class="s">Approved on the server · paid once per battle</div>
                </section>
                <section class="pk-card p-3 p-md-4 reveal" style="--d:.1s">
                    <div class="row text-center g-2">
                        <div class="col-4"><div class="gm-sub small">Score</div><div class="h4 mb-0">{{ number_format($me?->score ?? 0) }}</div></div>
                        <div class="col-4"><div class="gm-sub small">Correct</div><div class="h4 mb-0">{{ $me?->correct_count ?? 0 }}/{{ $competition->question_count }}</div></div>
                        <div class="col-4"><div class="gm-sub small">Rank</div><div class="h4 mb-0">{{ $me?->rank ? '#'.$me->rank : '—' }}</div></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <a href="{{ route('games.hub') }}" class="btn btn-pk flex-grow-1"><i class="bi bi-arrow-repeat me-1"></i>Play again</a>
                        <a href="{{ route('games.leaderboard') }}" class="btn btn-ghost flex-grow-1"><i class="bi bi-trophy me-1"></i>Leaderboard</a>
                    </div>
                </section>
            </div>
        </div>

        <section class="mt-5" aria-labelledby="reviewTitle">
            <h2 class="gm-section-title mb-3" id="reviewTitle">Question review</h2>
            @foreach($competition->questions as $cq)
                @php $mine = $myAnswers->get($cq->id); $q = $cq->question; @endphp
                <div class="gm-review reveal">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="fw-bold">{{ $cq->position + 1 }}. {{ $q->question }}</div>
                        @if($mine)
                            <span class="gm-badge {{ $mine->is_correct ? 'ready' : '' }} flex-shrink-0">{{ $mine->is_correct ? '+'.$mine->points : 'Missed' }}</span>
                        @else
                            <span class="gm-badge flex-shrink-0">No answer</span>
                        @endif
                    </div>
                    <div class="mt-2">
                        @foreach($q->options as $i => $opt)
                            <span class="opt {{ $i === $q->answer_index ? 'right' : ($mine && $mine->choice === $i ? 'mine-wrong' : '') }}">{{ ['A','B','C','D'][$i] }}. {{ $opt }}</span>
                        @endforeach
                    </div>
                    <div class="small pk-muted mt-2"><i class="bi bi-lightbulb me-1"></i>{{ $q->explanation }}</div>
                </div>
            @endforeach
        </section>
    @else
        <div class="gm-empty"><i class="bi bi-x-circle"></i>This battle was cancelled{{ $competition->end_reason === 'expired' ? ' because the lobby expired' : '' }}. <a href="{{ route('games.hub') }}">Start a new one</a>.</div>
    @endif
</div>
@endsection
