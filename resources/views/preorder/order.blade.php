@extends('layouts.app')
@section('title', $order->reference().' — Preorder — प्रतीक्षा')

@push('head')
    @include('preorder.partials.head')
@endpush

@section('content')
@use('App\Services\Preorder\Money')
@php
    $s = $order->stateArray();
    $isOwner = (int) $order->user_id === (int) auth()->id();
    if (! $isOwner) { $s['pickup_code'] = null; } // merchants verify the code; they don't get to see it here
    $steps = [['placed', 'Placed', 'bi-send'], ['accepted', 'Accepted', 'bi-hand-thumbs-up'], ['preparing', 'Preparing', 'bi-fire'], ['ready', 'Ready', 'bi-bell'], ['collected', 'Collected', 'bi-bag-check']];
    $ended = in_array($order->status, ['cancelled', 'rejected'], true);
    $timing = $order->timing_snapshot;
@endphp
<header class="pk-page-head">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3 fade-up">
            @if($order->restaurant)<a href="{{ \App\Services\Cafes\CafeHub::restaurantUrl($order->restaurant, 'order') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>{{ $order->restaurant->name }}</a>@endif
            <a href="{{ route('preorder.orders') }}" class="pk-breadcrumb"><i class="bi bi-clock-history me-1"></i>My orders</a>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1 fade-up">
            <h1 class="h2 mb-0">Order {{ $order->reference() }}</h1>
            @if($order->isOpen())<span class="chip"><span class="live-dot"></span>Live</span>@endif
        </div>
        <p class="pk-muted mb-0 fade-up">{{ $order->restaurant->name }} · placed {{ $order->created_at->format('M j, g:i A') }}</p>
    </div>
</header>

<div class="container py-4">
    @include('preorder.partials.errors')
    <div class="alert alert-warning small" id="poConn" hidden role="status"><i class="bi bi-wifi-off me-1"></i>Connection lost — retrying. Your order is safe.</div>

    <div class="row g-4">
        <div class="col-lg-7 d-flex flex-column gap-4">
            <section class="pk-card p-4 reveal {{ $order->status === 'ready' ? 'po-ready' : '' }}" aria-labelledby="poStatusLabel">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <div class="small pk-muted fw-semibold">Status</div>
                        <div class="h4 mb-0" id="poStatusLabel">{{ $s['status_label'] }}</div>
                    </div>
                    @unless($ended || $order->status === 'collected')
                        <div class="text-end">
                            <div class="small pk-muted fw-semibold">Estimated ready</div>
                            <div class="h4 mb-0" id="poEta">{{ $order->status === 'ready' ? 'Ready now' : ($s['minutes_to_ready'] !== null ? '~'.$s['minutes_to_ready'].' min' : ($s['estimated_ready_label'] ?? '—')) }}</div>
                        </div>
                    @endunless
                </div>

                @if($ended)
                    <div class="alert {{ $order->status === 'rejected' ? 'alert-danger' : 'alert-warning' }} mt-3 mb-0" id="poReason">
                        <i class="bi bi-x-circle me-1"></i><span>{{ $order->status_reason ?: $s['status_label'] }}</span>
                        @if($order->payment_status === 'demo_paid')<div class="small mt-1">Demo payment — no real money was taken, so there is nothing to refund.</div>@endif
                    </div>
                @else
                    <div class="po-track" role="list" aria-label="Order progress">
                        @foreach($steps as $i => [$key, $label, $icon])
                            @php $cls = $order->status === 'collected' ? 'done' : ($i < $s['step'] ? 'done' : ($i === $s['step'] ? 'now' : '')); @endphp
                            <div class="st {{ $cls }}" role="listitem"><div class="dot"><i class="bi {{ $icon }}"></i></div>{{ $label }}</div>
                        @endforeach
                    </div>
                    <div id="poReason" hidden><span></span></div>
                @endif

                <div id="poCodeBox" class="mt-3 p-3 rounded-4 text-center" style="background:var(--pk-tint)" @if(! $s['pickup_code']) hidden @endif>
                    <div class="small fw-bold text-uppercase" style="letter-spacing:.06em;color:var(--pk-blue)">Pickup code</div>
                    <div class="po-code my-1" id="poCode">{{ $s['pickup_code'] }}</div>
                    <div class="small" style="color:var(--pk-ink-2)">Show this at the counter — the café needs it to hand over your food.</div>
                </div>
                @if($order->status === 'placed')
                    <p class="small pk-muted mt-3 mb-0" id="poPlacedHint"><i class="bi bi-hourglass me-1"></i>Your pickup code appears once {{ $order->restaurant->name }} accepts the order. This page updates automatically.</p>
                @endif

                @if($isOwner)
                    <form method="POST" action="{{ route('preorder.order.cancel', $order) }}" class="mt-3" id="poCancel" @if(! $order->userCanCancel()) hidden @endif onsubmit="return confirm('Cancel this order? It is free until the café accepts.')">
                        @csrf
                        <button class="btn btn-ghost btn-sm text-danger"><i class="bi bi-x-lg me-1"></i>Cancel order (free)</button>
                        <span class="small pk-muted ms-2">Free until the café accepts.</span>
                    </form>
                @endif
            </section>

            <section class="pk-card p-4 reveal" style="--d:.05s">
                <div class="d-flex gap-3 align-items-start">
                    <span class="pk-icon green"><i class="bi bi-geo-alt"></i></span>
                    <div class="small">
                        <div class="fw-bold mb-1">Pickup at {{ $order->restaurant->name }}</div>
                        <div style="color:var(--pk-ink-2)">{{ $order->restaurant->pickup_instructions ?: 'Collect at the counter and show your pickup code.' }}</div>
                        @if($order->restaurant->merchant_contact)<div class="pk-muted mt-1"><i class="bi bi-telephone me-1"></i>{{ $order->restaurant->merchant_contact }}</div>@endif
                    </div>
                </div>
            </section>

            @if($order->ticket && $isOwner)
                <section class="pk-card p-4 reveal" style="--d:.08s">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="pk-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <div class="small flex-grow-1 min-w-0">
                            <div class="fw-bold">Queue context: {{ $order->ticket->token() }} · {{ $order->ticket->service->name }}</div>
                            <div class="pk-muted mb-2">At order time: {{ $timing['headline'] ?? '—' }}. This order is independent — it never changes your queue place, and if you're called your order stays as it is.</div>
                            <a href="{{ route('ticket', $order->ticket) }}" class="btn btn-ghost btn-sm">Queue status</a>
                        </div>
                    </div>
                </section>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="pk-card p-4 reveal" style="position:sticky;top:88px;--d:.06s">
                <h2 class="h6 mb-2">Receipt</h2>
                @foreach($order->items as $it)
                    <div class="po-line">
                        @include('preorder.partials.food-art', ['art' => $it->art, 'label' => $it->name, 'photo' => \App\Services\Cafes\CafeImages::dishPath($it->name, $it->art)])
                        <div class="min-w-0 small">
                            <div class="fw-bold">{{ $it->qty }}× {{ $it->name }}</div>
                            <div class="pk-muted">{{ Money::format($it->unit_price) }} each</div>
                            @if($it->notes)<div class="pk-muted">“{{ $it->notes }}”</div>@endif
                        </div>
                        <div class="fw-bold small">{{ Money::format($it->line_total) }}</div>
                    </div>
                @endforeach
                @if($order->special_instructions)
                    <div class="small p-2 rounded-3 my-2" style="background:var(--pk-bg)"><i class="bi bi-chat-left-text me-1"></i>{{ $order->special_instructions }}</div>
                @endif
                @include('preorder.partials.totals', ['order' => $order])
                <div class="d-flex justify-content-between align-items-center mt-3 small">
                    <span class="pk-muted">Payment</span>
                    @if($order->payment_status === 'demo_paid')
                        <span class="po-demo"><i class="bi bi-check2"></i>Demo paid — no money charged</span>
                    @else
                        <span class="chip" style="background:#eef0f6;color:var(--pk-ink-2)">Unpaid · cash on pickup</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<div class="visually-hidden" aria-live="polite" id="poLive"></div>
@endsection

@push('scripts')
@if($order->isOpen())
<script src="{{ asset('js/modules/preorder.js') }}?v={{ @filemtime(public_path('js/modules/preorder.js')) }}"></script>
<script>PreorderUI.order({ url: @json(route('preorder.order.state', $order)), initial: @json($order->status) });</script>
@endif
@endpush
