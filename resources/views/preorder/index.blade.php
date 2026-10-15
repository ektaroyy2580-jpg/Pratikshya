@extends('layouts.app')
@section('title', 'Preorder food — प्रतीक्षा')

@push('head')
    @include('preorder.partials.head')
@endpush

@section('content')
<header class="pk-page-head po-hero">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 fade-up">
            <span class="pk-eyebrow"><i class="bi bi-cup-hot"></i>Cafe preorder</span>
            <span class="po-demo"><i class="bi bi-info-circle"></i>Demo cafés · demo payments</span>
        </div>
        <div class="row align-items-end g-3">
            <div class="col-lg-8 fade-up" style="animation-delay:.05s">
                <h1 class="mb-2">Hungry while you wait? <span class="text-gradient">Order ahead.</span></h1>
                <p class="pk-muted mb-0" style="max-width:620px">Order from participating cafés near your queue. We check whether your food can be ready and collected before your turn — and your place in the queue never changes because of an order.</p>
            </div>
            <div class="col-lg-4 d-flex gap-2 justify-content-lg-end fade-up" style="animation-delay:.1s">
                <a href="{{ route('preorder.orders') }}" class="btn btn-ghost"><i class="bi bi-receipt me-1"></i>My orders</a>
                @if($managesAny)
                    <a href="{{ route('preorder.merchant') }}" class="btn btn-ghost"><i class="bi bi-shop me-1"></i>Café dashboard</a>
                @endif
            </div>
        </div>
    </div>
</header>

<div class="container py-4">
    @include('preorder.partials.errors')

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="pk-card p-3 p-md-4 h-100 reveal">
                @if($ticket)
                    @php $tState = $ticket->stateArray(); @endphp
                    <div class="d-flex align-items-center gap-3">
                        <span class="pk-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <div class="min-w-0">
                            <div class="small pk-muted fw-semibold">Planning around your token</div>
                            <div class="fw-bold">{{ $ticket->token() }} · {{ $ticket->service->name }}</div>
                            <div class="small pk-muted">{{ $ticket->service->location->name }} · {{ $ticket->status === 'waiting' ? $tState['ahead'].' ahead · earliest turn ~'.$tState['wait_low'].' min' : 'You are being called — head to your counter' }}</div>
                        </div>
                        <a href="{{ route('ticket', $ticket) }}" class="btn btn-ghost btn-sm ms-auto">View queue</a>
                    </div>
                @else
                    <div class="d-flex align-items-center gap-3">
                        <span class="pk-icon violet"><i class="bi bi-info-circle"></i></span>
                        <div>
                            <div class="fw-bold">You're not in a queue right now</div>
                            <div class="small pk-muted">You can still order — join a queue first and we'll check your food fits your wait.</div>
                        </div>
                        <a href="{{ route('find') }}" class="btn btn-ghost btn-sm ms-auto text-nowrap">Find a service</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="pk-card p-3 p-md-4 h-100 reveal" style="--d:.06s">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-bold"><span class="live-dot me-2"></span>Active orders</div>
                    <a href="{{ route('preorder.orders') }}" class="small fw-semibold text-decoration-none">History</a>
                </div>
                @forelse($activeOrders as $o)
                    <a href="{{ route('preorder.order', $o) }}" class="d-flex justify-content-between align-items-center py-2 text-decoration-none" style="color:inherit">
                        <span class="min-w-0 text-truncate"><span class="fw-bold">{{ $o->reference() }}</span> · {{ $o->restaurant->name }}</span>
                        <span class="pk-status {{ $o->status === 'ready' ? 'called' : ($o->status === 'placed' ? 'waiting' : 'serving') }}">{{ ucfirst($o->status) }}</span>
                    </a>
                @empty
                    <p class="small pk-muted mb-0">No active orders. Pick a café below — most snacks are ready in under 15 minutes.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="d-flex align-items-end justify-content-between mb-3">
        <div>
            <h2 class="h4 mb-1">Participating cafés</h2>
            <p class="small pk-muted mb-0">{{ $restaurants->count() }} taking preorders · fictional demo listings</p>
        </div>
    </div>

    @if($restaurants->isEmpty())
        <div class="pk-card p-5 text-center">
            <span class="pk-icon amber mb-3"><i class="bi bi-shop"></i></span>
            <div class="fw-bold">No cafés are taking preorders right now</div>
            <p class="small pk-muted mb-0">Check back soon — cafés can pause preorders when the kitchen is busy.</p>
        </div>
    @else
        <div class="row g-3 g-lg-4">
            @foreach($restaurants as $i => $r)
                @php $hint = $hints[$r->id] ?? null; @endphp
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <a href="{{ \App\Services\Cafes\CafeHub::restaurantUrl($r, null) }}" class="pk-card lift po-card reveal" style="--d:{{ $i * .05 }}s">
                        @include('preorder.partials.food-art', ['art' => $r->art, 'label' => $r->name, 'photo' => $r->image_path])
                        <div class="body">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                @if($r->location_id === $hereId)<span class="chip"><i class="bi bi-geo-alt-fill"></i>Near your queue</span>@endif
                                @if($hint)@include('preorder.partials.fit-pill', ['hint' => $hint])@endif
                            </div>
                            <h3>{{ $r->name }}</h3>
                            <div class="small pk-muted">{{ $r->cuisine }}</div>
                            <div class="po-meta mt-auto">
                                <span><i class="bi bi-geo-alt"></i>{{ $r->location->name }}</span>
                                <span><i class="bi bi-person-walking"></i>{{ $hint['walk_minutes'] ?? $r->walk_minutes }} min walk</span>
                                <span><i class="bi bi-basket"></i>{{ $r->available_count }} items</span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <p class="small pk-muted mt-4 mb-0"><i class="bi bi-info-circle me-1"></i>Only businesses that have joined the preorder programme are listed. All cafés, menus and prices here are fictional demo data.</p>
</div>

@if(($cartSummary['count'] ?? 0) > 0 && $cartRestaurant)
    <div class="po-cartbar show" role="region" aria-label="Cart">
        <span class="count">{{ $cartSummary['count'] }}</span>
        <div class="flex-grow-1 min-w-0"><div class="small opacity-75 text-truncate">{{ $cartRestaurant->name }}</div><div class="fw-bold">{{ $cartSummary['subtotal_label'] }}</div></div>
        <a href="{{ route('preorder.cart') }}" class="btn btn-pk btn-sm">View cart <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
@endif
@endsection
