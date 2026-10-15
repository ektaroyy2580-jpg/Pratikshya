@extends('layouts.app')
@section('title', 'Settle '.$bill->title.' — प्रतीक्षा')
@use('App\Services\Bills\BillCalculator')
@use('App\Models\BillPayment')
@include('bills.partials.assets')
@php
    $isOwner = $me->isOwner();
    $myBal = $balances['rows'][$me->id] ?? null;
    $people = $bill->joinedParticipants;
    $circ = 2 * M_PI * 54;
    $stateLabel = ['paid' => 'Paid', 'claimed' => 'Awaiting confirmation', 'due' => 'Due', 'covered' => 'Paid the restaurant'];
    $payLabel = ['claimed' => 'Claimed — not yet confirmed', 'confirmed_by_owner' => 'Confirmed by owner', 'disputed' => 'Not received'];
@endphp

@section('content')
<header class="pk-page-head bl-head">
    <div class="container">
        <div class="bl-crumbs mb-2">
            <a class="pk-breadcrumb" href="{{ route('bills.index') }}"><i class="bi bi-receipt me-1"></i>Bills</a>
            <i class="bi bi-chevron-right sep"></i><a class="pk-breadcrumb text-truncate" style="max-width:200px" href="{{ route('bills.show', $bill) }}">{{ $bill->title }}</a>
            <i class="bi bi-chevron-right sep"></i><span class="pk-breadcrumb">Settle</span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-4">
            <div class="bl-ring {{ $balances['progress'] >= 100 ? 'done' : '' }}" aria-label="{{ $balances['progress'] }}% collected" role="img">
                <svg viewBox="0 0 132 132" aria-hidden="true">
                    <defs><linearGradient id="blGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f5bea"/><stop offset="1" stop-color="#7046e8"/></linearGradient></defs>
                    <circle class="track" cx="66" cy="66" r="54"/>
                    <circle class="bar" cx="66" cy="66" r="54" stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ }}" data-offset="{{ $circ * (1 - $balances['progress'] / 100) }}"/>
                </svg>
                <div class="lbl"><div><b>{{ $balances['progress'] }}%</b><small>collected</small></div></div>
            </div>
            <div class="min-w-0 fade-up">
                <span class="bl-pill {{ $bill->status }} mb-2">{{ ucfirst($bill->status) }}</span>
                <h1 class="mb-1">Settlement</h1>
                <div class="pk-muted fw-semibold bl-num">
                    NPR {{ BillCalculator::money($balances['collected']) }} of {{ BillCalculator::money($balances['to_collect']) }} confirmed
                    @if($balances['pending'])· <span style="color:var(--pk-violet)">NPR {{ BillCalculator::money($balances['pending']) }} awaiting confirmation</span>@endif
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container py-4">
    @include('bills.partials.errors')
    <div class="row g-4">
        <div class="col-lg-7">
            @if($bill->status === 'open')
                <div class="pk-card p-4 mb-4 text-center">
                    <span class="pk-icon amber mb-2"><i class="bi bi-hourglass"></i></span>
                    <h2 class="h5">Not settling yet</h2>
                    <p class="pk-muted mb-3">{{ $isOwner ? 'Lock the bill when everyone\'s items are right, then payments can be recorded.' : 'The owner is still finalising the bill. You\'ll be able to record your payment once it\'s locked.' }}</p>
                    <a class="btn btn-ghost" href="{{ route('bills.show', $bill) }}">Back to bill</a>
                </div>
            @endif

            <section class="pk-card p-3 p-md-4 mb-4 reveal" aria-labelledby="trackH">
                <h2 id="trackH" class="bl-section-title mb-2">Who has paid</h2>
                @foreach($people as $p)
                    @php $b = $balances['rows'][$p->id]; @endphp
                    <div class="bl-settle-row">
                        @include('bills.partials.avatar', ['p' => $p])
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold text-truncate">{{ $p->display_name }}@if($p->id === $me->id) <span class="chip py-0 px-2 ms-1">You</span>@endif</div>
                            <div class="bl-sub bl-num">Share NPR {{ BillCalculator::money($b['share']) }}@if(! $b['is_owner']) · confirmed {{ BillCalculator::money($b['confirmed']) }}@if($b['claimed']) · claimed {{ BillCalculator::money($b['claimed']) }}@endif @endif</div>
                        </div>
                        <span class="bl-pill {{ $b['state'] }}">@if($b['state'] === 'paid')<i class="bi bi-check2"></i>@endif{{ $stateLabel[$b['state']] }}</span>
                    </div>
                @endforeach
            </section>

            <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="histH">
                <h2 id="histH" class="bl-section-title mb-2">{{ $isOwner ? 'Payment claims' : 'My payments' }}</h2>
                @forelse($payments as $pay)
                    <div class="bl-settle-row">
                        <span class="pk-icon {{ $pay->status === 'confirmed_by_owner' ? 'green' : ($pay->status === 'disputed' ? 'rose' : 'violet') }}" style="width:38px;height:38px;font-size:1rem"><i class="bi {{ $pay->status === 'confirmed_by_owner' ? 'bi-check2-circle' : ($pay->status === 'disputed' ? 'bi-x-circle' : 'bi-hourglass-split') }}"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold bl-num">NPR {{ BillCalculator::money(BillCalculator::toPaisa($pay->amount)) }} <span class="bl-sub fw-semibold">· {{ $pay->methodLabel() }}</span></div>
                            <div class="bl-sub">{{ $isOwner ? $pay->participant->display_name.' · ' : '' }}{{ $pay->created_at->diffForHumans() }}@if($pay->reference) · Ref {{ $pay->reference }}@endif @if($pay->note) · “{{ $pay->note }}”@endif</div>
                        </div>
                        <span class="bl-pill {{ $pay->status }}">{{ $payLabel[$pay->status] }}</span>
                        @if($isOwner && in_array($pay->status, ['claimed', 'disputed']) && in_array($bill->status, ['settling', 'settled']))
                            <div class="d-flex gap-2 w-100 justify-content-end">
                                @if($pay->status === 'claimed')
                                    <form method="POST" action="{{ route('bills.payments.dispute', [$bill, $pay]) }}">@csrf<button class="btn btn-ghost btn-sm"><i class="bi bi-x-lg me-1"></i>Not received</button></form>
                                @endif
                                <form method="POST" action="{{ route('bills.payments.confirm', [$bill, $pay]) }}">@csrf<button class="btn btn-pk btn-sm"><i class="bi bi-check2 me-1"></i>I received this</button></form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bl-empty py-4">
                        <span class="pk-icon mb-2"><i class="bi bi-inbox"></i></span>
                        <div class="fw-bold">No payments recorded yet</div>
                        <div class="bl-sub">{{ $isOwner ? 'When friends tap “I paid”, their claims appear here for you to confirm.' : 'Record your payment once you have paid.' }}</div>
                    </div>
                @endforelse
            </section>
        </div>

        <div class="col-lg-5 order-first order-lg-0">
            <div class="bl-sticky d-flex flex-column gap-4">
                @if(! $isOwner && $myBal)
                    <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="payH">
                        <h2 id="payH" class="bl-section-title mb-1">Pay {{ \Illuminate\Support\Str::of($bill->owner->name)->before(' ') }}</h2>
                        <div class="bl-total bl-num my-2"><span class="cur">NPR</span>{{ BillCalculator::money($myBal['remaining']) }}</div>
                        <div class="bl-sub mb-3">left to confirm of your NPR {{ BillCalculator::money($myBal['share']) }} share</div>
                        @if($bill->payment_note)
                            <div class="p-2 px-3 rounded-3 small mb-3" style="background:var(--pk-bg)"><i class="bi bi-wallet2 me-1 text-primary"></i>{{ $bill->payment_note }}</div>
                        @endif
                        @if($bill->status === 'settling' && $myBal['claimable'] > 0)
                            <form method="POST" action="{{ route('bills.payments.claim', $bill) }}" id="claimForm">
                                @csrf
                                <span class="form-label d-block">How did you pay?</span>
                                <div class="row g-2 mb-3">
                                    @foreach(['cash' => 'bi-cash-coin', 'esewa_link' => 'bi-phone', 'khalti_link' => 'bi-phone-vibrate', 'demo' => 'bi-joystick'] as $m => $ic)
                                        <div class="col-3">
                                            <input type="radio" class="btn-check" name="method" id="m_{{ $m }}" value="{{ $m }}" @checked(old('method', 'cash') === $m)>
                                            <label class="bl-method" for="m_{{ $m }}"><i class="bi {{ $ic }}"></i>{{ ['cash' => 'Cash', 'esewa_link' => 'eSewa', 'khalti_link' => 'Khalti', 'demo' => 'Demo'][$m] }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <label class="form-label" for="amount">Amount</label>
                                <div class="bl-input-group pre mb-2"><span class="suffix">NPR</span><input class="form-control bl-num @error('amount') is-invalid @enderror" id="amount" name="amount" inputmode="decimal" value="{{ old('amount', BillCalculator::toDecimal($myBal['claimable'])) }}" required></div>
                                @error('amount')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                                <label class="form-label" for="reference">Reference <span class="pk-muted fw-normal">(optional)</span></label>
                                <input class="form-control mb-3" id="reference" name="reference" maxlength="60" value="{{ old('reference') }}" placeholder="e.g. eSewa transaction ID">
                                <button class="btn btn-pk w-100" type="submit"><i class="bi bi-send-check me-1"></i>I paid — ask for confirmation</button>
                            </form>
                        @elseif($myBal['remaining'] === 0)
                            <div class="alert alert-success mb-0 d-flex gap-2 align-items-center"><i class="bi bi-patch-check-fill fs-5"></i><span class="fw-semibold">You're all settled. {{ \Illuminate\Support\Str::of($bill->owner->name)->before(' ') }} confirmed your payment.</span></div>
                        @elseif($myBal['claimed'] > 0)
                            <div class="alert alert-warning mb-0 d-flex gap-2 align-items-center"><i class="bi bi-hourglass-split fs-5"></i><span class="fw-semibold">Waiting for {{ \Illuminate\Support\Str::of($bill->owner->name)->before(' ') }} to confirm NPR {{ BillCalculator::money($myBal['claimed']) }}.</span></div>
                        @endif
                    </section>
                @endif

                @if($isOwner)
                    <section class="pk-card p-3 p-md-4 reveal">
                        <h2 class="bl-section-title mb-2">You paid the restaurant</h2>
                        <ul class="bl-receipt">
                            <li><span>Bill total</span><span>{{ BillCalculator::money(BillCalculator::toPaisa($bill->total)) }}</span></li>
                            <li><span>Your own share</span><span>{{ BillCalculator::money($balances['rows'][$me->id]['share'] ?? 0) }}</span></li>
                            <li><span>Confirmed from friends</span><span>{{ BillCalculator::money($balances['collected']) }}</span></li>
                            <li class="grand"><span>Still to collect</span><span>NPR {{ BillCalculator::money($balances['remaining']) }}</span></li>
                        </ul>
                    </section>
                @endif

                <div class="bl-demo-note"><i class="bi bi-shield-exclamation mt-1"></i><span><strong>Honest payments:</strong> eSewa and Khalti are not connected — pay in their own apps. Tapping “I paid” only records a <em>claim</em>; a share counts as paid only after the bill owner confirms they received it. Nothing is ever marked paid automatically.</span></div>
                <a href="{{ route('bills.show', $bill) }}" class="btn btn-ghost"><i class="bi bi-arrow-left me-1"></i>Back to bill details</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // One-shot ring fill.
    requestAnimationFrame(() => setTimeout(() => document.querySelectorAll('.bl-ring .bar').forEach(c => { c.style.strokeDashoffset = c.dataset.offset; }), 120));
    document.getElementById('claimForm')?.addEventListener('submit', e => { const b = e.target.querySelector('button[type=submit]'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Recording…'; });
</script>
@endpush
