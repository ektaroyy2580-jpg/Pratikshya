@extends('layouts.app')
@section('title', 'Café dashboard — '.$restaurant->name.' — प्रतीक्षा')

@push('head')
    @include('preorder.partials.head')
@endpush

@section('content')
@use('App\Services\Preorder\Money')
@php
    $cols = [
        ['New', 'placed', 'bi-inbox'],
        ['In the kitchen', ['accepted', 'preparing'], 'bi-fire'],
        ['Ready for pickup', 'ready', 'bi-bell'],
    ];
@endphp
<header class="pk-page-head">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 fade-up">
            <span class="pk-eyebrow"><i class="bi bi-shop"></i>Café dashboard</span>
            <span class="chip"><span class="live-dot"></span>Auto-refresh</span>
            @if($restaurant->is_demo)<span class="po-demo">Demo listing</span>@endif
        </div>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div class="fade-up">
                <h1 class="h2 mb-1">{{ $restaurant->name }}</h1>
                <p class="pk-muted mb-0">{{ $restaurant->accepts_preorders ? 'Taking preorders' : 'Preorders paused' }} · near {{ $restaurant->location?->name }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2 fade-up">
                @if($restaurants->count() > 1)
                    <form method="GET" action="{{ route('preorder.merchant') }}">
                        <label for="rsel" class="visually-hidden">Choose café</label>
                        <select id="rsel" name="restaurant" class="form-select" onchange="this.form.submit()">
                            @foreach($restaurants as $r)<option value="{{ $r->slug }}" @selected($r->id === $restaurant->id)>{{ $r->name }}</option>@endforeach
                        </select>
                    </form>
                @endif
                <form method="POST" action="{{ route('preorder.merchant.toggle', $restaurant) }}" data-confirm="{{ $restaurant->accepts_preorders ? 'Pause new preorders? Existing orders are not affected.' : 'Resume taking preorders?' }}">
                    @csrf
                    <button class="btn {{ $restaurant->accepts_preorders ? 'btn-ghost' : 'btn-pk' }}"><i class="bi {{ $restaurant->accepts_preorders ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i>{{ $restaurant->accepts_preorders ? 'Pause preorders' : 'Resume preorders' }}</button>
                </form>
            </div>
        </div>
    </div>
</header>

<div class="container py-4">
    @include('preorder.partials.errors')

    <div class="row g-3 mb-4">
        @foreach([['New', $stats['new'], 'bi-inbox', ''], ['In kitchen', $stats['kitchen'], 'bi-fire', 'amber'], ['Ready', $stats['ready'], 'bi-bell', 'green'], ['Orders today', $stats['today'], 'bi-receipt', 'violet']] as $k => [$l, $v, $ic, $tn])
            <div class="col-6 col-md-3">
                <div class="pk-card p-3 d-flex align-items-center gap-3 reveal" style="--d:{{ $k * .04 }}s">
                    <span class="pk-icon {{ $tn }}"><i class="bi {{ $ic }}"></i></span>
                    <div><div class="small pk-muted fw-semibold">{{ $l }}</div><div class="h4 mb-0">{{ $v }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        @foreach($cols as [$title, $st, $icon])
            @php $list = $open->whereIn('status', (array) $st); @endphp
            <div class="col-lg-4">
                <div class="mx-col">
                    <h3><span><i class="bi {{ $icon }} me-1"></i>{{ $title }}</span><span>{{ $list->count() }}</span></h3>
                    @forelse($list as $o)
                        <article class="mx-order {{ $o->status === 'placed' ? 'new' : '' }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-bold">{{ $o->reference() }}</div>
                                    <div class="small pk-muted">{{ \Illuminate\Support\Str::of($o->user?->name ?? 'Customer')->before(' ') }} · {{ $o->created_at->diffForHumans() }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold small">{{ Money::format($o->total) }}</div>
                                    <div class="small {{ $o->payment_status === 'demo_paid' ? 'text-success' : 'pk-muted' }}">{{ $o->payment_status === 'demo_paid' ? 'Demo paid' : 'Cash on pickup' }}</div>
                                </div>
                            </div>
                            <ul class="list-unstyled small my-2">
                                @foreach($o->items as $it)
                                    <li><span class="fw-bold">{{ $it->qty }}×</span> {{ $it->name }}@if($it->notes) <span class="pk-muted">— {{ $it->notes }}</span>@endif</li>
                                @endforeach
                            </ul>
                            @if($o->special_instructions)<div class="small p-2 rounded-3 mb-2" style="background:var(--pk-warn-tint);color:#8a4b04"><i class="bi bi-chat-left-text me-1"></i>{{ $o->special_instructions }}</div>@endif
                            <div class="small pk-muted mb-2"><i class="bi bi-stopwatch me-1"></i>Prep ~{{ $o->prep_minutes }} min @if($o->estimated_ready_at && $o->status !== 'placed')· due {{ $o->estimated_ready_at->format('g:i A') }}@endif
                                @if($o->timing_state === 'conflict')<span class="badge text-bg-danger ms-1" title="Customer confirmed it may clash with their queue turn">Tight for customer</span>@endif</div>

                            @if($o->status === 'placed')
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('preorder.merchant.status', $o) }}" class="flex-grow-1">@csrf<input type="hidden" name="status" value="accepted"><button class="btn btn-pk btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Accept</button></form>
                                    <form method="POST" action="{{ route('preorder.merchant.status', $o) }}" data-confirm="Decline this order? The customer will be notified.">@csrf<input type="hidden" name="status" value="rejected"><input type="hidden" name="reason" value="The café is too busy to take this order right now."><button class="btn btn-ghost btn-sm text-danger">Decline</button></form>
                                </div>
                            @elseif($o->status === 'accepted')
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('preorder.merchant.status', $o) }}" class="flex-grow-1">@csrf<input type="hidden" name="status" value="preparing"><button class="btn btn-pk btn-sm w-100"><i class="bi bi-fire me-1"></i>Start preparing</button></form>
                                    <form method="POST" action="{{ route('preorder.merchant.status', $o) }}">@csrf<input type="hidden" name="status" value="ready"><button class="btn btn-ghost btn-sm">Ready</button></form>
                                </div>
                            @elseif($o->status === 'preparing')
                                <form method="POST" action="{{ route('preorder.merchant.status', $o) }}">@csrf<input type="hidden" name="status" value="ready"><button class="btn btn-pk btn-sm w-100"><i class="bi bi-bell me-1"></i>Mark ready</button></form>
                            @elseif($o->status === 'ready')
                                <form method="POST" action="{{ route('preorder.merchant.status', $o) }}" class="d-flex gap-2">
                                    @csrf<input type="hidden" name="status" value="collected">
                                    <label for="pc-{{ $o->id }}" class="visually-hidden">Pickup code for {{ $o->reference() }}</label>
                                    <input id="pc-{{ $o->id }}" name="pickup_code" class="form-control form-control-sm" inputmode="numeric" maxlength="10" placeholder="Pickup code" required autocomplete="off">
                                    <button class="btn btn-pk btn-sm text-nowrap">Hand over</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="text-center small pk-muted py-4">Nothing here</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <section class="pk-card p-4">
                <h2 class="h6 mb-3">Recently finished</h2>
                @forelse($recent as $o)
                    <div class="d-flex justify-content-between align-items-center py-2 small" style="border-top:1px solid #f0f1f7">
                        <span><span class="fw-bold">{{ $o->reference() }}</span> · {{ $o->items->sum('qty') }} items · {{ $o->updated_at->diffForHumans() }}</span>
                        <span class="pk-status done">{{ ucfirst($o->status) }}</span>
                    </div>
                @empty
                    <p class="small pk-muted mb-0">No finished orders yet.</p>
                @endforelse
            </section>
        </div>
        <div class="col-lg-5">
            <section class="pk-card p-4">
                <h2 class="h6 mb-1">Menu availability</h2>
                <p class="small pk-muted">Sold out? Switch an item off — customers can't order it until you switch it back.</p>
                @foreach($items as $it)
                    <form method="POST" action="{{ route('preorder.merchant.item.toggle', [$restaurant, $it->id]) }}" class="mx-switch">
                        @csrf
                        <span class="min-w-0"><span class="fw-semibold">{{ $it->name }}</span> <span class="pk-muted">· {{ Money::format($it->price) }}</span></span>
                        <button class="btn btn-sm {{ $it->available ? 'btn-ghost' : 'btn-outline-primary' }}" style="padding:.3rem .7rem" aria-label="{{ $it->available ? 'Mark '.$it->name.' unavailable' : 'Mark '.$it->name.' available' }}">
                            <i class="bi {{ $it->available ? 'bi-toggle-on text-success' : 'bi-toggle-off' }} me-1"></i>{{ $it->available ? 'Available' : 'Sold out' }}
                        </button>
                    </form>
                @endforeach
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/preorder.js') }}?v={{ @filemtime(public_path('js/modules/preorder.js')) }}"></script>
<script>PreorderUI.merchant({ url: @json(route('preorder.merchant.feed', $restaurant)), signature: @json($signature) });</script>
@endpush
