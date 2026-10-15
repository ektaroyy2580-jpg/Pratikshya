@extends('layouts.app')
@section('title', 'Queue exchange — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/integrity.css') }}?v={{ @filemtime(public_path('css/modules/integrity.css')) }}">
@endpush

@section('content')
@php
    $outByTicket = $outgoing->keyBy('requester_ticket_id');
    $pendingIncoming = $incoming->where('status', 'pending')->count();
@endphp
<header class="pk-page-head ig-head">
    <div class="container">
        <a href="{{ route('profile') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Profile</a>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2">
            <div class="fade-up">
                <span class="pk-eyebrow mb-2"><i class="bi bi-arrow-left-right"></i> Voluntary exchange</span>
                <h1 class="mb-1">Swap places, <span class="text-gradient">fairly</span></h1>
                <div class="pk-muted" style="max-width: 560px">Two people in the same queue can agree to trade positions. Both must say yes, nothing is paid, and your token keeps its full history.</div>
            </div>
            <div class="d-flex flex-wrap gap-2 fade-up" style="animation-delay:.05s">
                <span class="chip"><i class="bi bi-diagram-2"></i> Same service only</span>
                <span class="chip"><i class="bi bi-cash-coin"></i> No payment or points</span>
                <span class="chip"><i class="bi bi-eye-slash"></i> Token only, never names</span>
            </div>
        </div>
    </div>
</header>

<div class="container py-4 py-lg-5" data-ig-poll="{{ route('integrity.exchange.poll') }}" data-ig-signature="{{ $signature }}">
    @if($errors->any() && ! $errors->has('queue') && ! $errors->has('target_token'))
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Incoming requests --}}
            <section class="pk-card p-3 p-md-4 mb-4 reveal" aria-labelledby="inHead">
                <div class="ig-sec-head">
                    <h2 id="inHead"><span class="pk-icon violet" style="width:34px;height:34px;font-size:.95rem"><i class="bi bi-inbox"></i></span> Requests for you</h2>
                    @if($pendingIncoming)<span class="chip" style="background:var(--pk-tint-violet);color:var(--pk-violet)">{{ $pendingIncoming }} waiting for your reply</span>@endif
                </div>
                @forelse($incoming as $x)
                    <div class="mb-3">@include('integrity.partials._incoming', ['x' => $x])</div>
                @empty
                    <div class="ig-empty py-3">
                        <span class="pk-icon violet"><i class="bi bi-inbox"></i></span>
                        <div class="fw-bold">No requests right now</div>
                        <div class="ig-note">When someone in your queue asks to swap, it appears here and in your notifications.</div>
                    </div>
                @endforelse
            </section>

            {{-- My waiting tokens --}}
            <section class="reveal" style="--d:.06s" aria-labelledby="myHead">
                <div class="ig-sec-head">
                    <h2 id="myHead"><i class="bi bi-ticket-perforated pk-muted"></i> Your waiting tokens</h2>
                </div>
                @forelse($tickets as $t)
                    @php $open = $outByTicket->get($t->id); @endphp
                    <article class="pk-card ig-ticket mb-3">
                        <div class="stub">
                            <div>
                                <small>Your token</small>
                                <div class="ig-token">{{ $t->token() }}</div>
                            </div>
                            <small class="text-end text-sm-start"><i class="bi bi-people me-1"></i>{{ $t->ahead() }} ahead</small>
                        </div>
                        <div class="body">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                <div class="ig-min-0">
                                    <div class="fw-bold text-truncate">{{ $t->service->name }}</div>
                                    <div class="ig-note text-truncate"><i class="bi bi-geo-alt me-1"></i>{{ $t->service->location->name }} · Counter {{ $t->counterNumber() }}</div>
                                </div>
                                <a href="{{ route('ticket', $t) }}" class="btn btn-ghost btn-sm align-self-start"><i class="bi bi-box-arrow-up-right me-1"></i>Open token</a>
                            </div>
                            @if($open)
                                @include('integrity.partials._outgoing', ['x' => $open])
                            @else
                                @include('integrity.partials._request-form', ['ticket' => $t])
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="pk-card ig-empty">
                        <span class="pk-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <div class="fw-bold mb-1">You're not waiting in any queue</div>
                        <div class="ig-note mb-3">Exchanges are possible only while your token is waiting.</div>
                        <a href="{{ route('find') }}" class="btn btn-pk btn-sm"><i class="bi bi-search me-1"></i>Find a service</a>
                    </div>
                @endforelse
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="ig-sticky">
                <section class="pk-card p-4 mb-4 reveal" style="--d:.1s" aria-labelledby="howHead">
                    <h2 id="howHead" class="h6 fw-bold mb-3">How an exchange works</h2>
                    <div class="ig-steps vertical">
                        <div class="ig-step"><span class="n">1</span><b>Ask by token</b><span>Enter the token you'd like to swap with, e.g. {{ $tickets->first()?->service->prefix ?? 'A' }}-29.</span></div>
                        <div class="ig-step"><span class="n">2</span><b>They decide</b><span>They have {{ config('integrity.exchange.expiry_minutes') }} minutes to accept or decline.</span></div>
                        <div class="ig-step"><span class="n">3</span><b>Staff check</b><span>Some offices ask counter staff to approve first.</span></div>
                        <div class="ig-step"><span class="n">4</span><b>Swap</b><span>Both tokens change at once and both of you are notified.</span></div>
                    </div>
                </section>
                <section class="pk-card p-4 reveal" style="--d:.14s" aria-labelledby="histHead">
                    <h2 id="histHead" class="h6 fw-bold mb-2">Recent activity</h2>
                    @forelse($history as $h)
                        @php
                            $mineIsReq = $h->requester_id === $me->id;
                            $icon = ['completed' => 'bi-check2-circle text-success', 'declined' => 'bi-x-circle text-danger', 'rejected' => 'bi-shield-x text-danger',
                                     'expired' => 'bi-clock-history pk-muted', 'cancelled' => 'bi-arrow-counterclockwise pk-muted', 'failed' => 'bi-exclamation-triangle text-warning'][$h->status] ?? 'bi-dot';
                        @endphp
                        <div class="ig-hist">
                            <i class="bi {{ $icon }} fs-5"></i>
                            <div class="ig-min-0 flex-grow-1">
                                <div class="fw-semibold">{{ $h->service->token($h->requester_number_before) }} <i class="bi bi-arrow-left-right small pk-muted"></i> {{ $h->service->token($h->target_number_before) }}</div>
                                <div class="ig-note">{{ $h->statusLabel() }} · {{ $mineIsReq ? 'you asked' : 'you were asked' }} · {{ $h->updated_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="ig-note">No past exchanges.</div>
                    @endforelse
                </section>
            </div>
        </aside>
    </div>
</div>

<div class="ig-update-bar" id="igUpdateBar" role="status" aria-live="polite">
    <i class="bi bi-arrow-repeat"></i> Your exchange requests changed
    <button type="button" class="btn btn-sm btn-light rounded-pill" onclick="location.reload()">Refresh</button>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/integrity.js') }}?v={{ @filemtime(public_path('js/modules/integrity.js')) }}"></script>
@endpush
