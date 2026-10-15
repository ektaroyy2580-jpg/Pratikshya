{{-- One open request the signed-in user sent. Needs $x (QueueExchangeRequest with service). --}}
@php
    $svc = $x->service;
    $diff = $x->requester_number_before - $x->target_number_before;   // >0: I'd move earlier
@endphp
<div class="ig-request" data-ig-card>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="ig-swap">
            <div class="tk mine"><small>Yours</small><span class="ig-token">{{ $svc->token($x->requester_number_before) }}</span></div>
            <i class="bi bi-arrow-left-right arrow" aria-hidden="true"></i>
            <div class="tk"><small>Asked</small><span class="ig-token">{{ $svc->token($x->target_number_before) }}</span></div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
            @if($x->status === 'pending')
                <span class="ig-countdown" data-expires="{{ $x->expires_at->toIso8601String() }}"><i class="bi bi-clock"></i><span class="v">{{ $x->expires_at->diffForHumans(null, true) }} left</span></span>
            @else
                <span class="ig-st reviewing"><i class="bi bi-person-check"></i> Accepted · awaiting staff</span>
            @endif
            <span class="ig-move {{ $diff > 0 ? 'earlier' : 'later' }}">{{ abs($diff) }} {{ \Illuminate\Support\Str::plural('place', abs($diff)) }} {{ $diff > 0 ? 'earlier' : 'later' }} for you</span>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <span class="ig-note">{{ $x->status === 'pending' ? 'Waiting for the other person to reply.' : 'They accepted. Counter staff will approve it.' }} Your place is unchanged until then.</span>
        <form method="POST" action="{{ route('integrity.exchange.cancel', $x) }}" data-ig-once>
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm" data-busy="Withdrawing…"><i class="bi bi-arrow-counterclockwise me-1"></i>Withdraw</button>
        </form>
    </div>
</div>
