@extends('layouts.app')
@section('title', $place->name.' — Café — प्रतीक्षा')
@use('App\Services\Preorder\Money')

@php
    $rec = true;
    $routed = $estimate['method'] === 'osrm' && $estimate['geometry'];
    $mapData = [
        'from' => [(float) $location->lat, (float) $location->lng],
        'to' => [$item['lat'], $item['lng']],
        'color' => $item['color'], 'icon' => $item['icon'],
        'geometry' => $routed ? $estimate['geometry'] : null,
        'label' => $routed ? $estimate['walk_minutes'].' min · walking route (approximate)' : $estimate['walk_minutes'].' min · direct line, not a route',
    ];
    $art = $restaurant?->art ?? ['momo' => 'momo', 'chiya' => 'chiya', 'thakali' => 'thali', 'coffee' => 'coffee', 'juice' => 'lassi', 'lassi' => 'lassi', 'canteen' => 'samosa'][$theme] ?? 'chiya';
    $inCart = $cartHere ? ($cart['items'] ?? []) : [];
    $tagClass = fn ($t) => in_array($t, ['vegetarian', 'vegan']) ? 'veg' : (str_contains($t, 'spicy') ? 'hot' : '');
    $defaultTab = $openOrders->isNotEmpty() ? 'order' : 'menu';
    $opening = $restaurant?->opening_note ?: $item['hours_text'];
    $billStatus = ['open' => 'Open', 'settling' => 'Settling up', 'settled' => 'Settled', 'archived' => 'Archived'];
    $steps = [['placed', 'Placed', 'bi-send'], ['accepted', 'Accepted', 'bi-hand-thumbs-up'], ['preparing', 'Preparing', 'bi-fire'], ['ready', 'Ready', 'bi-bell'], ['collected', 'Collected', 'bi-bag-check']];
@endphp

@push('head')
    @include('preorder.partials.head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <link rel="stylesheet" href="{{ asset('css/modules/explore.css') }}?v={{ @filemtime(public_path('css/modules/explore.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/modules/cafes.css') }}?v={{ @filemtime(public_path('css/modules/cafes.css')) }}">
    <link rel="canonical" href="{{ route('cafes.show', $place) }}">
    <script>document.documentElement.classList.add('cf-js');</script>
    <noscript><style>.cf-photo img.cf-img{opacity:1;transform:none}</style></noscript>
@endpush

@section('content')
{{-- ================= HEADER ================= --}}
<header class="pk-page-head cf-head">
    <div class="container">
        <a class="pk-breadcrumb fade-up d-inline-block mb-3" href="{{ route('explore.index', ['location' => $location->id]) }}"><i class="bi bi-arrow-left me-1"></i>Explore near {{ $location->name }}</a>
        <div class="row g-4 align-items-start">
            <div class="col-lg-7 fade-up">
                @if($gallery)
                    <div class="cf-gallery" data-gallery aria-roledescription="carousel" aria-label="Photos of {{ $place->name }}">
                        <div class="cf-track" tabindex="0">
                            @foreach($gallery as $i => $g)
                                <figure class="cf-slide" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ count($gallery) }}">
                                    @include('cafes.partials.photo', ['url' => $g['url'], 'alt' => $g['alt'], 'art' => $art, 'eager' => $i === 0])
                                    @if($i === 0)<span class="cf-rep">Representative photo</span>@endif
                                    @if($g['credit'])<figcaption class="cf-credit">{{ $g['credit'] }}</figcaption>@endif
                                </figure>
                            @endforeach
                        </div>
                        @if(count($gallery) > 1)
                            <button type="button" class="cf-nav prev" data-dir="-1" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
                            <button type="button" class="cf-nav next" data-dir="1" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
                            <div class="cf-dots" role="tablist" aria-label="Choose photo">
                                @foreach($gallery as $i => $g)<button type="button" role="tab" aria-label="Photo {{ $i + 1 }}" class="{{ $i === 0 ? 'on' : '' }}"></button>@endforeach
                            </div>
                        @endif
                    </div>
                    @if(count($gallery) > 1)
                        <div class="cf-thumbs" aria-hidden="true">
                            @foreach(array_slice($gallery, 0, 4) as $i => $g)
                                <button type="button" tabindex="-1" data-thumb="{{ $i }}" class="{{ $i === 0 ? 'on' : '' }}">@include('cafes.partials.photo', ['url' => $g['url'], 'alt' => '', 'art' => $art])</button>
                            @endforeach
                        </div>
                    @endif
                    <div class="small pk-muted mt-2"><i class="bi bi-image me-1"></i>Representative photos from Wikimedia Commons — not of this (fictional) café. <a href="{{ route('explore.credits') }}">Photo credits</a></div>
                @else
                    <div class="cf-gallery"><div class="cf-slide">@include('cafes.partials.photo', ['url' => null, 'art' => $art])</div></div>
                @endif
            </div>

            <div class="col-lg-5 fade-up" style="animation-delay:.06s">
                <div class="cf-badges mb-2">
                    <span class="chip"><i class="bi {{ $item['icon'] }}"></i>{{ $item['category_label'] === 'Cafes' ? 'Café' : 'Restaurant' }}@if($item['subcategory']) · {{ $item['subcategory'] }}@endif</span>
                    @if($participating)
                        <span class="cf-pill ok"><i class="bi bi-bag-check-fill"></i>Pre-order available</span>
                    @else
                        <span class="cf-pill off"><i class="bi bi-bag-x"></i>Walk-in only</span>
                    @endif
                    @if($item['sponsored'])<span class="ex-badge">Sponsored</span>@endif
                    @if(! $restaurant || $restaurant->is_demo)<span class="po-demo">Demo listing</span>@endif
                </div>
                <h1 class="mb-2">{{ $place->name }}</h1>
                @if($restaurant?->tagline)<p class="mb-2" style="color:var(--pk-ink-2)">{{ $restaurant->tagline }}</p>@elseif($place->tip)<p class="mb-2" style="color:var(--pk-ink-2)">{{ $place->tip }}</p>@endif
                <div class="cf-facts mb-3">
                    <span><i class="bi bi-star-fill" style="color:#f5b301"></i>{{ number_format($item['rating'], 1) }} <span class="fw-normal">(demo)</span></span>
                    <span><i class="bi bi-person-walking"></i>{{ $item['walk_there'] }} min walk · {{ number_format($item['distance_m']) }} m</span>
                    <span><i class="bi bi-geo-alt"></i>Near {{ $location->name }}</span>
                </div>
                <div class="cf-facts mb-3">
                    <span><i class="bi bi-clock"></i>{{ $opening }}</span>
                    @if($item['hours_verified'])<span class="cf-pill ok py-0">Verified hours</span>@else<span class="cf-pill warn py-0" title="Opening hours have not been confirmed with the business">Hours unverified</span>@endif
                </div>

                @if($remaining['minutes'] !== null)
                    <div class="cf-fit {{ $item['fits'] ? 'yes' : 'no' }} mb-3" role="status">
                        <div class="t"><i class="bi {{ $item['fits'] ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' }} me-1"></i>
                            @if($ticket)
                                {{ $item['fits'] ? 'Fits your wait' : 'Tight for your wait' }} · token {{ $ticket->token() }}
                            @else
                                {{ $item['fits'] ? 'Fits the time you picked' : 'Longer than the time you picked' }}
                            @endif
                        </div>
                        <div class="mt-1" style="color:var(--pk-ink-2)">@if($ticket)You have approximately {{ $remaining['minutes'] }} minutes. @endif{{ $item['explanation'] }}</div>
                    </div>
                @else
                    <div class="cf-fit mb-3">
                        <div style="color:var(--pk-ink-2)">{{ $item['explanation'] }}
                            @if($otherTicket) You're queued at {{ $otherTicket->service->location->name }}, not here. @else Join a queue at {{ $location->name }} to see whether it fits your wait. @endif
                        </div>
                    </div>
                @endif

                <div class="cf-cta">
                    @if($participating)
                        <a href="#menu" class="btn btn-pk" data-tab-link="menu"><i class="bi bi-bag-plus me-1"></i>Pre-order</a>
                    @endif
                    <a href="#bill" class="btn btn-ghost" data-tab-link="bill"><i class="bi bi-receipt me-1"></i>Split a bill here</a>
                    @if($rideUrl)<a href="{{ $rideUrl }}" class="btn btn-ghost"><i class="bi bi-car-front me-1"></i>Ride there</a>@endif
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container pb-5">
    @include('preorder.partials.errors')
    @if(session('ok'))<div class="alert alert-success mt-3" role="status"><i class="bi bi-check-circle-fill me-1"></i>{{ session('ok') }}</div>@endif

    {{-- ================= TABS ================= --}}
    <div class="cf-tabs-wrap">
        <nav class="cf-tabs" role="tablist" aria-label="Café sections" data-default="{{ $defaultTab }}">
            <a href="#menu" role="tab" id="tab-menu" aria-controls="menu" data-tab="menu"><i class="bi bi-journal-text"></i><span class="lbl-long">Menu &amp; Pre-order</span><span class="lbl-short">Menu</span></a>
            <a href="#bill" role="tab" id="tab-bill" aria-controls="bill" data-tab="bill"><i class="bi bi-receipt"></i><span class="lbl-long">Split a bill here</span><span class="lbl-short">Split bill</span>@if($bills->whereIn('status', ['open', 'settling'])->count())<span class="n">{{ $bills->whereIn('status', ['open', 'settling'])->count() }}</span>@endif</a>
            <a href="#order" role="tab" id="tab-order" aria-controls="order" data-tab="order"><i class="bi bi-bag-check"></i><span class="lbl-long">Your order here</span><span class="lbl-short">Order</span>@if($openOrders->count())<span class="n">{{ $openOrders->count() }}</span>@endif</a>
            <a href="#visit" role="tab" id="tab-visit" aria-controls="visit" data-tab="visit"><i class="bi bi-signpost-split"></i><span class="lbl-long">Getting there</span><span class="lbl-short">Map</span></a>
        </nav>
    </div>

    {{-- ================= MENU & PRE-ORDER ================= --}}
    <section id="menu" class="cf-panel mt-3" role="tabpanel" aria-labelledby="tab-menu">
        @if($participating)
            @if($cartRestaurant && $cartRestaurant->id !== $restaurant->id && ($cart['count'] ?? 0) > 0)
                <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="status">
                    <i class="bi bi-basket"></i><span class="flex-grow-1">Your cart has {{ $cart['count'] }} item(s) from <strong>{{ $cartRestaurant->name }}</strong>. Adding from here will ask to start a new cart.</span>
                    <a class="btn btn-ghost btn-sm" href="{{ \App\Services\Cafes\CafeHub::restaurantUrl($cartRestaurant) }}">Back to {{ $cartRestaurant->name }}</a>
                </div>
            @endif
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="cf-panel-title">Menu &amp; pre-order</h2>
                            <div class="small pk-muted">Order now, collect when it's ready — your queue place never changes.</div>
                        </div>
                        @if($hint)
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @include('preorder.partials.fit-pill', ['hint' => $hint])
                                @if($quick)<span class="small pk-muted">Quickest: {{ $quick->name }} (~{{ $hint['ready_in_minutes'] }} min)</span>@endif
                            </div>
                        @endif
                    </div>
                    @if($sections->isEmpty())
                        <div class="pk-card cf-empty">
                            <span class="pk-icon amber"><i class="bi bi-journal-x"></i></span>
                            <div class="fw-bold">The menu is being updated</div>
                            <p class="small pk-muted mb-0">No items are listed right now.</p>
                        </div>
                    @else
                        @if($sections->count() > 1)
                            <nav class="po-catnav mb-2" aria-label="Menu sections">
                                @foreach($sections as $s)<a href="#cat-{{ $s->id }}">{{ $s->name }}</a>@endforeach
                            </nav>
                        @endif
                        @foreach($sections as $s)
                            <section id="cat-{{ $s->id }}" class="mb-4" style="scroll-margin-top:150px" aria-labelledby="cat-h-{{ $s->id }}">
                                <h3 id="cat-h-{{ $s->id }}" class="h6 text-uppercase pk-muted mb-3" style="letter-spacing:.06em">{{ $s->name }}</h3>
                                <div class="row g-3">
                                    @foreach($s->items as $j => $menuItem)
                                        @php $qty = (int) ($inCart[$menuItem->id] ?? 0); @endphp
                                        <div class="col-md-6">
                                            <article class="pk-card po-item h-100 reveal {{ $qty ? 'in-cart' : '' }} {{ $menuItem->available ? '' : 'off' }}" style="--d:{{ $j * .04 }}s" data-item-card="{{ $menuItem->id }}">
                                                <div class="min-w-0 d-flex flex-column h-100">
                                                    <h4>{{ $menuItem->name }}</h4>
                                                    <p>{{ $menuItem->description }}</p>
                                                    <div class="mb-2">
                                                        @foreach($menuItem->dietary_tags ?? [] as $tag)<span class="po-tag {{ $tagClass($tag) }}">{{ str_replace('-', ' ', $tag) }}</span>@endforeach
                                                        <span class="po-tag time"><i class="bi bi-stopwatch me-1"></i>{{ $menuItem->prep_minutes }} min</span>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between gap-2 mt-auto">
                                                        <span class="price">{{ Money::format($menuItem->price) }}</span>
                                                        @include('preorder.partials.item-control', ['item' => $menuItem, 'qty' => $qty])
                                                    </div>
                                                </div>
                                                <div>
                                                    @include('preorder.partials.food-art', ['art' => $menuItem->art, 'label' => $menuItem->name, 'photo' => $menuItem->image_path])
                                                    @if($menuItem->image_credit && \App\Services\Cafes\CafeImages::url($menuItem->image_path))<div class="pk-muted mt-1 text-truncate" style="font-size:.62rem" title="{{ $menuItem->image_credit }}">{{ \Illuminate\Support\Str::after($menuItem->image_credit, 'Photo: ') }}</div>@endif
                                                </div>
                                            </article>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    @endif
                </div>

                <aside class="col-lg-4">
                    <div style="position:sticky;top:150px" class="d-flex flex-column gap-3">
                        <div class="pk-card p-4 cf-cart" id="cfCart" data-restaurant="{{ $restaurant->id }}">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="pk-icon violet" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-basket"></i></span>
                                <div class="fw-bold flex-grow-1">Your pre-order</div>
                                <span class="chip js-cf-count">{{ $cartHere ? $cart['count'] : 0 }} item{{ ($cartHere ? $cart['count'] : 0) === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="js-cf-empty small pk-muted" @if($cartHere) hidden @endif>Add dishes from the menu — we'll check they're ready before your turn.</div>
                            <div class="js-cf-filled" @if(! $cartHere) hidden @endif>
                                <div class="js-cf-lines">
                                    @foreach($cartLines as $l)
                                        <div class="line"><span class="text-truncate">{{ $l['qty'] }} × {{ $l['item']->name }}</span><span class="fw-semibold">{{ Money::format($l['line_paisa'], true) }}</span></div>
                                    @endforeach
                                </div>
                                <div class="tot"><span>Subtotal</span><span class="js-cart-subtotal-side">{{ $cart['subtotal_label'] ?? 'Rs 0' }}</span></div>
                                <div class="small pk-muted mt-1">+10% service &amp; 13% VAT · total <span class="js-cf-total">{{ $cart['total_label'] ?? '' }}</span></div>
                                @if($cartTiming)
                                    <div class="js-cf-timing">
                                        <div class="mt-2">@include('preorder.partials.fit-pill', ['hint' => $cartTiming])</div>
                                        <div class="small mt-1" style="color:var(--pk-ink-2)">{{ $cartTiming['explanation'] }}</div>
                                    </div>
                                @endif
                                <div class="small pk-muted mt-2 js-cf-stale" hidden><i class="bi bi-arrow-repeat me-1"></i>Timing is re-checked at checkout.</div>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="{{ route('preorder.checkout') }}" class="btn btn-pk"><i class="bi bi-bag-check me-1"></i>Review &amp; pre-order</a>
                                    <a href="{{ route('preorder.cart') }}" class="btn btn-ghost btn-sm">Edit cart &amp; notes</a>
                                </div>
                            </div>
                        </div>
                        <div class="pk-card p-4">
                            <div class="fw-bold mb-2"><i class="bi bi-geo me-1 text-success"></i>Pickup</div>
                            <p class="small mb-2" style="color:var(--pk-ink-2)">{{ $restaurant->pickup_instructions ?: 'Collect at the counter and show your pickup code.' }}</p>
                            <ul class="small mb-0 ps-3" style="color:var(--pk-ink-2)">
                                <li>Free cancellation until the café accepts.</li>
                                <li>Your queue place never changes because of food.</li>
                                <li>Payments here are demo only — no money is charged.</li>
                            </ul>
                            @if($restaurant->merchant_contact)<div class="small pk-muted mt-2"><i class="bi bi-telephone me-1"></i>{{ $restaurant->merchant_contact }}</div>@endif
                        </div>
                    </div>
                </aside>
            </div>
        @else
            <div class="pk-card cf-na p-4 p-md-5 text-center mx-auto" style="max-width:640px">
                <span class="pk-icon amber mb-3"><i class="bi bi-bag-x"></i></span>
                <h2 class="h5 mb-2">Pre-order not available here</h2>
                <p class="pk-muted mb-3">{{ $place->name }} hasn't joined pre-ordering, so you'll order at the counter. It's {{ $item['walk_there'] }} min on foot — you can still split the bill with friends here.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="#visit" class="btn btn-pk" data-tab-link="visit"><i class="bi bi-signpost-split me-1"></i>Directions</a>
                    @if($rideUrl)<a href="{{ $rideUrl }}" class="btn btn-ghost"><i class="bi bi-car-front me-1"></i>Ride there</a>@endif
                    <a href="#bill" class="btn btn-ghost" data-tab-link="bill"><i class="bi bi-receipt me-1"></i>Split a bill here</a>
                </div>
            </div>
        @endif
    </section>

    {{-- ================= SPLIT A BILL HERE ================= --}}
    <section id="bill" class="cf-panel mt-3" role="tabpanel" aria-labelledby="tab-bill">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="pk-card p-4 h-100">
                    <h2 class="cf-panel-title mb-1">Split a bill at {{ $place->name }}</h2>
                    <p class="small pk-muted mb-3">Eating together? Start a group bill here — friends join with a link or QR and everyone sees their exact share.</p>
                    <ol class="cf-steps mb-4">
                        <li><div><b>We fill in the café</b> — the bill is named after {{ $place->name }}.</div></li>
                        <li><div>@if($billOrder)<b>Your order is added</b> — the items from {{ $billOrder->reference() }} go on the bill.@else<b>Add what you ordered</b> — or pre-order first and we'll copy your items.@endif</div></li>
                        <li><div><b>Invite friends</b> — share the link or QR; mark who had what or split equally.</div></li>
                    </ol>
                    @if($billOrder)
                        <div class="rounded-4 p-3 mb-3" style="background:var(--pk-bg)">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold small">From your order {{ $billOrder->reference() }}</div>
                                <span class="small pk-muted">{{ $billOrder->created_at->format('M j, g:i A') }}</span>
                            </div>
                            <ul class="cf-items">
                                @foreach($billOrder->items as $oi)
                                    <li><span>{{ $oi->qty }} × {{ $oi->name }}</span><span class="fw-semibold">{{ Money::format($oi->line_total) }}</span></li>
                                @endforeach
                            </ul>
                            <div class="d-flex justify-content-between small mt-2"><span class="pk-muted">Order total (incl. service &amp; VAT)</span><b>{{ Money::format($billOrder->total) }}</b></div>
                        </div>
                    @endif
                    @if($billCreateUrl)
                        <a href="{{ $billCreateUrl }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i>{{ $billOrder ? 'Split this order' : 'Start a bill here' }}</a>
                        @if($billOrder)<a href="{{ route('bills.create', ['place' => $place->id]) }}" class="btn btn-ghost ms-1">Start an empty bill</a>@endif
                    @endif
                    <p class="small pk-muted mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Payments between friends happen outside the app (cash, eSewa, Khalti) and are confirmed by the bill owner. Bills never affect your queue.</p>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="pk-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">Your bills here</h3>
                        @if(Route::has('bills.index'))<a href="{{ route('bills.index') }}" class="small fw-semibold">All bills</a>@endif
                    </div>
                    @forelse($bills as $b)
                        <a href="{{ route('bills.show', $b) }}" class="cf-row">
                            <span class="ic"><i class="bi bi-receipt"></i></span>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate">{{ $b->title }}</div>
                                <div class="small pk-muted">{{ $b->joinedParticipants->count() }} {{ \Illuminate\Support\Str::plural('person', $b->joinedParticipants->count()) }} · {{ $b->owner?->id === auth()->id() ? 'you own it' : 'by '.$b->owner?->name }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold small">{{ Money::format($b->total) }}</div>
                                <span class="cf-st {{ $b->status }}">{{ $billStatus[$b->status] ?? $b->status }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="cf-empty">
                            <span class="pk-icon"><i class="bi bi-people"></i></span>
                            <div class="fw-bold">No bills here yet</div>
                            <div class="small pk-muted">Bills you start or join at this café show up here.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- ================= YOUR ORDER HERE ================= --}}
    <section id="order" class="cf-panel mt-3" role="tabpanel" aria-labelledby="tab-order">
        @forelse($openOrders as $o)
            @php $s = $o->stateArray(); @endphp
            <article class="pk-card cf-order p-4 mb-3" data-order-poll="{{ route('preorder.order.state', $o) }}" data-status="{{ $o->status }}">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1"><span class="chip"><span class="live-dot"></span>Live</span><span class="small pk-muted fw-semibold">{{ $o->reference() }} · {{ $o->items->sum('qty') }} items · {{ Money::format($o->total) }}</span></div>
                        <div class="h5 mb-0 js-o-label">{{ $s['status_label'] }}</div>
                    </div>
                    <div class="text-end">
                        <div class="small pk-muted fw-semibold">Ready</div>
                        <div class="h5 mb-0 js-o-eta">{{ $o->status === 'ready' ? 'Ready now' : ($s['minutes_to_ready'] !== null ? '~'.$s['minutes_to_ready'].' min' : ($s['estimated_ready_label'] ?? '—')) }}</div>
                    </div>
                </div>
                <div class="po-track" role="list" aria-label="Order progress">
                    @foreach($steps as $i => [$key, $label, $icon])
                        <div class="st {{ $i < $s['step'] ? 'done' : ($i === $s['step'] ? 'now' : '') }}" role="listitem"><div class="dot"><i class="bi {{ $icon }}"></i></div>{{ $label }}</div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-2">
                    <div class="js-o-code" @if(! $s['pickup_code']) hidden @endif><span class="small pk-muted fw-semibold me-2">Pickup code</span><span class="cf-code js-o-code-v">{{ $s['pickup_code'] }}</span></div>
                    <div class="d-flex flex-wrap gap-2 ms-auto">
                        <a href="{{ route('preorder.order', $o) }}" class="btn btn-ghost btn-sm">Order details</a>
                        @if($billCreateUrl)<a href="{{ route('bills.create', ['place' => $place->id, 'order' => $o->id]) }}" class="btn btn-ghost btn-sm"><i class="bi bi-receipt me-1"></i>Split this order</a>@endif
                    </div>
                </div>
            </article>
        @empty
            <div class="pk-card cf-empty">
                <span class="pk-icon violet"><i class="bi bi-bag"></i></span>
                <div class="fw-bold">No active order here</div>
                <p class="small pk-muted mb-3">{{ $participating ? 'Pre-order from the menu and track it here while you wait.' : 'This café takes walk-in orders only.' }}</p>
                @if($participating)<a href="#menu" class="btn btn-pk btn-sm" data-tab-link="menu">Browse the menu</a>@endif
            </div>
        @endforelse

        @if($pastOrders->isNotEmpty())
            <h3 class="h6 mt-4 mb-2">Earlier orders here</h3>
            @foreach($pastOrders as $o)
                <a href="{{ route('preorder.order', $o) }}" class="cf-row">
                    <span class="ic">@include('preorder.partials.food-art', ['art' => $o->items->first()?->art ?? $art, 'label' => '', 'photo' => \App\Services\Cafes\CafeImages::dishPath($o->items->first()?->name ?? '', $o->items->first()?->art)])</span>
                    <div class="min-w-0">
                        <div class="fw-bold">{{ $o->reference() }} · {{ $o->statusLabel() }}</div>
                        <div class="small pk-muted text-truncate">{{ $o->items->map(fn ($i) => $i->qty.'× '.$i->name)->join(', ') }}</div>
                    </div>
                    <span class="fw-bold small">{{ Money::format($o->total) }}</span>
                </a>
            @endforeach
        @endif
    </section>

    {{-- ================= GETTING THERE ================= --}}
    <section id="visit" class="cf-panel mt-3" role="tabpanel" aria-labelledby="tab-visit">
        <div class="row g-4">
            <div class="col-lg-7">
                <div id="cfMap" role="img" aria-label="Map showing {{ $place->name }} and {{ $location->name }}"></div>
                <div class="small pk-muted mt-2"><i class="bi bi-info-circle me-1"></i>
                    @if($routed) Route from the configured routing service — approximate.
                    @else The dashed line is a <b>direct line, not a route</b>. Follow streets and signs on the ground.
                    @endif
                </div>
            </div>
            <div class="col-lg-5">
                <div class="pk-card p-4">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4"><div class="ex-stat"><div class="l">Walk there</div><div class="v">{{ $item['walk_there'] }}<small class="fs-6 pk-muted"> min</small></div></div></div>
                        <div class="col-4"><div class="ex-stat"><div class="l">Visit</div><div class="v">{{ $item['visit'] }}<small class="fs-6 pk-muted"> min</small></div></div></div>
                        <div class="col-4"><div class="ex-stat"><div class="l">Back</div><div class="v">{{ $item['walk_back'] }}<small class="fs-6 pk-muted"> min</small></div></div></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if($rideUrl)<a href="{{ $rideUrl }}" class="btn btn-pk btn-sm"><i class="bi bi-car-front me-1"></i>Ride there</a>@endif
                        <a href="{{ route('explore.index', ['location' => $location->id]) }}" class="btn btn-ghost btn-sm"><i class="bi bi-map me-1"></i>Back to Explore</a>
                        @if($dismissed)
                            <span class="small pk-muted align-self-center"><i class="bi bi-eye-slash me-1"></i>Hidden from suggestions</span>
                        @else
                            <form method="POST" action="{{ route('explore.dismiss', $place) }}" class="m-0">@csrf
                                <button class="btn btn-link btn-sm text-danger text-decoration-none"><i class="bi bi-eye-slash me-1"></i>Not interested</button>
                            </form>
                        @endif
                    </div>
                    <div class="ex-kv"><i class="bi bi-rulers"></i><div>≈ {{ number_format($estimate['distance_m']) }} m on foot from {{ $location->name }} <span class="pk-muted">({{ $estimate['label'] }}; straight line {{ number_format($estimate['straight_m']) }} m)</span></div></div>
                    <div class="ex-kv"><i class="bi bi-clock"></i><div>{{ $item['hours_text'] }} @if($item['hours_verified'])<span class="badge text-bg-success ms-1">Verified</span>@else<span class="pk-muted">— check before you go</span>@endif</div></div>
                    @if($place->tip)<div class="ex-kv"><i class="bi bi-lightbulb"></i><div>{{ $place->tip }}</div></div>@endif
                    @if($item['accessibility'])<div class="ex-kv"><i class="bi bi-universal-access"></i><div>{{ $item['accessibility'] }}</div></div>@endif
                    @if($place->address)<div class="ex-kv"><i class="bi bi-geo-alt"></i><div>{{ $place->address }}</div></div>@endif
                    @if($place->phone)<div class="ex-kv"><i class="bi bi-telephone"></i><div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $place->phone) }}">{{ $place->phone }}</a></div></div>@endif
                </div>
            </div>
        </div>
    </section>
</div>

@if($participating)
    <div class="po-cartbar {{ $cartHere ? 'show' : '' }}" id="poCartBar" role="region" aria-label="Cart">
        <span class="count js-cart-count">{{ $cartHere ? $cart['count'] : 0 }}</span>
        <div class="flex-grow-1 min-w-0"><div class="small opacity-75">Subtotal</div><div class="fw-bold js-cart-subtotal">{{ $cart['subtotal_label'] ?? 'Rs 0' }}</div></div>
        <a href="{{ route('preorder.checkout') }}" class="btn btn-pk btn-sm">Pre-order <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
@endif
<div class="visually-hidden" aria-live="polite" id="poLive"></div>
<script type="application/json" id="cfMapData">@json($mapData)</script>
@if($participating)
<script type="application/json" id="cfMenuData">@json($sections->flatMap(fn ($s) => $s->items)->mapWithKeys(fn ($i) => [$i->id => ['name' => $i->name, 'paisa' => $i->pricePaisa()]]))</script>
@endif
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/modules/preorder.js') }}?v={{ @filemtime(public_path('js/modules/preorder.js')) }}"></script>
<script src="{{ asset('js/modules/cafes.js') }}?v={{ @filemtime(public_path('js/modules/cafes.js')) }}"></script>
@if($participating)<script>PreorderUI.menu({ restaurantId: {{ $restaurant->id }}, max: {{ (int) config('preorder.max_qty_per_item', 20) }} });</script>@endif
@endpush
