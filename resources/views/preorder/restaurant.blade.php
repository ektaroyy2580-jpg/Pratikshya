@extends('layouts.app')
@section('title', $restaurant->name.' — Preorder — प्रतीक्षा')

@push('head')
    @include('preorder.partials.head')
@endpush

@section('content')
@use('App\Services\Preorder\Money')
@php
    $inCart = ($cart['restaurant_id'] ?? null) == $restaurant->id ? ($cart['items'] ?? []) : [];
    $sections = $categories->filter(fn ($c) => $c->items->isNotEmpty())->values();
    if ($uncategorised->isNotEmpty()) { $sections->push((object) ['id' => 'more', 'name' => 'More', 'items' => $uncategorised]); }
    $tagClass = fn ($t) => in_array($t, ['vegetarian', 'vegan']) ? 'veg' : (str_contains($t, 'spicy') ? 'hot' : '');
@endphp

<header class="pk-page-head">
    <div class="container">
        <nav class="d-flex flex-wrap align-items-center gap-2 mb-3 fade-up" aria-label="Breadcrumb">
            <a href="{{ route('preorder.index') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>All cafés</a>
        </nav>
        <div class="row g-4 align-items-center">
            <div class="col-lg-7 fade-up" style="animation-delay:.04s">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="pk-eyebrow"><i class="bi bi-shop"></i>{{ $restaurant->cuisine }}</span>
                    @if($restaurant->is_demo)<span class="po-demo">Demo listing</span>@endif
                </div>
                <h1 class="mb-2" style="font-size:clamp(1.9rem,4.4vw,2.8rem);line-height:1.06">{{ $restaurant->name }}</h1>
                <p class="mb-3" style="color:var(--pk-ink-2);max-width:560px">{{ $restaurant->tagline }}</p>
                <div class="po-meta mb-3" style="font-size:.9rem">
                    <span><i class="bi bi-geo-alt"></i>Near {{ $restaurant->location->name }}</span>
                    @if($hint)<span><i class="bi bi-person-walking"></i>{{ $hint['walk_minutes'] }} min walk</span>@endif
                    @if($restaurant->opening_note)<span><i class="bi bi-clock"></i>{{ $restaurant->opening_note }}</span>@endif
                </div>
                @if($hint)
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @include('preorder.partials.fit-pill', ['hint' => $hint])
                        <span class="small pk-muted">{{ $quick ? 'Quickest: '.$quick->name.' (~'.$hint['ready_in_minutes'].' min)' : '' }}</span>
                    </div>
                @endif
            </div>
            <div class="col-lg-5 fade-up" style="animation-delay:.08s">
                <div class="po-cover">@include('preorder.partials.food-art', ['art' => $restaurant->art, 'label' => $restaurant->name, 'photo' => $restaurant->image_path])</div>
            </div>
        </div>
    </div>
</header>

<div class="container py-4">
    @include('preorder.partials.errors')
    @if($cartRestaurant && $cartRestaurant->id !== $restaurant->id && ($cart['count'] ?? 0) > 0)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="status">
            <i class="bi bi-basket"></i><span class="flex-grow-1">Your cart has {{ $cart['count'] }} item(s) from <strong>{{ $cartRestaurant->name }}</strong>. Adding from here will ask to start a new cart.</span>
            <a class="btn btn-ghost btn-sm" href="{{ \App\Services\Cafes\CafeHub::restaurantUrl($cartRestaurant) }}">Back to {{ $cartRestaurant->name }}</a>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            @if($sections->isEmpty())
                <div class="pk-card p-5 text-center">
                    <span class="pk-icon amber mb-3"><i class="bi bi-journal-x"></i></span>
                    <div class="fw-bold">The menu is being updated</div>
                    <p class="small pk-muted mb-0">No items are listed right now.</p>
                </div>
            @else
                <div class="po-sticky">
                    <nav class="po-catnav" aria-label="Menu sections">
                        @foreach($sections as $s)<a href="#cat-{{ $s->id }}">{{ $s->name }}</a>@endforeach
                    </nav>
                </div>
                @foreach($sections as $s)
                    <section id="cat-{{ $s->id }}" class="mb-4" style="scroll-margin-top:130px" aria-labelledby="cat-h-{{ $s->id }}">
                        <h2 id="cat-h-{{ $s->id }}" class="h5 mb-3">{{ $s->name }}</h2>
                        <div class="row g-3">
                            @foreach($s->items as $j => $item)
                                @php $qty = (int) ($inCart[$item->id] ?? 0); @endphp
                                <div class="col-md-6">
                                    <article class="pk-card po-item h-100 reveal {{ $qty ? 'in-cart' : '' }} {{ $item->available ? '' : 'off' }}" style="--d:{{ $j * .04 }}s" data-item-card="{{ $item->id }}">
                                        <div class="min-w-0 d-flex flex-column h-100">
                                            <h4>{{ $item->name }}</h4>
                                            <p>{{ $item->description }}</p>
                                            <div class="mb-2">
                                                @foreach($item->dietary_tags ?? [] as $tag)<span class="po-tag {{ $tagClass($tag) }}">{{ str_replace('-', ' ', $tag) }}</span>@endforeach
                                                <span class="po-tag time"><i class="bi bi-stopwatch me-1"></i>{{ $item->prep_minutes }} min</span>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between gap-2 mt-auto">
                                                <span class="price">{{ Money::format($item->price) }}</span>
                                                @include('preorder.partials.item-control', ['item' => $item, 'qty' => $qty])
                                            </div>
                                        </div>
                                        @include('preorder.partials.food-art', ['art' => $item->art, 'label' => $item->name, 'photo' => $item->image_path])
                                    </article>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endif
        </div>

        <aside class="col-lg-4">
            <div style="position:sticky;top:88px" class="d-flex flex-column gap-3">
                <div class="pk-card p-4 reveal">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="pk-icon green" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-bag-check"></i></span><div class="fw-bold">Pickup</div></div>
                    <p class="small mb-2" style="color:var(--pk-ink-2)">{{ $restaurant->pickup_instructions ?: 'Collect at the counter and show your pickup code.' }}</p>
                    @if($restaurant->merchant_contact)<div class="small pk-muted"><i class="bi bi-telephone me-1"></i>{{ $restaurant->merchant_contact }}</div>@endif
                </div>
                <div class="pk-card p-4 reveal" style="--d:.05s">
                    <div class="fw-bold mb-2"><i class="bi bi-shield-check me-1 text-success"></i>How preorders work</div>
                    <ul class="small mb-0 ps-3" style="color:var(--pk-ink-2)">
                        <li>Free cancellation until the café accepts.</li>
                        <li>We check the order fits your queue wait before you confirm.</li>
                        <li>Your queue place never changes because of food.</li>
                        <li>Prices include 10% service + 13% VAT at checkout.</li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</div>

<div class="po-cartbar {{ ($cart['restaurant_id'] ?? null) == $restaurant->id && ($cart['count'] ?? 0) > 0 ? 'show' : '' }}" id="poCartBar" role="region" aria-label="Cart">
    <span class="count js-cart-count">{{ $cart['count'] ?? 0 }}</span>
    <div class="flex-grow-1 min-w-0"><div class="small opacity-75">Subtotal</div><div class="fw-bold js-cart-subtotal">{{ $cart['subtotal_label'] ?? 'Rs 0' }}</div></div>
    <a href="{{ route('preorder.cart') }}" class="btn btn-pk btn-sm">View cart <i class="bi bi-arrow-right ms-1"></i></a>
</div>
<div class="visually-hidden" aria-live="polite" id="poLive"></div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/preorder.js') }}?v={{ @filemtime(public_path('js/modules/preorder.js')) }}"></script>
<script>PreorderUI.menu({ restaurantId: {{ $restaurant->id }}, max: {{ (int) config('preorder.max_qty_per_item', 20) }} });</script>
@endpush
