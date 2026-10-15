@extends('layouts.app')
@section('title', 'Leaderboard — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/games.css') }}?v={{ filemtime(public_path('css/modules/games.css')) }}">
@endpush

@section('content')
<header class="pk-page-head gm-head">
    <div class="container">
        <nav class="gm-crumbs mb-3 fade-up" aria-label="Breadcrumb">
            <a href="{{ route('games.hub') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Game hub</a>
        </nav>
        <span class="pk-eyebrow fade-up"><i class="bi bi-trophy"></i> Leaderboard</span>
        <h1 class="mt-2 mb-2 fade-up">Quick Brain Battle <span class="text-gradient">champions</span></h1>
        <p class="lead mb-0 fade-up">Total battle points from completed multiplayer games. Other players are shown under a private alias.</p>
    </div>
</header>

<div class="container py-4 py-md-5" style="max-width:860px">
    <div class="gm-seg mb-4" role="tablist" aria-label="Period">
        <a href="{{ route('games.leaderboard') }}" role="tab" aria-selected="{{ $period === 'weekly' ? 'true' : 'false' }}" class="text-decoration-none"><label class="mb-0" style="{{ $period === 'weekly' ? 'background:#fff;color:var(--pk-blue);box-shadow:var(--pk-shadow-sm)' : '' }}">This week</label></a>
        <a href="{{ route('games.leaderboard', ['period' => 'all']) }}" role="tab" aria-selected="{{ $period === 'all' ? 'true' : 'false' }}" class="text-decoration-none"><label class="mb-0" style="{{ $period === 'all' ? 'background:#fff;color:var(--pk-blue);box-shadow:var(--pk-shadow-sm)' : '' }}">All time</label></a>
    </div>

    <section class="pk-card p-2 p-md-3 reveal">
        @if($rows->isEmpty())
            <div class="gm-empty m-2"><i class="bi bi-bar-chart"></i>{{ $period === 'weekly' ? 'No battles have finished this week yet.' : 'No battles have finished yet.' }} <a href="{{ route('games.hub') }}">Start one</a>.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th scope="col">#</th><th scope="col">Player</th><th scope="col" class="text-end">Points</th><th scope="col" class="text-end d-none d-sm-table-cell">Wins</th><th scope="col" class="text-end d-none d-sm-table-cell">Battles</th><th scope="col" class="text-end">WaitPoints</th></tr></thead>
                    <tbody>
                        @foreach($rows as $r)
                            <tr @if($r['is_you']) style="background:var(--pk-tint)" @endif>
                                <td><span class="gm-pos {{ $r['position'] <= 3 ? 'p'.$r['position'] : '' }}">{{ $r['position'] }}</span></td>
                                <td class="fw-bold">{{ $r['name'] }}</td>
                                <td class="text-end fw-bold">{{ number_format($r['points']) }}</td>
                                <td class="text-end d-none d-sm-table-cell">{{ $r['wins'] }}</td>
                                <td class="text-end d-none d-sm-table-cell">{{ $r['games'] }}</td>
                                <td class="text-end">+{{ number_format($r['rewards']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    <p class="gm-sub small mt-3"><i class="bi bi-shield-check me-1"></i>Scores are calculated on the server from timed answers. Weekly board resets every Monday.</p>
</div>
@endsection
