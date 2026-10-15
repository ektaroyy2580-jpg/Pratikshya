{{-- "Getting there" card for a service page. Needs: $service (App\Models\Service). Safe if missing. --}}
@php
    $rwService = $service ?? null;
    $rwLoc = $rwService?->location;
    $rwUser = auth()->user();
    $rwModes = null;
    $rwFrom = null;
    if ($rwLoc && $rwLoc->lat && $rwLoc->lng && $rwUser) {
        $rwLast = \App\Models\RideRequest::ownedBy($rwUser)->latest('id')->first();
        if ($rwLast) {
            $rwTravel = app(\App\Services\Rides\TravelEstimator::class);
            $rwKm = \App\Services\Rides\Geo::haversine($rwLast->pickup_lat, $rwLast->pickup_lng, (float) $rwLoc->lat, (float) $rwLoc->lng);
            if ($rwKm >= 0.05) {
                $rwModes = $rwTravel->modes($rwKm * (float) config('rides.road_factor', 1.3));
                $rwFrom = $rwLast->pickup_label ?: 'your last pickup';
            }
        }
    }
@endphp
@if($rwService && $rwLoc)
<div class="pk-card p-3 p-md-4 rides-widget">
    <div class="d-flex align-items-center gap-3 mb-2">
        <span class="pk-icon violet"><i class="bi bi-signpost-split"></i></span>
        <div class="flex-grow-1" style="min-width:0">
            <h3 class="h6 fw-bold mb-0">Getting there</h3>
            <div class="small pk-muted text-truncate">{{ $rwLoc->name }}{{ $rwLoc->area ? ', '.$rwLoc->area : '' }}</div>
        </div>
    </div>
    @if($rwModes)
        <div class="small pk-muted mb-2">Approximate times from {{ $rwFrom }}:</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($rwModes as $key => $m)
                <a class="chip text-decoration-none" href="{{ route('rides.plan', ['service' => $rwService->id, 'mode' => $key]) }}"><i class="bi {{ $m['icon'] }}"></i>~{{ $m['minutes'] }} min</a>
            @endforeach
        </div>
    @else
        <p class="small pk-muted mb-3">Compare walking, bike, car and bus times, plus estimated inDrive, Yango and Pathao fares in NPR.</p>
    @endif
    <a class="btn btn-ghost btn-sm w-100" href="{{ route('rides.plan', ['service' => $rwService->id]) }}"><i class="bi bi-map me-1"></i>Plan route &amp; compare rides</a>
    <div class="small pk-muted mt-2" style="font-size:.74rem"><i class="bi bi-info-circle me-1"></i>Fares are estimates; booking happens in the provider's app.</div>
</div>
@endif
