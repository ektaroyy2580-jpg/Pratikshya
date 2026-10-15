{{-- One exchange awaiting staff approval. Needs $x (with service, requesterTicket, targetTicket). --}}
@php $svc = $x->service; @endphp
<div class="ig-request" data-ig-card>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="ig-swap">
            <div class="tk"><small>Requester</small><span class="ig-token">{{ $svc->token($x->requester_number_before) }}</span></div>
            <i class="bi bi-arrow-left-right arrow" aria-hidden="true"></i>
            <div class="tk"><small>Accepted by</small><span class="ig-token">{{ $svc->token($x->target_number_before) }}</span></div>
        </div>
        <span class="ig-countdown" data-expires="{{ $x->expires_at->toIso8601String() }}"><i class="bi bi-clock"></i><span class="v">{{ $x->expires_at->diffForHumans(null, true) }} left</span></span>
    </div>
    <div class="ig-note mb-2">{{ $svc->name }}@if($svc->relationLoaded('location') && $svc->location) · {{ $svc->location->name }}@endif · both tokens still waiting · both people agreed</div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <form method="POST" action="{{ route('integrity.staff.exchanges.approve', $x) }}" class="d-flex gap-2 flex-wrap flex-grow-1">
            @csrf
            <label class="visually-hidden" for="igNote{{ $x->id }}">Approval note (optional)</label>
            <input id="igNote{{ $x->id }}" name="note" class="form-control form-control-sm flex-grow-1" style="max-width:240px" maxlength="255" placeholder="Note (optional)">
            <button type="submit" class="btn btn-pk btn-sm" data-expiring data-ig-confirm="Tap again to approve"><i class="bi bi-check2 me-1"></i>Approve swap</button>
        </form>
        <form method="POST" action="{{ route('integrity.staff.exchanges.reject', $x) }}" data-ig-once>
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm" data-expiring data-busy="Rejecting…"><i class="bi bi-x-lg me-1"></i>Reject</button>
        </form>
    </div>
</div>
