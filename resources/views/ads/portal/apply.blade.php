@extends('layouts.app')
@section('title', 'Advertise with us — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ads.css') }}?v={{ @filemtime(public_path('css/modules/ads.css')) }}" rel="stylesheet">
@endpush

@section('content')
<header class="pk-page-head">
    <div class="container">
        <span class="pk-eyebrow"><i class="bi bi-megaphone"></i>For local businesses</span>
        <h1 class="h2 mb-1 mt-1">Reach people while they wait</h1>
        <p class="pk-muted mb-0" style="max-width: 60ch">Clearly labelled sponsored cards on Explore, nearby places and the game hub. Reviewed by our team before they run.</p>
    </div>
</header>
<div class="container py-4 py-md-5">
    <div class="row g-4">
        <div class="col-lg-7">
            @if($mine->isNotEmpty())
                <div class="alert alert-success d-flex gap-2 align-items-center">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="flex-grow-1">You already manage {{ $mine->pluck('name')->implode(', ') }}.</div>
                    <a href="{{ route('ads.portal') }}" class="btn btn-sm btn-pk">Open portal</a>
                </div>
            @endif
            <form method="post" action="{{ route('ads.apply.store') }}" class="pk-card p-3 p-md-4 reveal" novalidate>
                @csrf
                @include('ads.partials.errors')
                <h2 class="h5 mb-3">Business details</h2>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="name">Business name</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" maxlength="120" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="category">Category</label>
                        <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                            @foreach(config('ads.categories') as $k => $label)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $label }}</option>@endforeach
                        </select>
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="contact_name">Contact person</label>
                        <input class="form-control @error('contact_name') is-invalid @enderror" id="contact_name" name="contact_name" value="{{ old('contact_name', auth()->user()->name) }}" required>
                        @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="contact_email">Contact email</label>
                        <input type="email" class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" value="{{ old('contact_email') }}" required>
                        @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="contact_phone">Phone <span class="pk-muted fw-normal">(optional)</span></label>
                        <input class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" inputmode="tel">
                        @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input @error('accept_policy') is-invalid @enderror" type="checkbox" value="1" id="accept_policy" name="accept_policy" @checked(old('accept_policy'))>
                            <label class="form-check-label small" for="accept_policy">I agree that ads are labelled "Sponsored", are reviewed before running, may not make medical claims, and can never buy queue priority.</label>
                            @error('accept_policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <button class="btn btn-pk mt-4"><i class="bi bi-send me-1"></i>Apply to advertise</button>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="pk-card p-3 p-md-4 reveal" style="--d:.08s">
                <h2 class="h6">What to expect</h2>
                <ol class="small pk-muted ps-3 d-grid gap-2 mb-0">
                    <li>An admin reviews and approves your business.</li>
                    <li>You create campaigns: headline, image or generated design, placement, dates and budget (NPR).</li>
                    <li>Each campaign is reviewed before going live. Healthcare ads require a disclosure.</li>
                    <li>Reports show real impressions and clicks from the app. This demo build does not charge anyone.</li>
                </ol>
            </div>
            @include('ads.partials.policy', ['class' => 'mt-3'])
        </div>
    </div>
</div>
@endsection
