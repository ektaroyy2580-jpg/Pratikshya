{{--
    Hospital emergency banner. Pass $ticket (App\Models\Ticket) or $location (App\Models\Location).
    Renders nothing unless the location is a hospital.
    Include with: @includeIf('explore.partials.hospital-banner', ['ticket' => $ticket])
--}}
@php
    $hxLoc = $location ?? null;
    if (! $hxLoc && isset($ticket) && $ticket instanceof \App\Models\Ticket) {
        $hxLoc = $ticket->service?->location;
    }
    $hxShow = $hxLoc instanceof \App\Models\Location
        && \App\Http\Controllers\Explore\HospitalController::isHospital($hxLoc)
        && \Illuminate\Support\Facades\Route::has('explore.hospital');
@endphp
@if($hxShow)
    @once
        <link rel="stylesheet" href="{{ asset('css/modules/explore.css') }}?v={{ filemtime(public_path('css/modules/explore.css')) }}">
    @endonce
    <div class="hx-banner" role="note">
        <span class="ic"><i class="bi bi-heart-pulse-fill"></i></span>
        <div class="flex-grow-1 small">
            <b>Emergency? Go to the Emergency desk now — don't wait in a queue.</b>
            Ambulance <a href="tel:102" class="fw-bold">102</a> · Police <a href="tel:100" class="fw-bold">100</a> · Fire <a href="tel:101" class="fw-bold">101</a> <span class="opacity-75">(verify locally)</span>
        </div>
        <a href="{{ route('explore.hospital', $hxLoc) }}" class="btn btn-sm btn-ghost flex-shrink-0">Hospital help</a>
    </div>
@endif
