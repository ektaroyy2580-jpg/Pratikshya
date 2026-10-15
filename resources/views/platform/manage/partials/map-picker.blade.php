{{-- Pin picker. Needs $lat, $lng (current values). Field names: lat, lng. --}}
@once
    @push('head')<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endpush
    @push('scripts')
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script src="{{ asset('js/modules/platform-map.js') }}?v={{ filemtime(public_path('js/modules/platform-map.js')) }}"></script>
    @endpush
@endonce
<div class="row g-3">
    <div class="col-12">
        <div class="pf-map" data-pf-map data-lat="#pf_lat" data-lng="#pf_lng" data-note="#pf_map_note" role="application" aria-label="Map: click or drag the pin to set the location"></div>
        <div class="pf-map-note mt-2" id="pf_map_note"><i class="bi bi-hand-index me-1"></i>Click the map or drag the pin. Coordinates must be inside Nepal.</div>
    </div>
    <div class="col-6">
        <label class="form-label" for="pf_lat">Latitude</label>
        <input class="form-control @error('lat') is-invalid @enderror" id="pf_lat" name="lat" inputmode="decimal" value="{{ old('lat', $lat) }}" required>
        @error('lat')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6">
        <label class="form-label" for="pf_lng">Longitude</label>
        <input class="form-control @error('lng') is-invalid @enderror" id="pf_lng" name="lng" inputmode="decimal" value="{{ old('lng', $lng) }}" required>
        @error('lng')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
