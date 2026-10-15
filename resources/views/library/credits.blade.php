@extends('layouts.app')
@section('title', 'Image credits — E-Library')

@include('library.partials._styles')

@php
    // Only ever link out to Wikimedia / Creative Commons pages stored by the seeder.
    $safeUrl = fn ($u) => is_string($u) && preg_match('#^https://(commons\.wikimedia\.org|creativecommons\.org|en\.wikipedia\.org)/#', $u) ? $u : null;
    $licenceLink = function (?array $c) use ($safeUrl) {
        if (! $c) return '';
        $url = $safeUrl($c['license_url'] ?? null);
        return $url ? '<a href="'.e($url).'" target="_blank" rel="noopener license">'.e($c['license']).'</a>' : e($c['license'] ?? '');
    };
    $count = $books->sum(fn ($b) => ($b->image_credit ? 1 : 0) + $b->chapters->sum(fn ($c) => count($c->illustrations ?? [])));
@endphp

@section('content')
<header class="pk-page-head lib-head">
    <div class="container">
        <a href="{{ route('library.home') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>E-Library</a>
        <h1 class="mt-3 mb-2">Image credits</h1>
        <p class="lead mb-0">Every cover photo and illustration in the library is <strong>public domain</strong> or licensed under <strong>Creative Commons</strong> (CC0, CC BY, CC BY-SA). They were downloaded once from Wikimedia Commons, resized for the web and are served from this app — nothing is loaded from Commons while you read.</p>
    </div>
</header>

<div class="container py-4 py-md-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="pk-card p-3 p-md-4" aria-labelledby="creditsTitle">
                <h2 id="creditsTitle" class="lib-section-title mb-1"><i class="bi bi-camera"></i> {{ $count }} {{ \Illuminate\Support\Str::plural('image', $count) }} in {{ $books->count() }} {{ \Illuminate\Support\Str::plural('item', $books->count()) }}</h2>
                @forelse($books as $b)
                    <article class="lib-credits-row" id="{{ $b->slug }}">
                        <a href="{{ route('library.book', $b) }}" tabindex="-1" aria-hidden="true">@include('library.partials.cover', ['book' => $b, 'size' => 'sm'])</a>
                        <div class="min-w-0">
                            <a href="{{ route('library.book', $b) }}" class="fw-bold text-decoration-none" @if($b->language === 'ne') lang="ne" @endif>{{ $b->title }}</a>
                            <ul class="lib-credits-list">
                                @if($b->image_credit)
                                    @php $c = $b->image_credit; @endphp
                                    <li><span class="k">Cover</span><span>“{{ $c['title'] ?? 'Image' }}” by {{ $c['author'] ?? 'Unknown' }} · {!! $licenceLink($c) !!}@if($safeUrl($c['source_url'] ?? null)) · <a href="{{ $safeUrl($c['source_url']) }}" target="_blank" rel="noopener">Source</a>@endif</span></li>
                                @endif
                                @foreach($b->chapters as $ch)
                                    @foreach((array) $ch->illustrations as $ill)
                                        @php $c = $ill['credit'] ?? null; @endphp
                                        @if($c)
                                            <li><span class="k">Ch. {{ $ch->position }}</span><span>“{{ $c['title'] ?? 'Image' }}” by {{ $c['author'] ?? 'Unknown' }} · {!! $licenceLink($c) !!}@if($safeUrl($c['source_url'] ?? null)) · <a href="{{ $safeUrl($c['source_url']) }}" target="_blank" rel="noopener">Source</a>@endif</span></li>
                                        @endif
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    </article>
                @empty
                    <div class="lib-empty">
                        <span class="pk-icon violet"><i class="bi bi-image"></i></span>
                        <p class="mb-0">No photos have been added yet — items use their generated covers.</p>
                    </div>
                @endforelse
            </section>
        </div>
        <div class="col-lg-4">
            <div class="lib-license mb-3"><i class="bi bi-info-circle" style="color:var(--pk-blue)"></i><div><strong>How images are used.</strong> Images are shown unaltered apart from resizing and cropping to fit a cover. CC BY-SA images remain under their original licence. Where an author is unknown, Commons lists none.</div></div>
            <div class="lib-license"><i class="bi bi-palette" style="color:var(--pk-violet)"></i><div><strong>Comics.</strong> The comics are original vector art drawn for Pratikshya and are not on this list. Items without a photo use a generated cover.</div></div>
        </div>
    </div>
</div>
@endsection
