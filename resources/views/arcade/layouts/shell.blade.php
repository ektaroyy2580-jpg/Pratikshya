{{--
    Shared game shell for every arcade page.
    Child pages provide: @section('arcade-how') (<li> steps), @section('arcade-board') (play area),
    optional @section('arcade-hud') (extra HUD items), and push their module JS to 'scripts'.
--}}
@extends('layouts.app')
@section('title', $game['title'].' · Games · प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/arcade.css') }}?v={{ filemtime(public_path('css/modules/arcade.css')) }}" rel="stylesheet">
@endpush

@php
    $tone = $game['tone'] ?? 'blue';
    $toneClass = $tone === 'blue' ? '' : $tone;
    $variantLabels = [
        'sudoku' => ['easy' => ['Easy', 'About 40 clues'], 'medium' => ['Medium', 'About 33 clues']],
        'uno' => ['1' => ['1 opponent', 'Head to head'], '2' => ['2 opponents', '3-player table'], '3' => ['3 opponents', 'Full table']],
    ];
    $labels = $variantLabels[$gameKey] ?? [];
    $defaultVariant = $gameKey === 'uno' ? '2' : ($variants[0] ?? null);
    $capPct = $cap > 0 ? min(100, round($earnedToday / $cap * 100)) : 100;
@endphp

@section('content')
<header class="pk-page-head arc-head">
    <div class="container">
        <nav class="arc-crumbs mb-3 fade-up" aria-label="Breadcrumb">
            <a href="{{ $hubUrl }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Games</a>
            <i class="bi bi-chevron-right sep" aria-hidden="true"></i>
            <span class="pk-breadcrumb">{{ $game['title'] }}</span>
        </nav>
        <div class="d-flex align-items-start gap-3 fade-up" style="animation-delay:.05s">
            <span class="pk-icon game-mark {{ $toneClass }}"><i class="bi {{ $game['icon'] }}"></i></span>
            <div class="min-w-0">
                <span class="pk-eyebrow">{{ $game['category'] ?? 'Game' }} · {{ $game['players'] }}</span>
                <h1 class="mb-1 mt-1">{{ $game['title'] }}</h1>
                <p class="lead mb-0">{{ $game['description'] }}</p>
            </div>
        </div>
        <div class="arc-chips mt-3 fade-up" style="animation-delay:.1s">
            @isset($game['minutes'])<span class="chip neutral"><i class="bi bi-clock"></i> ~{{ $game['minutes'] }} min</span>@endisset
            <span class="chip neutral"><i class="bi bi-trophy"></i> Best: <span id="arcBestChip">{{ $best !== null ? number_format($best) : '—' }}</span></span>
            <span class="chip neutral"><i class="bi bi-arrow-repeat"></i> {{ $played ? 'Played '.$played.' '.\Illuminate\Support\Str::plural('time', $played) : 'First play' }}</span>
            <span class="chip green"><i class="bi bi-shield-check"></i> Server-verified scores</span>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="pk-card arc-stage" id="arcStage" aria-label="{{ $game['title'] }} game area">
                {{-- Intro --}}
                <div class="arc-screen arc-intro" id="arcIntro">
                    <span class="pk-icon hero-icon {{ $toneClass }}"><i class="bi {{ $game['icon'] }}"></i></span>
                    <h2 class="mb-2">Ready when you are</h2>
                    <p class="pk-muted mx-auto mb-0" style="max-width:30rem">@yield('arcade-intro', 'A fresh round is generated on the server each time you play, so every game is fair.')</p>

                    @if(count($variants) > 1)
                        <div class="arc-variants" role="radiogroup" aria-label="Game options">
                            @foreach($variants as $v)
                                <input type="radio" name="arcVariant" id="var-{{ $v }}" value="{{ $v }}" @checked($v === $defaultVariant)>
                                <label for="var-{{ $v }}">{{ $labels[$v][0] ?? ucfirst($v) }}<small>{{ $labels[$v][1] ?? '' }}</small></label>
                            @endforeach
                        </div>
                    @else
                        <div class="my-4"></div>
                    @endif

                    <button type="button" class="btn btn-pk btn-lg arc-start" id="arcStartBtn"><i class="bi bi-play-fill me-1"></i>Start game</button>
                    <div class="small pk-muted mt-3"><i class="bi bi-info-circle me-1"></i>Games never change your place in any queue.</div>
                </div>

                {{-- Play --}}
                <div class="arc-screen" id="arcPlay" hidden>
                    <div class="arc-hud">
                        <div class="hud-title"><i class="bi {{ $game['icon'] }} text-primary"></i><span>{{ $game['title'] }}</span></div>
                        @yield('arcade-hud')
                        <span class="hud-item" title="Time"><i class="bi bi-stopwatch"></i><span id="arcTime">0:00</span></span>
                        <span class="hud-item accent" title="Score"><i class="bi bi-star-fill"></i><span id="arcScore">0</span></span>
                    </div>
                    <div class="arc-body" id="arcBoard">
                        @yield('arcade-board')
                    </div>
                </div>

                {{-- Result --}}
                <div class="arc-screen arc-result" id="arcResult" hidden></div>
            </section>
        </div>

        <aside class="col-lg-4 arc-side">
            <div class="pk-card p-4 mb-3 reveal">
                <h2 class="mb-3 d-flex align-items-center gap-2"><i class="bi bi-lightbulb text-primary"></i> How to play</h2>
                <ol class="arc-howto">@yield('arcade-how')</ol>
            </div>

            @if($ticket)
                <div class="pk-card p-4 mb-3 reveal" style="--d:.06s" id="arcTicketCard">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="pk-eyebrow"><span class="live-dot"></span> Your wait</span>
                        <span class="pk-status {{ $ticket->status === 'called' ? 'called' : ($ticket->status === 'serving' ? 'serving' : 'waiting') }}" id="arcTicketStatus">{{ ucfirst($ticket->status) }}</span>
                    </div>
                    <div class="arc-ticket">
                        <span class="pk-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <div class="min-w-0">
                            <div class="tok">{{ $ticket->token() }}</div>
                            <div class="small pk-muted text-truncate">{{ $ticket->service->name ?? 'Service' }} · <span id="arcTicketAhead">{{ $ticket->ahead() }}</span> ahead</div>
                        </div>
                    </div>
                    <p class="small pk-muted mt-3 mb-2">We check every few seconds and will stop you here if you're called. Your place in line never depends on games or points.</p>
                    <a href="{{ route('ticket', $ticket) }}" class="btn btn-ghost btn-sm w-100"><i class="bi bi-arrow-up-right me-1"></i>Open my ticket</a>
                </div>
            @endif

            <div class="pk-card p-4 reveal" style="--d:.12s">
                <h2 class="mb-3 d-flex align-items-center gap-2"><i class="bi bi-gem text-primary"></i> WaitPoints</h2>
                <div class="d-flex justify-content-between small fw-bold mb-1"><span>Earned from games today</span><span><span id="arcToday">{{ $earnedToday }}</span> / {{ $cap }}</span></div>
                <div class="progress arc-meter mb-3" role="progressbar" aria-label="Daily game points" aria-valuenow="{{ $earnedToday }}" aria-valuemin="0" aria-valuemax="{{ $cap }}"><div class="progress-bar" id="arcTodayBar" style="width: {{ $capPct }}%"></div></div>
                <div class="d-flex justify-content-between small"><span class="pk-muted">Balance</span><b id="arcBalance">{{ number_format($balance) }}</b></div>
                <hr class="my-3">
                <ul class="list-unstyled small pk-muted mb-0 d-grid gap-2">
                    <li><i class="bi bi-check2-circle text-success me-1"></i>Answers and results are checked on the server.</li>
                    <li><i class="bi bi-check2-circle text-success me-1"></i>Each game pays out once, with a small daily limit.</li>
                    <li><i class="bi bi-check2-circle text-success me-1"></i>Points never affect queue order.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>
@endsection

@prepend('scripts')
@php
$arcadeCfg = [
    'game' => $gameKey,
    'title' => $game['title'],
    'startUrl' => route('arcade.start', $gameKey),
    'hubUrl' => $hubUrl,
    'cap' => $cap,
    'today' => $earnedToday,
    'ticket' => $ticket ? ['stateUrl' => route('ticket.state', $ticket), 'url' => route('ticket', $ticket), 'token' => $ticket->token()] : null,
];
@endphp
<script>window.ARCADE = @json($arcadeCfg);</script>
<script src="{{ asset('js/modules/arcade/arcade-core.js') }}?v={{ filemtime(public_path('js/modules/arcade/arcade-core.js')) }}"></script>
@endprepend
