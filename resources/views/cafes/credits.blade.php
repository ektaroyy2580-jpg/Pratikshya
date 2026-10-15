@extends('layouts.app')
@section('title', 'Photo credits — प्रतीक्षा')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/modules/cafes.css') }}?v={{ @filemtime(public_path('css/modules/cafes.css')) }}">
    <script>document.documentElement.classList.add('cf-js');</script>
    <noscript><style>.cf-photo img.cf-img{opacity:1;transform:none}</style></noscript>
@endpush

@section('content')
<header class="pk-page-head">
    <div class="container">
        @auth<a class="pk-breadcrumb fade-up d-inline-block mb-3" href="{{ route('explore.index') }}"><i class="bi bi-arrow-left me-1"></i>Explore</a>@endauth
        <div><span class="pk-eyebrow fade-up"><i class="bi bi-image"></i>Attribution</span></div>
        <h1 class="h2 mt-2 mb-2 fade-up">Photo credits</h1>
        <p class="pk-muted mb-0 fade-up" style="max-width:720px">The cafés, restaurants and menus in this app are fictional demo listings. Their photos are <b>representative</b> images from
            <a href="https://commons.wikimedia.org/" rel="noopener" target="_blank">Wikimedia Commons</a>, used under the licence shown for each (CC0, CC BY or CC BY-SA). They do not show the listed business.
            Images were resized to web thumbnails; no other changes were made.</p>
    </div>
</header>

<div class="container py-4">
    @foreach($groups as $heading => $images)
        @continue($images->isEmpty())
        <section class="mb-5" aria-labelledby="h-{{ $loop->index }}">
            <h2 id="h-{{ $loop->index }}" class="h5 mb-3">{{ $heading }} <span class="pk-muted fw-normal small">({{ $images->count() }})</span></h2>
            <div class="cf-credits">
                @foreach($images as $img)
                    <figure class="reveal" style="--d:{{ min($loop->index, 12) * .03 }}s" id="{{ str_replace('/', '-', $img['key']) }}">
                        @include('cafes.partials.photo', ['url' => asset($img['path']), 'alt' => $img['alt']])
                        <figcaption>
                            <b>{{ $img['alt'] }}</b><br>
                            “<a href="{{ $img['source_url'] }}" rel="noopener" target="_blank">{{ $img['title'] }}</a>” by {{ $img['author'] }},
                            @if($img['license_url'])<a href="{{ $img['license_url'] }}" rel="license noopener" target="_blank">{{ $img['license'] }}</a>@else{{ $img['license'] }}@endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endforeach
    <p class="small pk-muted"><i class="bi bi-info-circle me-1"></i>Map tiles © OpenStreetMap contributors. To report a credit problem, contact the app administrator.</p>
</div>
@endsection
