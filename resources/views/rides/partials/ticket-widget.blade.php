{{-- "Getting there" + leave-by card for a queue ticket. Needs: $ticket (App\Models\Ticket). Read-only: never changes queue order. --}}
@php
    $rtTicket = $ticket ?? null;
    $rtLoc = $rtTicket?->service?->location;
    $rtShow = $rtTicket && $rtLoc && $rtTicket->isActive() && auth()->id() === $rtTicket->user_id;
    $rtLeave = null; $rtRec = null; $rtFrom = null; $rtKm = null;
    if ($rtShow && $rtLoc->lat && $rtLoc->lng) {
        // Pickup = this ticket's planned trip, else the user's most recent pickup.
        $rtRide = \App\Models\RideRequest::where('user_id', $rtTicket->user_id)->where('ticket_id', $rtTicket->id)->latest('id')->first()
            ?? \App\Models\RideRequest::where('user_id', $rtTicket->user_id)->latest('id')->first();
        if ($rtRide) {
            $rtTravel = app(\App\Services\Rides\TravelEstimator::class);
            $rtKm = \App\Services\Rides\Geo::haversine($rtRide->pickup_lat, $rtRide->pickup_lng, (float) $rtLoc->lat, (float) $rtLoc->lng);
            $rtModes = $rtTravel->modes($rtKm * (float) config('rides.road_factor', 1.3));
            $rtRec = $rtTravel->recommend($rtKm, $rtModes);
            $rtLeave = app(\App\Services\Rides\RidePlanner::class)->leaveBy($rtTicket, $rtModes[$rtRec['mode']]['minutes']);
            $rtLeave['mode_label'] = $rtModes[$rtRec['mode']]['label'];
            $rtFrom = $rtRide->pickup_label ?: 'your last pickup';
        }
    }
@endphp
@if($rtShow)
<div class="pk-card p-3 p-md-4 rides-widget">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="pk-icon violet"><i class="bi bi-signpost-split"></i></span>
        <div class="flex-grow-1" style="min-width:0">
            <h3 class="h6 fw-bold mb-0">Getting there</h3>
            <div class="small pk-muted text-truncate">{{ $rtLoc->name }}</div>
        </div>
    </div>
    @if($rtLeave)
        <div class="p-3 rounded-4 mb-3" style="background:{{ $rtLeave['leave_now'] ? 'var(--pk-danger-tint)' : 'var(--pk-tint)' }}">
            <div class="small fw-semibold pk-muted">From {{ $rtFrom }} · {{ $rtLeave['mode_label'] }} ~{{ $rtLeave['travel_minutes'] }} min</div>
            <div class="h5 fw-bold mb-0 mt-1" style="color:{{ $rtLeave['leave_now'] ? 'var(--pk-danger)' : 'var(--pk-blue)' }}">{{ $rtLeave['leave_now'] ? 'Leave now' : 'Leave by '.$rtLeave['leave_at'] }}</div>
            <div class="small pk-muted mt-1">Turn in ~{{ $rtLeave['turn_low'] }}–{{ $rtLeave['turn_high'] }} min (estimate) · {{ $rtLeave['buffer_minutes'] }} min buffer</div>
        </div>
        @if($rtKm > (float) config('rides.far_threshold_km', 2))
            <div class="small mb-3"><i class="bi bi-lightbulb me-1 text-warning"></i>{{ $rtRec['reason'] }}</div>
        @endif
    @else
        <p class="small pk-muted mb-3">Set your pickup to get a “leave by” time and compare rides — your queue spot is never affected.</p>
    @endif
    <div class="d-grid gap-2" style="grid-template-columns:repeat(4,1fr)">
        @foreach(config('rides.modes') as $key => $m)
            <a class="btn btn-ghost btn-sm px-1 py-2 d-flex flex-column align-items-center" style="font-size:.72rem" href="{{ route('rides.plan', ['service' => $rtTicket->service_id, 'mode' => $key]) }}"><i class="bi {{ $m['icon'] }} fs-5"></i>{{ $key === 'transit' ? 'Bus' : \Illuminate\Support\Str::before($m['label'], ' /') }}</a>
        @endforeach
    </div>
    <div class="small pk-muted mt-2" style="font-size:.74rem"><i class="bi bi-info-circle me-1"></i>Fares are estimates; booking happens in the provider's app.</div>
</div>
@endif
