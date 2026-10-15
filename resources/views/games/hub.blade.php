@extends('layouts.app')
@section('title', 'Game hub — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/games.css') }}?v={{ filemtime(public_path('css/modules/games.css')) }}">
@endpush

@php
    $waiting = $ticket && $ticket->status === 'waiting';
    $called = $ticket && in_array($ticket->status, ['called', 'serving'], true);
    $toneMap = ['blue' => '', 'violet' => 'violet', 'green' => 'green', 'amber' => 'amber', 'rose' => 'rose'];
@endphp

@section('content')
<header class="pk-page-head gm-head">
    <div class="container">
        <nav class="gm-crumbs mb-3 fade-up" aria-label="Breadcrumb">
            <a href="{{ $ticket ? route('ticket', $ticket) : route('home') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>{{ $ticket ? 'My token '.$ticket->token() : 'Home' }}</a>
        </nav>
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3">
            <div class="fade-up" style="animation-delay:.04s">
                <span class="pk-eyebrow"><i class="bi bi-controller"></i> Game hub</span>
                <h1 class="mt-2 mb-2">Play while you <span class="text-gradient">wait</span></h1>
                <p class="lead mb-0">Battle people waiting in the same queue, or play a quick solo game. Games never change your place in line.</p>
            </div>
            <div class="gm-points fade-up" style="animation-delay:.1s" aria-label="WaitPoints balance">
                <div class="l"><i class="bi bi-stars me-1"></i>WaitPoints</div>
                <div class="v">{{ number_format($balance) }}</div>
                <div class="s">Server-verified rewards only</div>
            </div>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">

    {{-- Queue context --}}
    @if($waiting)
        <div class="gm-context reveal mb-4">
            <span class="tok">{{ $ticket->token() }}</span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-bold text-truncate">{{ $ticket->service->name }} <span class="pk-muted fw-semibold">· {{ $ticket->service->location->name }}</span></div>
                <div class="meta mt-1 d-flex flex-wrap gap-3">
                    <span><i class="bi bi-people me-1 pk-muted"></i>{{ $ticketState['ahead'] }} ahead</span>
                    <span><i class="bi bi-clock me-1 pk-muted"></i>≈ {{ $ticketState['wait_low'] }}–{{ $ticketState['wait_high'] }} min wait</span>
                    <span><span class="live-dot me-1"></span>We'll pause your game if you're called</span>
                </div>
            </div>
            <div class="gm-context-cta"><a href="{{ route('ticket', $ticket) }}" class="btn btn-ghost btn-sm">View token</a></div>
        </div>
    @elseif($called)
        <div class="gm-context called reveal mb-4" role="status">
            <span class="tok">{{ $ticket->token() }}</span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-bold">You've been called — please go to Counter {{ $ticket->counterNumber() }}.</div>
                <div class="meta mt-1">Multiplayer battles are paused while you're at the counter. Your scores are saved.</div>
            </div>
            <div class="gm-context-cta"><a href="{{ route('ticket', $ticket) }}" class="btn btn-pk btn-sm">Open my token</a></div>
        </div>
    @else
        <div class="gm-context none reveal mb-4">
            <span class="pk-icon"><i class="bi bi-ticket-perforated"></i></span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-bold">Join a queue to play with people waiting with you.</div>
                <div class="meta mt-1">Quick Brain Battles are matched by queue. Solo games below work any time.</div>
            </div>
            <div class="gm-context-cta"><a href="{{ route('find') }}" class="btn btn-pk btn-sm">Find a service</a></div>
        </div>
    @endif

    {{-- Current battle --}}
    @if($seat)
        <div class="pk-card p-3 p-md-4 mb-4 d-flex flex-wrap align-items-center gap-3 reveal" style="border-color:#c5d0fb">
            <span class="pk-icon violet"><i class="bi bi-lightning-charge"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold">You're in a Quick Brain Battle <span class="gm-status-pill ms-1 {{ in_array($seat->competition->status, ['starting','in_progress']) ? 'live' : '' }}">{{ $seat->competition->statusLabel() }}</span></div>
                <div class="gm-sub">Playing as {{ $seat->token_label }} · {{ $seat->competition->categoryLabel() }} · {{ $seat->competition->question_count }} questions</div>
            </div>
            <a href="{{ route('games.competitions.show', $seat->competition) }}" class="btn btn-pk">Return to battle <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    @endif

    {{-- Invitations --}}
    @if($invitations->isNotEmpty())
        <section class="mb-4 reveal" aria-labelledby="invTitle">
            <h2 class="gm-section-title mb-2" id="invTitle"><i class="bi bi-envelope-paper me-1 text-primary"></i> Invitations</h2>
            @foreach($invitations as $inv)
                @php $hostSeat = $inv->competition->players->firstWhere('user_id', $inv->competition->host_id); @endphp
                <div class="gm-row">
                    <span class="pk-icon violet"><i class="bi bi-people"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold">{{ $hostSeat?->token_label ?? 'A player' }} invited you to a battle</div>
                        <div class="gm-sub">{{ $inv->competition->categoryLabel() }} · {{ $inv->competition->question_count }} questions · {{ $inv->competition->players->count() }}/{{ $inv->competition->max_players }} players</div>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <form method="POST" action="{{ route('games.invitations.decline', $inv) }}">@csrf<button class="btn btn-ghost btn-sm">Decline</button></form>
                        <form method="POST" action="{{ route('games.invitations.accept', $inv) }}">@csrf<button class="btn btn-pk btn-sm" @disabled(! $waiting || ($seat && $seat->competition_id !== $inv->competition_id))>Accept</button></form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Quick Brain Battle --}}
            <section class="pk-card gm-feature reveal" aria-labelledby="qbbTitle">
                <div class="gm-feature-top">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="big-icon"><i class="bi bi-lightning-charge-fill"></i></span>
                        <div class="min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h2 class="h4 mb-0" id="qbbTitle">Quick Brain Battle</h2>
                                <span class="chip" style="background:var(--pk-success-tint);color:var(--pk-success)"><span class="live-dot"></span> Multiplayer</span>
                            </div>
                            <p class="mb-2 mt-1 pk-muted">A live multiple-choice battle against 1–3 people in your queue. Faster correct answers score more.</p>
                            <div class="gm-facts">
                                <span class="chip"><i class="bi bi-people"></i> 2–4 players</span>
                                <span class="chip"><i class="bi bi-geo-alt"></i> Same queue only</span>
                                <span class="chip"><i class="bi bi-shield-check"></i> Server-scored</span>
                                <span class="chip"><i class="bi bi-eye-slash"></i> Token labels only</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-3 p-md-4">
                    @if($waiting && ! $seat)
                        <form method="POST" action="{{ route('games.competitions.store') }}" id="createForm">
                            @csrf
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="category">Topic</label>
                                    <select class="form-select" id="category" name="category">
                                        <option value="">Mixed topics</option>
                                        @foreach($categories as $key => $label)
                                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <span class="form-label d-block" id="qcLabel">Questions</span>
                                    <div class="gm-seg" role="radiogroup" aria-labelledby="qcLabel">
                                        @foreach([5, 8, 10] as $n)
                                            <input type="radio" name="question_count" id="qc{{ $n }}" value="{{ $n }}" @checked((int) old('question_count', 5) === $n)><label for="qc{{ $n }}">{{ $n }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="form-label d-block" id="mpLabel">Max players</span>
                                    <div class="gm-seg" role="radiogroup" aria-labelledby="mpLabel">
                                        @foreach([2, 3, 4] as $n)
                                            <input type="radio" name="max_players" id="mp{{ $n }}" value="{{ $n }}" @checked((int) old('max_players', 4) === $n)><label for="mp{{ $n }}">{{ $n }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="form-label d-block" id="visLabel">Who can join</span>
                                    <div class="gm-seg" role="radiogroup" aria-labelledby="visLabel">
                                        <input type="radio" name="visibility" id="visOpen" value="open" @checked(old('visibility', 'open') === 'open')><label for="visOpen"><i class="bi bi-unlock me-1"></i>Anyone in queue</label>
                                        <input type="radio" name="visibility" id="visInv" value="invite" @checked(old('visibility') === 'invite')><label for="visInv"><i class="bi bi-envelope me-1"></i>Invite only</label>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="seconds_per_question" value="15">
                            @if($errors->any() && ! $errors->has('queue'))
                                <div class="alert alert-danger mt-3 mb-0 small">{{ $errors->first() }}</div>
                            @endif
                            <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
                                <button class="btn btn-pk" type="submit"><i class="bi bi-plus-lg me-1"></i>Create a battle</button>
                                <span class="gm-sub"><i class="bi bi-clock me-1"></i>15 seconds per question · about 2–4 minutes</span>
                            </div>
                        </form>
                    @elseif($seat)
                        <div class="gm-sub"><i class="bi bi-info-circle me-1"></i>You can be in one battle at a time. <a href="{{ route('games.competitions.show', $seat->competition) }}">Return to your battle</a>.</div>
                    @elseif($called)
                        <div class="gm-sub"><i class="bi bi-pause-circle me-1"></i>New battles are unavailable while you're being served.</div>
                    @else
                        <div class="gm-sub"><i class="bi bi-lock me-1"></i>Battles open once you're waiting in a queue. <a href="{{ route('find') }}">Find a service</a> to join one.</div>
                    @endif
                </div>
            </section>

            {{-- Open competitions in this queue --}}
            @if($waiting)
                <section class="mt-4 reveal" aria-labelledby="openTitle">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="gm-section-title" id="openTitle">Open battles in {{ $ticket->service->name }}</h2>
                        <a href="{{ route('games.hub') }}" class="btn btn-ghost btn-sm" aria-label="Refresh list"><i class="bi bi-arrow-clockwise"></i></a>
                    </div>
                    @forelse($open as $c)
                        @php $hostSeat = $c->players->firstWhere('user_id', $c->host_id); @endphp
                        <div class="gm-row">
                            <span class="gm-av c{{ $loop->index % 4 }}">{{ $hostSeat?->token_label ?? '?' }}</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-bold text-truncate">{{ $c->categoryLabel() }} · {{ $c->question_count }} questions</div>
                                <div class="gm-sub d-flex align-items-center gap-2 flex-wrap">
                                    <span class="gm-slots" aria-label="{{ $c->players->count() }} of {{ $c->max_players }} players">
                                        @for($i = 0; $i < $c->max_players; $i++)<span class="{{ $i < $c->players->count() ? 'on' : '' }}"></span>@endfor
                                    </span>
                                    {{ $c->players->count() }}/{{ $c->max_players }} players · {{ $c->statusLabel() }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('games.competitions.join', $c) }}" class="flex-shrink-0">
                                @csrf
                                <button class="btn btn-pk btn-sm" @disabled((bool) $seat)>Join</button>
                            </form>
                        </div>
                    @empty
                        <div class="gm-empty"><i class="bi bi-hourglass"></i>No open battles in your queue yet. Create one and others waiting with you can join.</div>
                    @endforelse
                </section>
            @endif
        </div>

        <div class="col-lg-4">
            <section class="pk-card p-3 p-md-4 reveal" style="--d:.06s" aria-labelledby="lbTitle">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="gm-section-title" id="lbTitle"><i class="bi bi-trophy me-1" style="color:#d97706"></i> This week</h2>
                    <a href="{{ route('games.leaderboard') }}" class="small fw-bold text-decoration-none">Full leaderboard</a>
                </div>
                @if($weekly->isEmpty())
                    <div class="gm-empty py-3"><i class="bi bi-bar-chart"></i>No battles finished this week yet. Be the first on the board.</div>
                @else
                    <ol class="gm-lb">
                        @foreach($weekly as $row)
                            <li class="{{ $row['is_you'] ? 'you' : '' }}">
                                <span class="gm-pos {{ $row['position'] <= 3 ? 'p'.$row['position'] : '' }}">{{ $row['position'] }}</span>
                                <span class="flex-grow-1 fw-semibold text-truncate">{{ $row['name'] }}</span>
                                <span class="fw-bold">{{ number_format($row['points']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <section class="pk-card p-3 p-md-4 mt-4 reveal" style="--d:.1s">
                <h2 class="gm-section-title mb-2"><i class="bi bi-info-circle me-1 text-primary"></i> How rewards work</h2>
                <ul class="small pk-muted ps-3 mb-0">
                    <li>5 WaitPoints for taking part, +2 per correct answer.</li>
                    <li>Winner +15, runner-up +5 (2+ players).</li>
                    <li>Paid once per battle, checked on the server.</li>
                    <li>Points never move you up the queue.</li>
                </ul>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('games.admin.questions') }}" class="btn btn-ghost btn-sm mt-3 w-100"><i class="bi bi-ui-checks me-1"></i>Manage question bank</a>
                @endif
            </section>
        </div>
    </div>

    {{-- Solo games (arcade module) --}}
    @if($arcade->isNotEmpty())
        <section class="mt-5" aria-labelledby="soloTitle">
            <div class="d-flex align-items-end justify-content-between mb-3">
                <div>
                    <h2 class="gm-section-title" id="soloTitle">Solo games</h2>
                    <div class="gm-sub">Quick games for one, any time.</div>
                </div>
            </div>
            <div class="row g-3">
                @foreach($arcade as $g)
                    <div class="col-6 col-md-4 col-xl-3">
                        <a href="{{ route($g['route']) }}" class="pk-card lift gm-game reveal" style="--d:{{ min($loop->index, 7) * 0.04 }}s">
                            <span class="pk-icon {{ $toneMap[$g['tone'] ?? 'blue'] ?? '' }} mb-3"><i class="bi {{ $g['icon'] ?? 'bi-controller' }}"></i></span>
                            <span class="t">{{ $g['title'] }}</span>
                            <span class="d mt-1">{{ $g['description'] ?? '' }}</span>
                            <span class="d-flex align-items-center justify-content-between mt-3">
                                <span class="gm-sub" style="font-size:.76rem">{{ $g['players'] ?? '' }}@if(! empty($g['minutes'])) · {{ $g['minutes'] }} min @endif</span>
                                <span class="go">Play <i class="bi bi-arrow-right"></i></span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
