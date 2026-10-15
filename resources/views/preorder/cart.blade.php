@extends('layouts.app')
@section('title', 'Your cart — Preorder — प्रतीक्षा')

@push('head')
    @include('preorder.partials.head')
@endpush

@section('content')
@use('App\Services\Preorder\Money')
<header class="pk-page-head">
    <div class="container">
        <a href="{{ $restaurant ? \App\Services\Cafes\CafeHub::restaurantUrl($restaurant) : route('preorder.index') }}" class="pk-breadcrumb fade-up d-inline-block mb-3"><i class="bi bi-arrow-left me-1"></i>{{ $restaurant ? 'Back to '.$restaurant->name : 'All cafés' }}</a>
        <h1 class="h2 mb-1 fade-up">Your cart</h1>
        <p class="pk-muted mb-0 fade-up">{{ $restaurant ? 'Ordering from '.$restaurant->name : 'Nothing here yet' }}</p>
    </div>
</header>

<div class="container py-4">
    @include('preorder.partials.errors')

    @if(! $lines)
        <div class="pk-card p-5 text-center reveal">
            @include('preorder.partials.food-art', ['art' => 'momo', 'class' => 'mx-auto mb-3', 'label' => 'Empty plate'])
            <div class="fw-bold fs-5">Your cart is empty</div>
            <p class="pk-muted">Browse participating cafés near your queue and add something tasty.</p>
            <a href="{{ route('preorder.index') }}" class="btn btn-pk">Browse cafés</a>
        </div>
        <style>.pk-card > .fa { max-width: 220px; }</style>
    @else
        @if($unavailable)
            <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle me-1"></i><strong>{{ implode(', ', $unavailable) }}</strong> {{ count($unavailable) === 1 ? 'is' : 'are' }} no longer available. Remove {{ count($unavailable) === 1 ? 'it' : 'them' }} to check out.</div>
        @endif
        <div class="row g-4">
            <div class="col-lg-7">
                <section class="pk-card p-3 p-md-4 reveal" aria-label="Items">
                    @foreach($lines as $l)
                        @php $item = $l['item']; @endphp
                        <div class="po-line">
                            @include('preorder.partials.food-art', ['art' => $item->art, 'label' => $item->name, 'photo' => $item->image_path])
                            <div class="min-w-0">
                                <div class="fw-bold">{{ $item->name }} @unless($l['available'])<span class="chip ms-1" style="background:var(--pk-danger-tint);color:var(--pk-danger)">Unavailable</span>@endunless</div>
                                <div class="small pk-muted">{{ Money::format($l['unit_paisa'], true) }} each · ~{{ $item->prep_minutes }} min</div>
                                <form method="POST" action="{{ route('preorder.cart.set', $item->id) }}" class="d-flex gap-2 mt-2">
                                    @csrf
                                    <input type="hidden" name="qty" value="{{ $l['qty'] }}">
                                    <label class="visually-hidden" for="note-{{ $item->id }}">Note for {{ $item->name }}</label>
                                    <input id="note-{{ $item->id }}" name="note" class="form-control form-control-sm" style="padding:.35rem .6rem" maxlength="200" placeholder="Note (e.g. extra achar)" value="{{ $l['note'] }}">
                                    <button class="btn btn-ghost btn-sm" style="padding:.35rem .7rem">Save</button>
                                </form>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold mb-2">{{ Money::format($l['line_paisa'], true) }}</div>
                                <form method="POST" action="{{ route('preorder.cart.set', $item->id) }}">
                                    @csrf
                                    <div class="stepper">
                                        <button type="submit" name="qty" value="{{ $l['qty'] - 1 }}" aria-label="One less {{ $item->name }}"><i class="bi {{ $l['qty'] === 1 ? 'bi-trash3' : 'bi-dash-lg' }}"></i></button>
                                        <output>{{ $l['qty'] }}</output>
                                        <button type="submit" name="qty" value="{{ $l['qty'] + 1 }}" aria-label="One more {{ $item->name }}" @disabled(! $l['available'] || $l['qty'] >= config('preorder.max_qty_per_item', 20))><i class="bi bi-plus-lg"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between align-items-center pt-3 mt-2 border-top">
                        <a href="{{ \App\Services\Cafes\CafeHub::restaurantUrl($restaurant) }}" class="small fw-semibold text-decoration-none"><i class="bi bi-plus-circle me-1"></i>Add more items</a>
                        <form method="POST" action="{{ route('preorder.cart.clear') }}">@csrf<button class="btn btn-link btn-sm text-danger text-decoration-none p-0">Clear cart</button></form>
                    </div>
                </section>

                @if($timing)
                    <div class="mt-4 reveal" style="--d:.05s">
                        <h2 class="h6 mb-2">Will it fit your wait?</h2>
                        @include('preorder.partials.timing-card', ['timing' => $timing])
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="pk-card p-4 reveal" style="position:sticky;top:88px;--d:.08s">
                    <h2 class="h6 mb-3">Order summary</h2>
                    @include('preorder.partials.totals', ['quote' => $quote])
                    <form method="POST" action="{{ route('preorder.cart.instructions') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="to_checkout" value="1">
                        <label for="special_instructions" class="form-label">Special instructions <span class="pk-muted fw-normal">(optional)</span></label>
                        <textarea id="special_instructions" name="special_instructions" class="form-control" rows="2" maxlength="500" placeholder="Allergies, spice level, packing…">{{ old('special_instructions', $instructions) }}</textarea>
                        <button class="btn btn-pk w-100 mt-3" @disabled($unavailable)>Continue to checkout <i class="bi bi-arrow-right ms-1"></i></button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
