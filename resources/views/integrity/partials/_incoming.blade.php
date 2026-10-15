{{-- One incoming exchange request for the signed-in recipient. Needs $x (QueueExchangeRequest with service). --}}
@php
    $svc = $x->service;
    $theirs = $svc->token($x->requester_number_before);
    $mine = $svc->token($x->target_number_before);
    $diff = $x->target_number_before - $x->requester_number_before;   // >0: I'd move earlier
@endphp
<div class="ig-request incoming" data-ig-card>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div class="ig-min-0">
            <div class="fw-bold">{{ $theirs }} asks to swap places with you</div>
            <div class="ig-note">{{ $svc->name }}@if($svc->relationLoaded('location') && $svc->location) · {{ $svc->location->name }}@endif</div>
        </div>
        @if($x->status === 'pending')
            <span class="ig-countdown" data-expires="{{ $x->expires_at->toIso8601String() }}"><i class="bi bi-clock"></i><span class="v">{{ $x->expires_at->diffForHumans(null, true) }} left</span></span>
        @else
            <span class="ig-st reviewing"><i class="bi bi-person-check"></i> Awaiting staff</span>
        @endif
    </div>
    <div class="ig-swap mb-3">
        <div class="tk mine"><small>You now</small><span class="ig-token">{{ $mine }}</span></div>
        <i class="bi bi-arrow-left-right arrow" aria-hidden="true"></i>
        <div class="tk"><small>You'd get</small><span class="ig-token">{{ $theirs }}</span></div>
        <span class="ig-move {{ $diff > 0 ? 'earlier' : 'later' }}">
            <i class="bi {{ $diff > 0 ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
            {{ abs($diff) }} {{ \Illuminate\Support\Str::plural('place', abs($diff)) }} {{ $diff > 0 ? 'earlier' : 'later' }}
        </span>
    </div>
    @if($x->status === 'pending')
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('integrity.exchange.accept', $x) }}">
                @csrf
                <button type="submit" class="btn btn-pk btn-sm" data-expiring data-ig-confirm="Tap again to swap"><i class="bi bi-check2 me-1"></i>Accept</button>
            </form>
            <form method="POST" action="{{ route('integrity.exchange.decline', $x) }}" data-ig-once>
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" data-expiring data-busy="Declining…"><i class="bi bi-x-lg me-1"></i>Decline</button>
            </form>
        </div>
        @if($svc->requires_exchange_approval)
            <div class="ig-note mt-2"><i class="bi bi-person-badge me-1"></i>Counter staff must also approve before positions change.</div>
        @endif
    @else
        <div class="ig-note"><i class="bi bi-hourglass-split me-1"></i>You accepted. Positions change only after staff approve.</div>
    @endif
</div>
