{{-- Emergency panel. Optional: $profile (HospitalProfile). Numbers come from config('explore.emergency_numbers'). --}}
@php $numbers = config('explore.emergency_numbers', []); @endphp
<section class="hx-emergency" aria-labelledby="hxEmergencyTitle">
    <div class="top">
        <span class="pk-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <h2 class="h5 mb-0" id="hxEmergencyTitle">Emergency?</h2>
            <div class="small fw-semibold" style="opacity:.9">Go to the hospital Emergency desk now — do not wait in a queue for emergencies.</div>
        </div>
    </div>
    <div class="p-3 p-md-4">
        <div class="row g-2 g-md-3">
            @foreach($numbers as $n)
                <div class="col-4">
                    <a class="hx-call" href="tel:{{ $n['number'] }}" aria-label="Call {{ $n['label'] }} {{ $n['number'] }}">
                        <span class="ic"><i class="bi {{ $n['icon'] }}"></i></span>
                        <span><span class="num d-block">{{ $n['number'] }}</span><span class="lbl">{{ $n['label'] }}</span></span>
                    </a>
                </div>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center mt-3 small">
            @if(! empty($profile?->emergency_desk))
                <span class="fw-bold"><i class="bi bi-door-open-fill text-danger me-1"></i>{{ $profile->emergency_desk }}</span>
            @endif
            <span class="pk-muted">Nepal emergency numbers — verify locally. Queue estimates never apply to emergency care.</span>
        </div>
    </div>
</section>
