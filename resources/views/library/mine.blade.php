@extends('layouts.app')
@section('title', 'My library — E-Library')

@include('library.partials._styles')

@section('content')
<header class="pk-page-head lib-head">
    <div class="container">
        <a href="{{ route('library.home') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>E-Library</a>
        <h1 class="mt-3 mb-1">My library</h1>
        <p class="pk-muted fw-semibold mb-4">Your history, favourites and bookmarks — private to your account and the same in every queue.</p>
        <nav class="lib-tabs" aria-label="My library sections">
            @foreach(['history' => ['bi-clock-history', 'History', $history->count()], 'favorites' => ['bi-heart', 'Favourites', $favorites->count()], 'bookmarks' => ['bi-bookmark', 'Bookmarks', $bookmarks->count()]] as $key => [$icon, $label, $n])
                <a href="{{ route('library.mine', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif><i class="bi {{ $icon }} me-1"></i>{{ $label }} <span class="pk-muted">{{ $n }}</span></a>
            @endforeach
        </nav>
    </div>
</header>

<div class="container py-4 py-md-5">
    <div class="mb-4 reveal">@include('library.partials.continue-card', ['progress' => $continue])</div>

    <section class="pk-card px-3 px-md-4 py-2 reveal" style="--d:.05s">
        @if($tab === 'history')
            <h2 class="visually-hidden">Reading history</h2>
            @forelse($history as $h)
                <div class="lib-row">
                    @include('library.partials.cover', ['book' => $h->book, 'size' => 'sm'])
                    <div class="min-w-0 flex-grow-1">
                        <a href="{{ $h->completed ? route('library.book', $h->book) : $h->resumeUrl() }}" class="r-t d-block text-truncate" @if($h->book->language === 'ne') lang="ne" @endif>{{ $h->book->title }}</a>
                        <div class="r-m text-truncate">{{ $h->completed ? 'Finished '.$h->completed_at?->diffForHumans() : $h->placeLabel() }}</div>
                        <div class="d-flex align-items-center gap-2 mt-1" style="max-width:320px">
                            <div class="progress flex-grow-1" style="height:5px" role="progressbar" aria-label="Progress" aria-valuenow="{{ (int) $h->overall_percent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar {{ $h->completed ? 'bg-success' : '' }}" style="width:{{ (int) $h->overall_percent }}%"></div></div>
                            <span class="small fw-bold pk-muted">{{ (int) $h->overall_percent }}%</span>
                        </div>
                    </div>
                    <span class="small pk-muted d-none d-md-inline text-nowrap">{{ $h->last_read_at->diffForHumans() }}</span>
                    <form method="post" action="{{ route('library.history.hide', $h->book) }}">
                        @csrf
                        <button class="btn btn-sm btn-ghost px-2" aria-label="Remove {{ $h->book->title }} from history" title="Remove from history"><i class="bi bi-x-lg"></i></button>
                    </form>
                </div>
            @empty
                <div class="lib-empty"><span class="pk-icon"><i class="bi bi-clock-history"></i></span><h3 class="h6 fw-bold">No reading history yet</h3><p class="mb-3">Open any story and your place is saved automatically.</p><a class="btn btn-pk btn-sm" href="{{ route('library.home') }}">Find something to read</a></div>
            @endforelse
        @elseif($tab === 'favorites')
            <h2 class="visually-hidden">Favourites</h2>
            @forelse($favorites as $fav)
                <div class="lib-row">
                    @include('library.partials.cover', ['book' => $fav->book, 'size' => 'sm'])
                    <div class="min-w-0 flex-grow-1">
                        <a href="{{ route('library.book', $fav->book) }}" class="r-t d-block text-truncate" @if($fav->book->language === 'ne') lang="ne" @endif>{{ $fav->book->title }}</a>
                        <div class="r-m text-truncate">{{ $fav->book->author }} · {{ $fav->book->category->name }} · {{ $fav->book->readingTimeLabel() }}</div>
                    </div>
                    <form method="post" action="{{ route('library.favorite', $fav->book) }}">
                        @csrf
                        <button class="btn btn-sm btn-ghost lib-fav" aria-pressed="true" aria-label="Remove {{ $fav->book->title }} from favourites"><i class="bi bi-heart-fill"></i></button>
                    </form>
                </div>
            @empty
                <div class="lib-empty"><span class="pk-icon rose"><i class="bi bi-heart"></i></span><h3 class="h6 fw-bold">No favourites yet</h3><p class="mb-0">Tap <i class="bi bi-heart"></i> on any title to keep it here.</p></div>
            @endforelse
        @else
            <h2 class="visually-hidden">Bookmarks</h2>
            @forelse($bookmarks as $bm)
                <div class="lib-row">
                    @include('library.partials.cover', ['book' => $bm->book, 'size' => 'sm'])
                    <div class="min-w-0 flex-grow-1">
                        <a href="{{ $bm->url() }}" class="r-t d-block text-truncate">{{ $bm->book->title }}</a>
                        <div class="r-m text-truncate">{{ $bm->placeLabel() }} · {{ $bm->note ?: 'No note' }}</div>
                    </div>
                    <form method="post" action="{{ route('library.bookmarks.destroy', $bm) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-ghost px-2" aria-label="Delete bookmark"><i class="bi bi-trash3"></i></button>
                    </form>
                </div>
            @empty
                <div class="lib-empty"><span class="pk-icon violet"><i class="bi bi-bookmark"></i></span><h3 class="h6 fw-bold">No bookmarks yet</h3><p class="mb-0">In the reader, tap <i class="bi bi-bookmark-plus"></i> to save the exact spot.</p></div>
            @endforelse
        @endif
    </section>
</div>
@endsection
