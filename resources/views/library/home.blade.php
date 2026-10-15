@extends('layouts.app')
@section('title', 'E-Library — प्रतीक्षा')

@include('library.partials._styles')

@section('content')
<header class="pk-page-head lib-head">
    <div class="container">
        <div class="row align-items-end g-4">
            <div class="col-lg-7">
                <span class="pk-eyebrow"><i class="bi bi-book-half"></i> E-Library · ई-पुस्तकालय</span>
                <h1 class="mt-2 mb-2">Read while you <span class="text-gradient">wait</span>.</h1>
                <p class="lead mb-4">Stories, comics, classics and short reads sized for a queue. Your exact page is saved to your account, so you can pick up in any queue, on any device.</p>
                <form action="{{ route('library.search') }}" method="get" role="search" class="lib-search" data-suggest-url="{{ route('library.search') }}">
                    <label for="libQ" class="visually-hidden">Search by title, author or category</label>
                    <i class="bi bi-search"></i>
                    <input id="libQ" name="q" type="search" class="form-control" placeholder="Search title, author or genre…" autocomplete="off" maxlength="80"
                           role="combobox" aria-expanded="false" aria-controls="libSuggest" aria-autocomplete="list">
                    <div id="libSuggest" class="lib-suggest" role="listbox" aria-label="Suggestions"></div>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <span class="chip"><i class="bi bi-collection"></i> {{ $totalItems }} free reads</span>
                    <span class="chip" style="background:var(--pk-tint-violet);color:var(--pk-violet)"><i class="bi bi-patch-check"></i> Public-domain &amp; original</span>
                    <a href="{{ route('library.search', ['audio' => 1]) }}" class="chip text-decoration-none" style="background:#fff;border:1px solid #d9ccff;color:var(--pk-violet)"><i class="bi bi-headphones"></i> Audiobooks</a>
                    <a href="{{ route('library.mine') }}" class="chip text-decoration-none" style="background:#fff;border:1px solid var(--pk-line);color:var(--pk-ink)"><i class="bi bi-person-lines-fill"></i> My library</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('library.admin.index') }}" class="chip text-decoration-none" style="background:var(--pk-warn-tint);color:#8a4b04"><i class="bi bi-gear"></i> Manage</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    {{-- Continue reading --}}
    <section class="mb-5 reveal" aria-label="Continue reading">
        <div class="row g-3 align-items-stretch">
            <div class="col-lg-7">
                @include('library.partials.continue-card', ['progress' => $continue])
            </div>
            <div class="col-lg-5">
                <div class="pk-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h2 class="lib-section-title" style="font-size:1rem"><i class="bi bi-clock-history"></i> Recently read</h2>
                        <a class="lib-see-all" href="{{ route('library.mine') }}">History</a>
                    </div>
                    @forelse($recent->take(3) as $r)
                        <div class="lib-row py-2">
                            @include('library.partials.cover', ['book' => $r->book, 'size' => 'sm'])
                            <div class="min-w-0 flex-grow-1">
                                <a href="{{ $r->resumeUrl() }}" class="r-t d-block text-truncate" @if($r->book->language === 'ne') lang="ne" @endif>{{ $r->book->title }}</a>
                                <div class="r-m text-truncate">{{ $r->completed ? 'Finished' : (int) $r->overall_percent.'% · '.$r->last_read_at->diffForHumans() }}</div>
                            </div>
                            @if($r->completed)<i class="bi bi-check-circle-fill text-success" aria-label="Finished"></i>@endif
                        </div>
                    @empty
                        <p class="small pk-muted mb-0 mt-2">Your reading history will appear here.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- Genres --}}
    <section class="mb-5" aria-labelledby="genresTitle">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 id="genresTitle" class="lib-section-title"><i class="bi bi-grid"></i> Browse by genre</h2>
        </div>
        <div class="lib-genres">
            @foreach($genres as $i => $g)
                @php $tone = ['', 'violet', 'green', 'amber', 'rose'][$i % 5]; @endphp
                <a href="{{ route('library.category', $g) }}" class="lib-genre reveal" style="--d: {{ min($i, 10) * .03 }}s">
                    <span class="pk-icon {{ $tone }}"><i class="bi {{ $g->icon }}"></i></span>
                    <span class="min-w-0"><span class="g-n d-block">{{ $g->name }}</span><span class="g-c" lang="ne">{{ $g->name_ne }} · {{ $g->books_count }}</span></span>
                </a>
            @endforeach
        </div>
    </section>

    @foreach([
        ['featuredTitle', 'bi-stars', 'Featured', $featured, null],
        ['popularTitle', 'bi-fire', 'Top reads', $popular, null],
        ['audioTitle', 'bi-headphones', 'Audiobooks · Listen while you wait', $audiobooks, route('library.search', ['audio' => 1])],
        ['comicsTitle', 'bi-chat-square-quote', 'Comics & manga-style', $comics, route('library.search', ['type' => 'comic'])],
        ['nepaliTitle', 'bi-translate', 'नेपाली साहित्य · In Nepali', $nepali, route('library.search', ['lang' => 'ne'])],
    ] as [$sid, $icon, $label, $items, $more])
        @if($items->isNotEmpty())
        <section class="mb-5" aria-labelledby="{{ $sid }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 id="{{ $sid }}" class="lib-section-title"><i class="bi {{ $icon }}"></i> {{ $label }}</h2>
                @if($more)<a class="lib-see-all" href="{{ $more }}">See all <i class="bi bi-arrow-right"></i></a>@endif
            </div>
            <div class="lib-shelf" tabindex="0" aria-label="{{ $label }} shelf (scrolls sideways)">
                @foreach($items as $i => $b)
                    @include('library.partials.book-card', ['book' => $b, 'delay' => min($i, 6) * .05])
                @endforeach
            </div>
        </section>
        @endif
    @endforeach

    {{-- News, blogs, articles --}}
    <section class="mb-4" aria-labelledby="newsTitle">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 id="newsTitle" class="lib-section-title"><i class="bi bi-newspaper"></i> News, blogs &amp; articles</h2>
            <div class="d-flex gap-2">
                @foreach($periodicalCats as $pc)
                    <a href="{{ route('library.category', $pc) }}" class="chip text-decoration-none">{{ $pc->name }} · {{ $pc->books_count }}</a>
                @endforeach
            </div>
        </div>
        <div class="pk-card px-3 px-md-4 py-1">
            @forelse($periodicals->take(6) as $item)
                <div class="lib-row">
                    @include('library.partials.cover', ['book' => $item, 'size' => 'sm'])
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="chip py-0 px-2" style="font-size:.68rem"><i class="bi {{ $item->typeIcon() }}"></i> {{ $item->typeLabel() }}</span>
                            @if(in_array($item->type, ['news', 'blog'], true))<span class="lib-sample">Sample content</span>@endif
                        </div>
                        <a href="{{ route('library.book', $item) }}" class="r-t d-block" @if($item->language === 'ne') lang="ne" @endif>{{ $item->title }}</a>
                        <div class="r-m text-truncate">{{ $item->author }} · {{ $item->readingTimeLabel() }}</div>
                    </div>
                    <a href="{{ route('library.read', $item) }}" class="btn btn-ghost btn-sm d-none d-sm-inline-flex">Read</a>
                </div>
            @empty
                <div class="lib-empty">No articles yet.</div>
            @endforelse
        </div>
    </section>
    <p class="lib-credit text-center mt-4 mb-0"><i class="bi bi-camera me-1"></i>Cover photos and illustrations are public domain or Creative Commons, stored locally. <a href="{{ route('library.credits') }}">Image credits</a></p>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/library.js') }}?v={{ @filemtime(public_path('js/modules/library.js')) }}"></script>
@endpush
