@extends('layouts.app')
@section('title', 'Checkout — Preorder — प्रतीक्षा')

@push('head')
    @include('preorder.partials.head')
@endpush

@section('content')
@use('App\Services\Preorder\Money')
@php $mode = old('payment_mode', 'demo'); @endphp
<header class="pk-page-head">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3 fade-up">
            <a href="{{ \App\Services\Cafes\CafeHub::restaurantUrl($restaurant) }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>{{ $restaurant->name }}</a>
            <a href="{{ route('preorder.cart') }}" class="pk-breadcrumb"><i class="bi bi-basket me-1"></i>Edit cart</a>
        </div>
        <div class="d-flex align-items-center gap-3 fade-up">
            <div style="width:64px;flex:0 0 64px">@include('preorder.partials.food-art', ['art' => $restaurant->art, 'class' => 'sq', 'label' => $restaurant->name, 'photo' => $restaurant->image_path])</div>
            <div class="min-w-0">
                <h1 class="h2 mb-1">Pre-order checkout</h1>
                <p class="pk-muted mb-0">{{ $restaurant->name }} · pickup near {{ $restaurant->location->name }}</p>
            </div>
        </div>
    </div>
</header>

<div class="container py-4">
    @include('preorder.partials.errors')

    <form method="POST" action="{{ route('preorder.place') }}" id="poCheckout" novalidate>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
        <div class="row g-4">
            <div class="col-lg-7 d-flex flex-column gap-4">
                <section class="reveal" aria-labelledby="timingH">
                    <h2 id="timingH" class="h6 mb-2"><span class="badge rounded-pill text-bg-light me-2">1</span>Timing check</h2>
                    @include('preorder.partials.timing-card', ['timing' => $timing])
                    @if($timing['needs_confirmation'])
                        <div class="form-check mt-3 p-3 ps-5 rounded-3" style="background:var(--pk-danger-tint);border:1px solid #ffd0dc">
                            <input class="form-check-input" type="checkbox" name="acknowledge_conflict" value="1" id="ack" @checked(old('acknowledge_conflict'))>
                            <label class="form-check-label fw-semibold" for="ack" style="color:#a3153a">I understand this order may not be ready before my turn. If I'm called, I'll go to the counter first — my queue place won't be held or changed for food.</label>
                        </div>
                    @endif
                </section>

                <section class="reveal" style="--d:.05s" aria-labelledby="payH">
                    <h2 id="payH" class="h6 mb-2"><span class="badge rounded-pill text-bg-light me-2">2</span>Payment</h2>
                    <div class="d-flex flex-column gap-2">
                        <label class="pay-opt">
                            <input class="form-check-input" type="radio" name="payment_mode" value="demo" @checked($mode === 'demo')>
                            <span>
                                <span class="d-flex align-items-center gap-2 fw-bold">Demo payment <span class="po-demo">Demo mode</span></span>
                                <span class="small d-block mt-1" style="color:var(--pk-ink-2)">Demo mode — no money is charged. Real payments require a configured provider (e.g. eSewa/Khalti) with verified callbacks.</span>
                            </span>
                        </label>
                        <label class="pay-opt">
                            <input class="form-check-input" type="radio" name="payment_mode" value="cash_on_pickup" @checked($mode === 'cash_on_pickup')>
                            <span>
                                <span class="fw-bold d-block">Cash on pickup</span>
                                <span class="small d-block mt-1" style="color:var(--pk-ink-2)">Pay the café when you collect. The app marks this order unpaid.</span>
                            </span>
                        </label>
                    </div>
                    <p class="small pk-muted mt-2 mb-0"><i class="bi bi-lock me-1"></i>We never ask for or store card details.</p>
                </section>

                <section class="reveal" style="--d:.1s" aria-labelledby="noteH">
                    <h2 id="noteH" class="h6 mb-2"><span class="badge rounded-pill text-bg-light me-2">3</span>Special instructions</h2>
                    <textarea name="special_instructions" class="form-control" rows="2" maxlength="500" placeholder="Allergies, spice level, packing…" aria-labelledby="noteH">{{ old('special_instructions', $instructions) }}</textarea>
                    <div class="pk-card p-3 mt-3 d-flex gap-3 align-items-start" style="box-shadow:none">
                        <span class="pk-icon green" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-bag-check"></i></span>
                        <div class="small"><div class="fw-bold mb-1">Pickup instructions</div><div style="color:var(--pk-ink-2)">{{ $restaurant->pickup_instructions ?: 'Collect at the counter and show your pickup code.' }}</div></div>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <div class="pk-card p-4 reveal" style="position:sticky;top:88px;--d:.08s">
                    <h2 class="h6 mb-2">Your order</h2>
                    @foreach($lines as $l)
                        <div class="d-flex justify-content-between gap-2 small py-1">
                            <span class="min-w-0"><span class="fw-bold">{{ $l['qty'] }}×</span> {{ $l['item']->name }}@if($l['note'])<span class="d-block pk-muted">“{{ $l['note'] }}”</span>@endif</span>
                            <span class="text-nowrap">{{ Money::format($l['unit_paisa'] * $l['qty'], true) }}</span>
                        </div>
                    @endforeach
                    <hr class="my-2" style="border-color:var(--pk-line);opacity:1">
                    @include('preorder.partials.totals', ['quote' => $quote])
                    <div class="small pk-muted mt-2"><i class="bi bi-stopwatch me-1"></i>Estimated ready ~{{ $timing['prep_minutes'] }} min after the café accepts · free cancellation until then.</div>
                    <button type="submit" class="btn btn-pk w-100 mt-3" id="poPlace">
                        <i class="bi bi-bag-check me-1"></i>Place order · {{ Money::format($quote['total'], true) }}
                    </button>
                    @if($timing['needs_confirmation'])<div class="small text-center mt-2" style="color:#a3153a">Tick the confirmation above to continue.</div>@endif
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/preorder.js') }}?v={{ @filemtime(public_path('js/modules/preorder.js')) }}"></script>
<script>PreorderUI.checkout();</script>
@endpush
