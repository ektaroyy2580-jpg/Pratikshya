@extends('layouts.app')
@section('title', $book->title.' — E-Library')

@include('library.partials._styles')

@section('content')
@php $credit = $book->hasImage() ? $book->image_credit : null; @endphp
<header class="pk-page-head lib-head {{ $book->hasImage() && $book->safePlaceholder() ? 'has-photo' : '' }}" @if($book->hasImage() && $book->safePlaceholder()) style="--ph: url('{{ $book->safePlaceholder() }}')" @endif>
    <div class="container">
        <nav class="d-flex flex-wrap align-items-center gap-2" aria-label="Breadcrumb">
            <a href="{{ route('library.home') }}" class="pk-breadcrumb">E-Library</a>
            <i class="bi bi-chevron-right pk-muted" style="font-size:.7rem"></i>
            <a href="{{ route('library.category', $book->category) }}" class="pk-breadcrumb">{{ $book->category->name }}</a>
        </nav>

        @unless($book->isVisible())
            <div class="alert alert-warning mt-3 mb-0"><i class="bi bi-eye-slash me-1"></i> Admin preview — this item is hidden from readers.</div>
        @endunless

        <div class="row g-4 g-lg-5 align-items-center mt-1">
            <div class="col-md-auto text-center">
                <div class="lib-detail-cover mx-auto fade-up">@include('library.partials.cover', ['book' => $book, 'size' => 'lg', 'eager' => true])</div>
                @if($credit)
                    <p class="lib-credit mx-auto mt-2 mb-0" style="max-width:250px">
                        <i class="bi bi-camera me-1"></i>Cover: {{ \App\Models\Book::creditLine($credit) }}
                        · <a href="{{ route('library.credits') }}#{{ $book->slug }}">Credits</a>
                    </p>
                @endif
            </div>
            <div class="col-md min-w-0">
                <div class="lib-meta mb-2">
                    <span class="chip"><i class="bi {{ $book->typeIcon() }}"></i> {{ $book->typeLabel() }}</span>
                    <span class="chip" style="background:var(--pk-tint-violet);color:var(--pk-violet)"><i class="bi {{ $book->category->icon }}"></i> {{ $book->category->name }}</span>
                    <span class="chip" style="background:#fff;border:1px solid var(--pk-line);color:var(--pk-ink-2)"><i class="bi bi-translate"></i> {{ \App\Models\Book::LANGUAGES[$book->language] ?? $book->language }}</span>
                    @if($book->isRtl())<span class="chip" style="background:#1c1c22;color:#fff"><i class="bi bi-arrow-left"></i> Reads right to left</span>@endif
                    @if(in_array($book->type, ['news', 'blog'], true))<span class="lib-sample align-self-center">Sample content</span>@endif
                </div>
                <h1 class="mb-1" @if($book->language === 'ne') lang="ne" @endif>{{ $book->title }}</h1>
                <p class="fs-6 fw-semibold pk-muted mb-3">by {{ $book->author }}</p>
                @if($book->description)
                    <p class="mb-3" style="max-width:44rem;color:var(--pk-ink-2)" @if($book->language === 'ne') lang="ne" @endif>{{ $book->description }}</p>
                @endif

                <div class="d-flex flex-wrap gap-3 small fw-bold mb-3" style="color:var(--pk-ink-2)">
                    <span><i class="bi bi-clock me-1 pk-muted"></i>{{ $book->readingTimeLabel() }}</span>
                    @if($book->isAudiobook())
                        <span style="color:var(--pk-violet)"><i class="bi bi-headphones me-1"></i>Audiobook · {{ $book->listeningTimeLabel() }}</span>
                    @else
                        <span style="color:var(--pk-violet)"><i class="bi bi-megaphone me-1"></i>Read-aloud bubbles</span>
                    @endif
                    <span><i class="bi bi-list-ol me-1 pk-muted"></i>{{ $book->chapters->count() }} {{ \Illuminate\Support\Str::plural($book->isComic() ? 'chapter' : 'part', $book->chapters->count()) }}</span>
                    @if($book->isComic())
                        <span><i class="bi bi-grid-3x2 me-1 pk-muted"></i>{{ $book->chapters->sum(fn ($c) => $c->pageCount()) }} pages</span>
                    @else
                        <span><i class="bi bi-fonts me-1 pk-muted"></i>{{ number_format($book->word_count) }} words</span>
                    @endif
                </div>

                @if($progress && ! $progress->completed && $progress->chapter)
                    <div class="pk-card p-3 mb-3" style="max-width:34rem">
                        <div class="small fw-bold" style="color:var(--pk-violet)"><i class="bi {{ $progress->isListening() ? 'bi-headphones' : 'bi-bookmark-star-fill' }} me-1"></i>{{ $progress->isListening() ? 'Continue listening where you left off' : 'Continue reading where you left off' }}</div>
                        <div class="fw-semibold small mt-1">{{ $progress->placeLabel() }}</div>
                        <div class="progress mt-2" style="height:6px" role="progressbar" aria-label="Overall progress" aria-valuenow="{{ (int) $progress->overall_percent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:{{ (int) $progress->overall_percent }}%"></div></div>
                    </div>
                @elseif($progress && $progress->completed)
                    <p class="small fw-bold text-success mb-3"><i class="bi bi-check-circle-fill me-1"></i>You finished this {{ $progress->completed_at?->diffForHumans() }}.</p>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    @if($progress && ! $progress->completed && $progress->chapter)
                        <a href="{{ $progress->resumeUrl() }}" class="btn btn-pk"><i class="bi bi-play-fill me-1"></i>Resume reading</a>
                        <a href="{{ route('library.read', ['book' => $book, 'chapter' => 1]) }}" class="btn btn-ghost">Start over</a>
                    @else
                        <a href="{{ route('library.read', ['book' => $book, 'chapter' => 1]) }}" class="btn btn-pk"><i class="bi bi-book-half me-1"></i>{{ $progress?->completed ? 'Read again' : ($book->isComic() ? 'Start reading' : 'Start reading') }}</a>
                    @endif
                    @php $listenChapter = ($progress && ! $progress->completed && $progress->chapter) ? $progress->chapter->position : 1; @endphp
                    <a href="{{ route('library.read', ['book' => $book, 'chapter' => $listenChapter, 'listen' => 1]) }}" class="btn btn-ghost lib-listen-btn">
                        <i class="bi bi-headphones me-1"></i>{{ $progress && $progress->isListening() && ! $progress->completed ? 'Continue listening' : ($book->isComic() ? 'Read aloud' : 'Listen') }}
                    </a>
                    @if($book->isVisible())
                        <form method="post" action="{{ route('library.favorite', $book) }}" data-fav>
                            @csrf
                            <button type="submit" class="btn btn-ghost lib-fav" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
                                <i class="bi {{ $isFavorite ? 'bi-heart-fill' : 'bi-heart' }} me-1"></i><span>{{ $isFavorite ? 'Favourited' : 'Favourite' }}</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="tocTitle">
                <h2 id="tocTitle" class="lib-section-title mb-2"><i class="bi bi-list-ol"></i> {{ $book->isComic() ? 'Chapters' : 'Contents' }}</h2>
                <ol class="lib-chapters">
                    @foreach($book->chapters as $ch)
                        @php $here = $progress && (int) $progress->book_chapter_id === (int) $ch->id && ! $progress->completed; @endphp
                        <li class="{{ $here ? 'here' : '' }}">
                            <a href="{{ route('library.read', ['book' => $book, 'chapter' => $ch->position]) }}" @if($here) aria-current="step" @endif>
                                <span class="n">{{ $ch->position }}</span>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="d-block fw-bold text-truncate" @if($book->language === 'ne') lang="ne" @endif>{{ $ch->title }}</span>
                                    <span class="small pk-muted fw-semibold">
                                        @if($ch->isComic()) {{ $ch->pageCount() }} pages @else {{ max(1, (int) ceil($ch->word_count / \App\Models\Book::WPM)) }} min @endif
                                        @if($here) · <span style="color:var(--pk-violet)">You are here</span>@endif
                                    </span>
                                </span>
                                <i class="bi bi-chevron-right pk-muted"></i>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="pk-card p-3 p-md-4 mb-4 reveal" style="--d:.05s" aria-labelledby="bmTitle">
                <h2 id="bmTitle" class="lib-section-title mb-2"><i class="bi bi-bookmark"></i> Your bookmarks</h2>
                @forelse($bookmarks as $bm)
                    <div class="lib-row">
                        <span class="pk-icon violet" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-bookmark-fill"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <a href="{{ $bm->url() }}" class="r-t d-block">{{ $bm->placeLabel() }}</a>
                            <div class="r-m text-truncate">{{ $bm->note ?: 'No note' }} · {{ $bm->created_at->diffForHumans() }}</div>
                        </div>
                        <form method="post" action="{{ route('library.bookmarks.destroy', $bm) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-ghost px-2" aria-label="Delete bookmark {{ $bm->placeLabel() }}"><i class="bi bi-trash3"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="small pk-muted mb-0">No bookmarks yet. Tap <i class="bi bi-bookmark-plus"></i> in the reader to save a spot.</p>
                @endforelse
            </section>
            <div class="lib-license reveal" style="--d:.1s"><i class="bi bi-patch-check-fill" style="color:var(--pk-success)"></i><div><strong>Licence &amp; source.</strong> {{ $book->license }}</div></div>
        </div>
    </div>

    @if($related->isNotEmpty())
        <section class="mt-5" aria-labelledby="relTitle">
            <h2 id="relTitle" class="lib-section-title mb-3"><i class="bi bi-collection"></i> More in {{ $book->category->name }}</h2>
            <div class="lib-grid">
                @foreach($related as $i => $r)
                    @include('library.partials.book-card', ['book' => $r, 'delay' => $i * .05])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/library.js') }}?v={{ @filemtime(public_path('js/modules/library.js')) }}"></script>
@endpush
