@extends('platform.layouts.console')
@section('title', ($place->exists ? $place->name : 'New place').' — प्रतीक्षा')

@section('console')
@php $editing = $place->exists; @endphp
<div class="pf-title fade-up">
    <div>
        <a href="{{ route('platform.manage.places') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left"></i> Nearby places</a>
        <h1>{{ $editing ? $place->name : 'New nearby place' }}</h1>
        <p>Shown on queue pages and the explore map for people waiting at the chosen location.</p>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('platform.manage.places.update', $place) : route('platform.manage.places.store') }}" class="pk-card pf-panel pf-form reveal">
    @csrf
    <div class="pf-panel-head"><h2><i class="bi bi-shop text-primary"></i> Place details</h2></div>
    <div class="pf-panel-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="p_loc">Near location</label>
                        <select class="form-select" id="p_loc" name="location_id" required>
                            @foreach($locations as $l)<option value="{{ $l->id }}" @selected((int) old('location_id', $place->location_id) === $l->id)>{{ $l->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-9">
                        <label class="form-label" for="p_name">Name</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="p_name" name="name" value="{{ old('name', $place->name) }}" required maxlength="120">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-3">
                        <label class="form-label" for="p_emoji">Emoji</label>
                        <input class="form-control text-center @error('emoji') is-invalid @enderror" id="p_emoji" name="emoji" value="{{ old('emoji', $place->emoji) }}" required maxlength="8">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="p_cat">Category</label>
                        <input class="form-control @error('category') is-invalid @enderror" id="p_cat" name="category" value="{{ old('category', $place->category) }}" list="catList" required maxlength="40">
                        <datalist id="catList"><option value="Cafe"><option value="Restaurant"><option value="Park"><option value="Bookstore"><option value="Heritage"><option value="Market"><option value="Errand"><option value="Pharmacy"></datalist>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="p_rating">Rating (0–5)</label>
                        <input class="form-control @error('rating') is-invalid @enderror" id="p_rating" type="number" step="0.1" min="0" max="5" name="rating" value="{{ old('rating', $place->rating) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="p_tip">Tip for waiting visitors</label>
                        <input class="form-control @error('tip') is-invalid @enderror" id="p_tip" name="tip" value="{{ old('tip', $place->tip) }}" required maxlength="200">
                        @error('tip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="p_walk">Walk (minutes)</label>
                        <input class="form-control" id="p_walk" type="number" min="0" max="120" name="walk_minutes" value="{{ old('walk_minutes', $place->walk_minutes) }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="p_visit">Visit (minutes)</label>
                        <input class="form-control" id="p_visit" type="number" min="1" max="240" name="visit_minutes" value="{{ old('visit_minutes', $place->visit_minutes) }}" required>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                @include('platform.manage.partials.map-picker', ['lat' => $place->lat, 'lng' => $place->lng])
            </div>
            <div class="col-12">
                <div class="pf-switch">
                    <div><div class="t">Business listing</div><div class="d">Mark as a listed business (contact & hours shown). Listings are informational — paid placement belongs to the advertising module and is always labelled “Sponsored”.</div></div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_business_listing" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_business_listing" value="1" aria-label="Business listing" @checked(old('is_business_listing', $place->is_business_listing))>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="p_phone">Listing phone</label>
                        <input class="form-control @error('listing_phone') is-invalid @enderror" id="p_phone" name="listing_phone" value="{{ old('listing_phone', $place->listing_phone) }}" maxlength="30">
                        @error('listing_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="p_hours">Opening hours</label>
                        <input class="form-control" id="p_hours" name="listing_hours" value="{{ old('listing_hours', $place->listing_hours) }}" placeholder="e.g. Sun–Fri 7:00–19:00" maxlength="120">
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
            <button class="btn btn-pk"><i class="bi bi-check2 me-1"></i> {{ $editing ? 'Save place' : 'Add place' }}</button>
        </div>
    </div>
</form>
@if($editing)
    <form method="POST" action="{{ route('platform.manage.places.delete', $place) }}" class="mt-3 text-end" data-confirm="Remove this place? This cannot be undone.">
        @csrf<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3 me-1"></i> Remove place</button>
    </form>
@endif
@endsection
