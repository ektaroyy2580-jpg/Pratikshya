{{--
    Preorder widget for the ticket page. Include: @include('preorder.partials.ticket-widget', ['ticket' => $ticket])
    Shows the user's active food order (if any) and up to 3 participating cafés near the ticket's location
    with a "fits your wait?" hint. Read-only: never changes the ticket. Renders nothing if data is missing.
--}}
@php
    $pwCafes = collect(); $pwHints = []; $pwOrder = null;
    try {
        if (isset($ticket) && $ticket && auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('restaurants')) {
            $pwTiming = app(\App\Services\Preorder\TimingCheck::class);
            $pwOrder = \App\Models\FoodOrder::where('user_id', auth()->id())->whereIn('status', \App\Models\FoodOrder::OPEN)->with('restaurant')->latest()->first();
            $pwCafes = \App\Models\Restaurant::participating()->where('location_id', $ticket->service?->location_id)->orderBy('name')->take(3)->get();
            foreach ($pwCafes as $c) {
                $q = $c->quickestItem();
                $pwHints[$c->id] = $q ? $pwTiming->evaluate($c, [['prep_minutes' => $q->prep_minutes, 'qty' => 1]], $ticket) : null;
            }
        }
    } catch (\Throwable $e) {
        $pwCafes = collect(); $pwOrder = null;
    }
@endphp
@if($pwOrder || $pwCafes->isNotEmpty())
    @once<link href="{{ asset('css/modules/preorder.css') }}?v={{ @filemtime(public_path('css/modules/preorder.css')) }}" rel="stylesheet">@endonce
    <section class="pk-card p-3 p-md-4 po-widget" aria-labelledby="poWidgetH">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h2 id="poWidgetH" class="h6 mb-0"><i class="bi bi-cup-hot me-1" style="color:var(--pk-violet)"></i>Hungry while you wait?</h2>
            <a href="{{ route('preorder.index') }}" class="small fw-semibold text-decoration-none">All cafés</a>
        </div>
        @if($pwOrder)
            <a href="{{ $pwOrder->restaurant ? \App\Services\Cafes\CafeHub::restaurantUrl($pwOrder->restaurant, 'order') : route('preorder.order', $pwOrder) }}" class="d-flex align-items-center justify-content-between gap-2 p-2 mb-2 rounded-3 text-decoration-none" style="background:var(--pk-tint);color:inherit">
                <span class="small min-w-0 text-truncate"><span class="live-dot me-1"></span><span class="fw-bold">{{ $pwOrder->reference() }}</span> · {{ $pwOrder->restaurant?->name }}</span>
                <span class="pk-status {{ $pwOrder->status === 'ready' ? 'called' : ($pwOrder->status === 'placed' ? 'waiting' : 'serving') }}">{{ ucfirst($pwOrder->status) }}</span>
            </a>
        @endif
        @foreach($pwCafes as $c)
            @php $pwUrl = \App\Services\Cafes\CafeHub::restaurantUrl($c, null); @endphp
            <div class="po-mini po-mini-cafe">
                <a href="{{ $pwUrl }}" tabindex="-1" aria-hidden="true">@include('preorder.partials.food-art', ['art' => $c->art, 'class' => 'sq', 'label' => $c->name, 'photo' => $c->image_path])</a>
                <span class="min-w-0">
                    <a href="{{ $pwUrl }}" class="fw-bold d-block text-truncate text-decoration-none" style="color:inherit">{{ $c->name }}</a>
                    <span class="small pk-muted d-block text-truncate">{{ $c->cuisine }} · {{ $pwHints[$c->id]['walk_minutes'] ?? $c->walk_minutes }} min walk</span>
                    @if($pwHints[$c->id] ?? null)<span class="d-inline-block mt-1">@include('preorder.partials.fit-pill', ['hint' => $pwHints[$c->id]])</span>@endif
                </span>
                <span class="d-flex flex-column gap-1">
                    <a href="{{ $pwUrl }}" class="btn btn-ghost btn-sm py-1 px-2">Open café</a>
                    <a href="{{ \App\Services\Cafes\CafeHub::restaurantUrl($c, 'menu') }}" class="btn btn-pk btn-sm py-1 px-2">Pre-order</a>
                </span>
            </div>
        @endforeach
        <p class="small pk-muted mb-0 mt-2"><i class="bi bi-shield-check me-1"></i>Food orders never change your place in the queue.</p>
    </section>
@endif
