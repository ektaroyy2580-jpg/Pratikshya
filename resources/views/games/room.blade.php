@extends('layouts.app')
@section('title', 'Quick Brain Battle — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/games.css') }}?v={{ filemtime(public_path('css/modules/games.css')) }}">
@endpush

@section('content')
<header class="pk-page-head gm-head gm-room-head">
    <div class="container">
        <nav class="gm-crumbs mb-2 fade-up" aria-label="Breadcrumb">
            <a href="{{ route('games.hub') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Game hub</a>
            <i class="bi bi-chevron-right sep" aria-hidden="true"></i>
            <span class="pk-breadcrumb">Battle #{{ $competition->id }}</span>
        </nav>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fade-up" style="animation-delay:.04s">
            <div class="min-w-0">
                <h1 class="h2 mb-1">Quick Brain Battle</h1>
                <div class="gm-sub">
                    <i class="bi bi-geo-alt me-1"></i>{{ $competition->service->name }} · {{ $competition->service->location->name }}
                    <span class="mx-1">·</span>{{ $competition->categoryLabel() }}
                    <span class="mx-1">·</span>{{ $competition->question_count }} questions · {{ $competition->seconds_per_question }}s each
                </div>
            </div>
            <span class="gm-status-pill" id="gmStatus"><span class="live-dot"></span> <span>{{ $competition->statusLabel() }}</span></span>
        </div>
    </div>
</header>

<div class="container py-4" id="gmRoom"
     data-state-url="{{ route('games.competitions.state', $competition) }}"
     data-answer-url="{{ route('games.competitions.answer', $competition) }}"
     data-ready-url="{{ route('games.competitions.ready', $competition) }}"
     data-start-url="{{ route('games.competitions.start', $competition) }}"
     data-leave-url="{{ route('games.competitions.leave', $competition) }}"
     data-cancel-url="{{ route('games.competitions.cancel', $competition) }}"
     data-invite-url="{{ route('games.competitions.invite', $competition) }}"
     data-hub-url="{{ route('games.hub') }}"
     data-ticket-url="{{ ($t = $competition->playerFor(auth()->user())?->ticket) ? route('ticket', $t) : route('games.hub') }}"
     data-reveal-ms="{{ \App\Services\Games\CompetitionService::REVEAL_MS }}">

    <div id="gmNet" class="gm-alert err mb-3 d-none" role="alert"><i class="bi bi-wifi-off fs-5"></i><div><strong>Reconnecting…</strong> We lost contact with the server. Your answers are safe; we'll retry automatically.</div></div>
    <div id="gmToast" class="visually-hidden" aria-live="polite"></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="pk-card p-3 p-md-4" id="gmStage" aria-live="polite">
                <div class="py-4">
                    <div class="skeleton mb-3" style="height:22px;width:40%">.</div>
                    <div class="skeleton mb-2" style="height:56px">.</div>
                    <div class="skeleton" style="height:56px">.</div>
                </div>
                <noscript><p class="mb-0">This live game room needs JavaScript to show questions and live scores.</p></noscript>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="pk-card p-3 p-md-4" aria-labelledby="gmPlayersTitle">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="gm-section-title" id="gmPlayersTitle">Players</h2>
                    <span class="gm-sub" id="gmPlayerCount"></span>
                </div>
                <div id="gmPlayers"></div>
                <p class="gm-sub small mt-3 mb-0"><i class="bi bi-eye-slash me-1"></i>Players appear by token only. Names stay private.</p>
            </section>

            <section class="pk-card p-3 p-md-4 mt-4 d-none" id="gmInvite" aria-labelledby="gmInviteTitle">
                <h2 class="gm-section-title mb-1" id="gmInviteTitle"><i class="bi bi-person-plus me-1 text-primary"></i> Invite people waiting with you</h2>
                <p class="gm-sub small mb-3">Same queue only. They get a notification and can accept or decline.</p>
                <div id="gmCandidates" class="d-flex flex-wrap gap-2"></div>
                <div id="gmInvites" class="mt-3"></div>
            </section>
        </div>
    </div>
</div>

<script type="application/json" id="gmInitial">@json($state)</script>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/games-room.js') }}?v={{ filemtime(public_path('js/modules/games-room.js')) }}"></script>
@endpush
