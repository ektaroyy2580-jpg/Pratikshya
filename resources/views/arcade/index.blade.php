@extends('layouts.app')
@section('title', 'Solo games · प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/arcade.css') }}?v={{ filemtime(public_path('css/modules/arcade.css')) }}" rel="stylesheet">
@endpush

@section('content')
<header class="pk-page-head arc-head">
    <div class="container">
        @if($hubUrl)
            <a href="{{ $hubUrl }}" class="pk-breadcrumb d-inline-block mb-3 fade-up"><i class="bi bi-arrow-left me-1"></i>Game hub</a>
        @endif
        <span class="pk-eyebrow fade-up"><i class="bi bi-controller"></i> Solo arcade</span>
        <h1 class="mt-1 mb-2 fade-up" style="animation-delay:.05s">Play while you <span class="text-gradient">wait</span></h1>
        <p class="lead mb-3 fade-up" style="animation-delay:.1s">Quick, fair games with server-checked scores. Earn a few WaitPoints each day. Your place in line never changes.</p>
        <div class="arc-chips fade-up" style="animation-delay:.15s">
            <span class="chip green"><i class="bi bi-gem"></i> {{ $earnedToday }} / {{ $cap }} game points today</span>
            <span class="chip neutral"><i class="bi bi-shield-check"></i> Server-verified</span>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    @if($games->isEmpty())
        <div class="pk-card p-5 text-center pk-muted">No games are available right now.</div>
    @else
        <div class="row g-3 g-md-4">
            @foreach($games as $g)
                @php $tone = ($g['tone'] ?? 'blue') === 'blue' ? '' : $g['tone']; @endphp
                <div class="col-sm-6 col-lg-3 reveal" style="--d:{{ $loop->index * 0.04 }}s">
                    <a href="{{ route($g['route']) }}" class="pk-card lift arc-tile">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="pk-icon {{ $tone }}"><i class="bi {{ $g['icon'] }}"></i></span>
                            <span class="chip neutral">{{ $g['players'] }}</span>
                        </div>
                        <h3>{{ $g['title'] }}</h3>
                        <p>{{ $g['description'] }}</p>
                        <div class="foot">
                            <span>@if($g['best'] !== null)<i class="bi bi-trophy me-1"></i>Best {{ number_format($g['best']) }}@else<i class="bi bi-clock me-1"></i>~{{ $g['minutes'] ?? 3 }} min @endif</span>
                            <span class="go">Play <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
