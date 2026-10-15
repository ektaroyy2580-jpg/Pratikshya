@extends('layouts.app')
@php
    $editing = $bill->exists;
    $rows = old('items', $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit_price' => $i->unit_price, 'quantity' => $i->quantity, 'is_shared' => $i->is_shared])->all());
    if (! $rows) { $rows = [['name' => '', 'unit_price' => '', 'quantity' => 1, 'is_shared' => false]]; }
    $v = fn ($k, $d = null) => old($k, $bill->{$k} ?? $d);
    $num = fn ($x) => str_contains((string) $x, '.') ? (rtrim(rtrim((string) $x, '0'), '.') ?: '0') : (string) $x;
@endphp
@section('title', ($editing ? 'Edit '.$bill->title : 'New bill').' — प्रतीक्षा')
@include('bills.partials.assets')

@section('content')
<header class="pk-page-head bl-head">
    <div class="container">
        <div class="bl-crumbs mb-2">
            <a class="pk-breadcrumb" href="{{ route('bills.index') }}"><i class="bi bi-receipt me-1"></i>Bills</a>
            @if($editing)<i class="bi bi-chevron-right sep"></i><a class="pk-breadcrumb" href="{{ route('bills.show', $bill) }}">{{ $bill->title }}</a>@endif
        </div>
        <h1 class="mb-1 fade-up">{{ $editing ? 'Edit bill' : 'New group bill' }}</h1>
        <p class="pk-muted mb-0 fade-up">Add what was ordered. Totals update as you type — the server re-checks every paisa when you save.</p>
    </div>
</header>

<div class="container py-4">
    @include('bills.partials.errors')
    <form method="POST" action="{{ $editing ? route('bills.update', $bill) : route('bills.store') }}" id="billForm" novalidate>
        @csrf
        @if(! $editing && ! empty($cafePlace))
            @push('head')<link rel="stylesheet" href="{{ asset('css/modules/cafes.css') }}?v={{ @filemtime(public_path('css/modules/cafes.css')) }}">@endpush
            <input type="hidden" name="nearby_place_id" value="{{ $cafePlace->id }}">
            @if(! empty($cafeOrder))<input type="hidden" name="food_order_id" value="{{ $cafeOrder->id }}">@endif
            <div class="pk-card p-3 mb-4 d-flex flex-wrap align-items-center gap-3 reveal" style="border-color:#c9d4ff;background:linear-gradient(180deg,#f7f9ff,#fff)">
                <div style="width:56px;flex:0 0 56px;border-radius:14px;overflow:hidden;aspect-ratio:1">
                    @include('cafes.partials.photo', ['url' => \App\Services\Cafes\CafeImages::heroUrl($cafePlace), 'alt' => '', 'icon' => 'bi-cup-hot-fill', 'class' => 'w-100 h-100'])
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold">Splitting a bill at {{ $cafePlace->name }}</div>
                    <div class="small pk-muted">@if(! empty($cafeOrder))Items from your pre-order {{ $cafeOrder->reference() }} are filled in — add anything else you ordered, then save and invite friends.@else Add what your table ordered, then save and invite friends.@endif</div>
                </div>
                <a href="{{ route('cafes.show', $cafePlace) }}#bill" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to café</a>
            </div>
        @endif
        <div class="row g-4">
            <div class="col-lg-7">
                {{-- Details --}}
                <section class="pk-card p-3 p-md-4 mb-4 reveal" aria-labelledby="detailsH">
                    <h2 id="detailsH" class="bl-section-title mb-3"><i class="bi bi-card-text me-2 text-primary"></i>Details</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="title">Bill name</label>
                            <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="120" required value="{{ $v('title') }}" placeholder="Friday momo night">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="restaurant_name">Restaurant <span class="pk-muted fw-normal">(optional)</span></label>
                            <input class="form-control" id="restaurant_name" name="restaurant_name" maxlength="120" value="{{ $v('restaurant_name') }}" placeholder="e.g. Thamel Momo Ghar">
                        </div>
                    </div>
                </section>

                {{-- Items --}}
                <section class="pk-card p-3 p-md-4 mb-4 reveal" aria-labelledby="itemsH">
                    <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                        <h2 id="itemsH" class="bl-section-title"><i class="bi bi-list-check me-2 text-primary"></i>Items</h2>
                        <span class="bl-sub"><span id="itemCount">{{ count($rows) }}</span> items</span>
                    </div>
                    <div class="d-none d-sm-grid bl-sub fw-bold px-2 mb-1" style="grid-template-columns:minmax(0,1fr) 118px 78px auto 38px;gap:.5rem">
                        <span>Item</span><span>Price (NPR)</span><span>Qty</span><span>Shared?</span><span></span>
                    </div>
                    <div id="itemRows">
                        @foreach($rows as $i => $row)
                            <div class="bl-item-row" data-row>
                                @if(!empty($row['id']))<input type="hidden" name="items[{{ $i }}][id]" value="{{ $row['id'] }}">@endif
                                <input class="form-control name" name="items[{{ $i }}][name]" maxlength="80" value="{{ $row['name'] ?? '' }}" placeholder="Item name" aria-label="Item name">
                                <input class="form-control bl-num" name="items[{{ $i }}][unit_price]" inputmode="decimal" value="{{ isset($row['unit_price']) && $row['unit_price'] !== '' ? $num($row['unit_price']) : '' }}" placeholder="0.00" aria-label="Unit price in NPR">
                                <input class="form-control qty bl-num" name="items[{{ $i }}][quantity]" type="number" min="1" max="99" value="{{ $row['quantity'] ?? 1 }}" aria-label="Quantity">
                                <label class="bl-share-toggle" title="Shared items are split between several people">
                                    <input type="hidden" name="items[{{ $i }}][is_shared]" value="0">
                                    <input type="checkbox" name="items[{{ $i }}][is_shared]" value="1" @checked(filter_var($row['is_shared'] ?? false, FILTER_VALIDATE_BOOLEAN))><i class="bi bi-people"></i><span class="d-none d-md-inline">Shared</span>
                                </label>
                                <button type="button" class="bl-icon-btn" data-remove aria-label="Remove item"><i class="bi bi-trash3"></i></button>
                            </div>
                        @endforeach
                    </div>
                    @error('items')<div class="text-danger small fw-semibold mt-2">{{ $message }}</div>@enderror
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <button type="button" class="btn btn-ghost btn-sm" id="addItem"><i class="bi bi-plus-lg me-1"></i>Add item</button>
                        <div class="bl-quick" aria-label="Quick add">
                            @foreach([['Buff momo', 220], ['Chicken chowmein', 260], ['Thakali set', 450], ['Masala tea', 60], ['Lassi', 150]] as [$n, $p])
                                <button type="button" data-quick="{{ $n }}" data-price="{{ $p }}">+ {{ $n }}</button>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Charges --}}
                <section class="pk-card p-3 p-md-4 mb-4 reveal" aria-labelledby="chargesH">
                    <h2 id="chargesH" class="bl-section-title mb-3"><i class="bi bi-percent me-2 text-primary"></i>Discount, charges &amp; tip</h2>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <span class="form-label d-block">Discount <span class="pk-muted fw-normal">(applied before charges)</span></span>
                            <div class="bl-seg" role="radiogroup" aria-label="Discount type">
                                @foreach(['none' => 'None', 'amount' => 'NPR', 'percent' => '%'] as $k => $l)
                                    <input type="radio" name="discount_type" id="dt_{{ $k }}" value="{{ $k }}" @checked($v('discount_type', 'none') === $k)><label for="dt_{{ $k }}">{{ $l }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="discount_value">Discount value</label>
                            <div class="bl-input-group"><input class="form-control bl-num @error('discount_value') is-invalid @enderror" id="discount_value" name="discount_value" inputmode="decimal" value="{{ $num($v('discount_value', 0)) }}"><span class="suffix" id="discSuffix">NPR</span></div>
                            @error('discount_value')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="service_charge_percent">Service charge</label>
                            <div class="bl-input-group"><input class="form-control bl-num" id="service_charge_percent" name="service_charge_percent" inputmode="decimal" value="{{ $num($v('service_charge_percent', 10)) }}"><span class="suffix">%</span></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="vat_percent">VAT</label>
                            <div class="bl-input-group"><input class="form-control bl-num" id="vat_percent" name="vat_percent" inputmode="decimal" value="{{ $num($v('vat_percent', 13)) }}"><span class="suffix">%</span></div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="vat_on_service_charge" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="vat_on_service_charge" name="vat_on_service_charge" value="1" @checked($v('vat_on_service_charge', true))>
                                <label class="form-check-label small fw-semibold" for="vat_on_service_charge">VAT on service charge</label>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="tip_amount">Tip</label>
                            <div class="bl-input-group pre"><span class="suffix">NPR</span><input class="form-control bl-num" id="tip_amount" name="tip_amount" inputmode="decimal" value="{{ $num($v('tip_amount', 0)) }}"></div>
                        </div>
                        <div class="col-md-7">
                            <span class="form-label d-block">Tip split</span>
                            <div class="bl-seg" role="radiogroup" aria-label="Tip allocation">
                                @foreach(['equal' => 'Equally', 'proportional' => 'By share', 'custom' => 'Custom'] as $k => $l)
                                    <input type="radio" name="tip_allocation" id="ta_{{ $k }}" value="{{ $k }}" @checked($v('tip_allocation', 'equal') === $k)><label for="ta_{{ $k }}">{{ $l }}</label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <p class="bl-sub mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Order: items → discount → 10% service charge → 13% VAT on (subtotal − discount + service charge) → tip. Turn the switch off if your receipt charges VAT before service charge.</p>
                </section>

                {{-- Split --}}
                <section class="pk-card p-3 p-md-4 mb-4 reveal" aria-labelledby="splitH">
                    <h2 id="splitH" class="bl-section-title mb-3"><i class="bi bi-diagram-3 me-2 text-primary"></i>How to split</h2>
                    <div class="bl-seg mb-2" role="radiogroup" aria-label="Split mode">
                        @foreach(['equal' => ['bi-distribute-horizontal', 'Equally'], 'itemized' => ['bi-ui-checks-grid', 'By item'], 'custom' => ['bi-sliders', 'Custom amounts']] as $k => [$ic, $l])
                            <input type="radio" name="split_mode" id="sm_{{ $k }}" value="{{ $k }}" @checked($v('split_mode', 'equal') === $k)><label for="sm_{{ $k }}"><i class="bi {{ $ic }}"></i>{{ $l }}</label>
                        @endforeach
                    </div>
                    <p class="bl-sub mb-3" id="splitHelp"></p>
                    <div class="form-check form-switch">
                        <input type="hidden" name="members_can_select" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="members_can_select" name="members_can_select" value="1" @checked($v('members_can_select', true))>
                        <label class="form-check-label small fw-semibold" for="members_can_select">Let friends tick their own items (itemized split)</label>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="payment_note">How to pay you <span class="pk-muted fw-normal">(optional, visible to people on this bill)</span></label>
                        <input class="form-control" id="payment_note" name="payment_note" maxlength="160" value="{{ $v('payment_note') }}" placeholder="e.g. Cash is fine, or eSewa to my number">
                    </div>
                </section>
            </div>

            {{-- Live preview --}}
            <div class="col-lg-5">
                <aside class="bl-sticky" aria-label="Live total">
                    <div class="pk-card p-3 p-md-4 reveal">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="pk-eyebrow"><span class="live-dot"></span> Live total</span>
                            <span class="chip">NPR</span>
                        </div>
                        <div class="bl-total bl-num mt-3"><span class="cur">NPR</span><span id="pvGrand" data-paisa="0">0.00</span></div>
                        <ul class="bl-receipt mt-3">
                            <li><span>Subtotal</span><span id="pvSubtotal">0.00</span></li>
                            <li class="neg" id="pvDiscRow"><span>Discount</span><span id="pvDiscount">0.00</span></li>
                            <li><span>Service charge <span class="pk-muted" id="pvScPct"></span></span><span id="pvService">0.00</span></li>
                            <li><span>VAT <span class="pk-muted" id="pvVatPct"></span></span><span id="pvVat">0.00</span></li>
                            <li><span>Tip</span><span id="pvTip">0.00</span></li>
                            <li class="grand"><span>Total</span><span>NPR <span id="pvGrand2">0.00</span></span></li>
                        </ul>
                        <div class="mt-3 p-3 rounded-4" style="background:var(--pk-bg)">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div>
                                    <div class="bl-sub fw-semibold">Equal-split preview</div>
                                    <div class="fw-bold fs-5 bl-num">NPR <span id="pvEach">0.00</span> <span class="bl-sub fw-semibold">each</span></div>
                                </div>
                                <div class="d-flex align-items-center gap-1" role="group" aria-label="People in preview">
                                    <button type="button" class="bl-icon-btn" id="pplMinus" aria-label="Fewer people" style="border:1px solid var(--pk-line)"><i class="bi bi-dash-lg"></i></button>
                                    <span class="fw-bold px-2 bl-num" id="pplCount" aria-live="polite">{{ max(1, $peopleCount) }}</span>
                                    <button type="button" class="bl-icon-btn" id="pplPlus" aria-label="More people" style="border:1px solid var(--pk-line)"><i class="bi bi-plus-lg"></i></button>
                                </div>
                            </div>
                            <div class="bl-sub mt-1" id="pvSpread"></div>
                        </div>
                        <button class="btn btn-pk w-100 mt-3" type="submit" id="saveBtn"><i class="bi bi-check2-circle me-1"></i>{{ $editing ? 'Save changes' : 'Create bill & get invite link' }}</button>
                        @if($editing)<a href="{{ route('bills.show', $bill) }}" class="btn btn-ghost w-100 mt-2">Cancel</a>@endif
                        <p class="bl-sub mt-2 mb-0 text-center">Preview only — the server recalculates on save.</p>
                    </div>
                </aside>
            </div>
        </div>
    </form>
</div>

<template id="itemTpl">
    <div class="bl-item-row" data-row>
        <input class="form-control name" name="items[__i__][name]" maxlength="80" placeholder="Item name" aria-label="Item name">
        <input class="form-control bl-num" name="items[__i__][unit_price]" inputmode="decimal" placeholder="0.00" aria-label="Unit price in NPR">
        <input class="form-control qty bl-num" name="items[__i__][quantity]" type="number" min="1" max="99" value="1" aria-label="Quantity">
        <label class="bl-share-toggle" title="Shared items are split between several people">
            <input type="hidden" name="items[__i__][is_shared]" value="0">
            <input type="checkbox" name="items[__i__][is_shared]" value="1"><i class="bi bi-people"></i><span class="d-none d-md-inline">Shared</span>
        </label>
        <button type="button" class="bl-icon-btn" data-remove aria-label="Remove item"><i class="bi bi-trash3"></i></button>
    </div>
</template>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/bills-calc.js') }}?v={{ filemtime(public_path('js/modules/bills-calc.js')) }}"></script>
<script src="{{ asset('js/modules/bills-form.js') }}?v={{ filemtime(public_path('js/modules/bills-form.js')) }}"></script>
@endpush
