@extends('layouts.app')
@section('title', $chapter->title.' · '.$book->title.' — E-Library')

@include('library.partials._styles')
@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/library-reader.css') }}?v={{ @filemtime(public_path('css/modules/library-reader.css')) }}">
<script>
    // Apply saved reader preferences before first paint (per-device conveniences only).
    (() => { try {
        const d = document.documentElement;
        d.dataset.rdTheme = localStorage.getItem('lib.theme') || 'light';
        d.dataset.rdFont = localStorage.getItem('lib.font') || 'serif';
        d.style.setProperty('--rd-size', (localStorage.getItem('lib.size') || '19') + 'px');
    } catch (e) {} })();
</script>
@endpush

@php
    $total = $book->chapters->count();
    $isComic = $chapter->isComic();
    $rtl = $book->isRtl();
    $pages = $isComic ? array_values($chapter->pages ?? []) : [];
@endphp

@section('content')
<div class="rd" id="reader"
     data-save-url="{{ route('library.progress', $book) }}"
     data-bookmark-url="{{ route('library.bookmarks.store', $book) }}"
     data-chapter-id="{{ $chapter->id }}"
     data-chapter-index="{{ $book->chapters->search(fn ($c) => $c->id === $chapter->id) }}"
     data-chapter-count="{{ $total }}"
     data-kind="{{ $isComic ? 'comic' : 'text' }}"
     data-rtl="{{ $rtl ? '1' : '0' }}"
     data-start-position="{{ $startPosition }}"
     data-start-page="{{ $startPage }}"
     data-lang="{{ $book->language }}"
     data-chapter-heading="Chapter {{ $chapter->position }}. {{ $chapter->title }}."
     data-listen-start="{{ $listenStart ?? '' }}"
     data-open-listen="{{ $openListen ? '1' : '0' }}"
     data-prev-url="{{ $prev ? route('library.read', ['book' => $book, 'chapter' => $prev->position, 'go' => 1]) : '' }}"
     data-next-url="{{ $next ? route('library.read', ['book' => $book, 'chapter' => $next->position, 'go' => 1]) : '' }}"
     @if($ticket) data-ticket-url="{{ route('ticket.state', $ticket) }}" @endif>

    {{-- Toolbar --}}
    <div class="rd-bar" role="toolbar" aria-label="Reader controls">
        <div class="rd-bar-in">
            <a href="{{ route('library.book', $book) }}" class="rd-btn" aria-label="Back to {{ $book->title }}"><i class="bi bi-arrow-left"></i></a>
            <div class="rd-title min-w-0">
                <div class="t text-truncate" @if($book->language === 'ne') lang="ne" @endif>{{ $book->title }}</div>
                <div class="s text-truncate">Chapter {{ $chapter->position }} of {{ $total }}<span class="d-none d-sm-inline"> · {{ $chapter->title }}</span></div>
            </div>
            @if($ticket)
                <a href="{{ route('ticket', $ticket) }}" class="rd-ticket" id="rdTicket" aria-live="polite" title="Your place in line is unaffected by reading">
                    <span class="live-dot"></span><span class="tok">{{ $ticket->token() }}</span><span class="st d-none d-md-inline" id="rdTicketState">in line</span>
                </a>
            @endif
            <button type="button" class="rd-btn rd-listen-btn" id="rdListenBtn" aria-pressed="false" aria-controls="lsBar" title="{{ $isComic ? 'Read the speech bubbles aloud' : 'Listen — audiobook mode' }}">
                <i class="bi bi-headphones"></i><span class="d-none d-md-inline ms-1">{{ $isComic ? 'Read aloud' : 'Listen' }}</span>
            </button>
            <span class="rd-save" id="rdSave" role="status" aria-live="polite"><i class="bi bi-cloud-check"></i><span class="d-none d-md-inline"> Saved</span></span>
            <button type="button" class="rd-btn" data-bs-toggle="offcanvas" data-bs-target="#rdToc" aria-controls="rdToc" aria-label="Chapters and bookmarks"><i class="bi bi-list-ul"></i></button>
            <div class="position-relative">
                <button type="button" class="rd-btn" id="rdBmBtn" aria-expanded="false" aria-controls="rdBmPanel" aria-label="Add bookmark here"><i class="bi bi-bookmark-plus"></i></button>
                <div class="rd-pop" id="rdBmPanel" hidden>
                    <form id="rdBmForm">
                        <label for="rdBmNote" class="form-label small mb-1">Bookmark this spot</label>
                        <input id="rdBmNote" class="form-control form-control-sm mb-2" maxlength="280" placeholder="Optional note…">
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-sm btn-ghost" data-close>Cancel</button>
                            <button class="btn btn-sm btn-pk">Save</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="position-relative">
                <button type="button" class="rd-btn" id="rdSetBtn" aria-expanded="false" aria-controls="rdSetPanel" aria-label="Reading settings"><span class="fw-bold" style="font-size:.95rem">Aa</span></button>
                <div class="rd-pop rd-settings" id="rdSetPanel" hidden>
                    @unless($isComic)
                        <div class="rd-set-row">
                            <span class="lbl">Text size</span>
                            <div class="rd-seg">
                                <button type="button" data-size="-1" aria-label="Smaller text"><i class="bi bi-dash-lg"></i></button>
                                <output id="rdSizeOut" aria-live="polite">19</output>
                                <button type="button" data-size="1" aria-label="Larger text"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </div>
                        <div class="rd-set-row">
                            <span class="lbl">Font</span>
                            <div class="rd-seg" role="radiogroup" aria-label="Font">
                                <button type="button" role="radio" data-font="serif" style="font-family:Georgia,serif">Serif</button>
                                <button type="button" role="radio" data-font="sans">Sans</button>
                            </div>
                        </div>
                    @endunless
                    <div class="rd-set-row">
                        <span class="lbl">Theme</span>
                        <div class="rd-themes" role="radiogroup" aria-label="Theme">
                            <button type="button" role="radio" data-theme="light" class="th-light" aria-label="Light"><span>Aa</span></button>
                            <button type="button" role="radio" data-theme="sepia" class="th-sepia" aria-label="Sepia"><span>Aa</span></button>
                            <button type="button" role="radio" data-theme="dark" class="th-dark" aria-label="Dark"><span>Aa</span></button>
                        </div>
                    </div>
                    <p class="small mb-0 rd-muted"><i class="bi bi-keyboard me-1"></i>{{ $isComic ? ($rtl ? '← next page · → previous' : '→ next page · ← previous') : '→ next chapter · ← previous' }}</p>
                </div>
            </div>
        </div>
        <div class="rd-progress" aria-hidden="true"><span id="rdBar" style="width: {{ $isComic ? (($startPage + 1) / max(1, count($pages)) * 100) : $startPosition }}%"></span></div>
    </div>

    <div class="rd-called alert alert-success shadow" id="rdCalled" role="alert" hidden>
        <i class="bi bi-megaphone-fill me-2"></i><strong>It’s your turn!</strong> <span id="rdCalledMsg">Please go to the counter.</span>
        <a href="{{ $ticket ? route('ticket', $ticket) : '#' }}" class="alert-link ms-1">Open ticket</a>
    </div>

    {{-- Content --}}
    @if($isComic)
        <main class="rd-comic" id="rdMain" aria-label="{{ $chapter->title }}" @if($book->language === 'ne') lang="ne" @endif>
            <div class="rd-comic-head">
                <div class="rd-eyebrow">Chapter {{ $chapter->position }}{{ $rtl ? ' · Read right to left' : '' }}</div>
                <h1 class="rd-h1">{{ $chapter->title }}</h1>
            </div>
            <div class="cm-stage" id="cmStage" tabindex="0" aria-roledescription="comic pages" aria-label="Comic pages. Use arrow keys or swipe to turn pages.">
                @foreach($pages as $i => $page)
                    @include('library.partials.comic-page', ['page' => $page, 'i' => $i, 'total' => count($pages), 'chapter' => $chapter, 'art' => $art, 'rtl' => $rtl, 'active' => $i === $startPage])
                @endforeach
            </div>
            <nav class="cm-nav {{ $rtl ? 'rtl' : '' }}" aria-label="Page navigation">
                <button type="button" class="btn btn-ghost" id="cmPrev"><i class="bi {{ $rtl ? 'bi-arrow-right' : 'bi-arrow-left' }}"></i><span class="d-none d-sm-inline ms-1">Previous</span></button>
                <div class="cm-dots" role="group" aria-label="Pages">
                    @foreach($pages as $i => $page)
                        <button type="button" data-goto="{{ $i }}" aria-label="Go to page {{ $i + 1 }}" @if($i === $startPage) aria-current="true" @endif></button>
                    @endforeach
                    <span class="cm-count" id="cmCount">Page {{ $startPage + 1 }} of {{ count($pages) }}</span>
                </div>
                <button type="button" class="btn btn-pk" id="cmNext"><span class="d-none d-sm-inline me-1" id="cmNextLbl">Next</span><i class="bi {{ $rtl ? 'bi-arrow-left' : 'bi-arrow-right' }}"></i></button>
            </nav>
            <p class="text-center small rd-muted mt-2 d-md-none"><i class="bi bi-hand-index me-1"></i>Swipe {{ $rtl ? 'right' : 'left' }} for the next page</p>
        </main>
    @else
        <main class="rd-text" id="rdMain" @if($book->language === 'ne') lang="ne" @endif>
            <article id="rdArticle">
                <div class="rd-eyebrow">Chapter {{ $chapter->position }} of {{ $total }}</div>
                <h1 class="rd-h1">{{ $chapter->title }}</h1>
                @if($chapter->position === 1)
                    <p class="rd-byline">{{ $book->author }} · {{ $book->readingTimeLabel() }}@if($book->isAudiobook()) · <i class="bi bi-headphones"></i> {{ $book->listeningTimeLabel() }}@endif</p>
                    @if($book->hasImage())
                        <figure class="rd-fig rd-hero">
                            @include('library.partials.photo', ['src' => $book->imageUrl(), 'alt' => (string) $book->image_alt, 'ph' => $book->safePlaceholder(), 'class' => 'rd-fig-img', 'eager' => true])
                            @if($book->image_credit)<figcaption><span class="rd-credit">{{ \App\Models\Book::creditLine($book->image_credit) }}</span></figcaption>@endif
                        </figure>
                    @endif
                @endif
                @php
                    // Every sentence is its own span (escaped with e()) so Listen mode can highlight and resume it exactly.
                    $spans = fn (array $line) => collect($line)->map(fn ($x) => '<span class="rd-s" data-s="'.(int) $x[0].'">'.e($x[1]).'</span>')->implode(' ');
                    $illus = $chapter->illustrationsByBlock();
                @endphp
                @foreach($chapter->segments() as $bi => $block)
                    @if($block['type'] === 'h')
                        <h2 class="rd-h2">{!! collect($block['lines'])->map($spans)->implode(' ') !!}</h2>
                    @elseif($block['type'] === 'quote')
                        <blockquote class="rd-quote">{!! collect($block['lines'])->map($spans)->implode('<br>') !!}</blockquote>
                    @else
                        <p>{!! collect($block['lines'])->map($spans)->implode('<br>') !!}</p>
                    @endif
                    @foreach($illus[$bi] ?? [] as $ill)
                        @include('library.partials.figure', ['ill' => $ill])
                    @endforeach
                @endforeach
            </article>
        </main>
    @endif

    {{-- End of chapter --}}
    <section class="rd-end" aria-label="Chapter navigation">
        @if($next)
            <a href="{{ route('library.read', ['book' => $book, 'chapter' => $next->position, 'go' => 1]) }}" class="rd-next">
                <span class="k">Next chapter</span>
                <span class="t" @if($book->language === 'ne') lang="ne" @endif>{{ $next->position }}. {{ $next->title }}</span>
                <i class="bi bi-arrow-right-circle-fill"></i>
            </a>
        @else
            <div class="rd-finish" id="rdFinish">
                <span class="pk-icon green"><i class="bi bi-trophy"></i></span>
                <div>
                    <div class="fw-bold">That’s the end of <span @if($book->language === 'ne') lang="ne" @endif>{{ $book->title }}</span>.</div>
                    <div class="small rd-muted">Finished items move to your history. <span class="d-none d-sm-inline">{{ $book->license }}</span></div>
                </div>
                <a href="{{ route('library.home') }}" class="btn btn-pk btn-sm ms-auto">Find the next read</a>
            </div>
        @endif
        <div class="d-flex justify-content-between mt-3 small">
            @if($prev)
                <a href="{{ route('library.read', ['book' => $book, 'chapter' => $prev->position]) }}" class="rd-link"><i class="bi bi-arrow-left me-1"></i>{{ $prev->title }}</a>
            @else
                <a href="{{ route('library.book', $book) }}" class="rd-link"><i class="bi bi-info-circle me-1"></i>About this {{ strtolower($book->typeLabel()) }}</a>
            @endif
            <a href="{{ route('library.mine') }}" class="rd-link">My library<i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </section>

    {{-- Chapters + bookmarks --}}
    <div class="offcanvas offcanvas-end rd-toc" tabindex="-1" id="rdToc" aria-labelledby="rdTocTitle">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title h5" id="rdTocTitle">Contents</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <ol class="lib-chapters mb-4">
                @foreach($book->chapters as $ch)
                    <li class="{{ $ch->id === $chapter->id ? 'here' : '' }}">
                        <a href="{{ route('library.read', ['book' => $book, 'chapter' => $ch->position]) }}" @if($ch->id === $chapter->id) aria-current="page" @endif>
                            <span class="n">{{ $ch->position }}</span>
                            <span class="flex-grow-1 min-w-0 fw-bold text-truncate" @if($book->language === 'ne') lang="ne" @endif>{{ $ch->title }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <h3 class="h6 fw-bold mb-2"><i class="bi bi-bookmark me-1"></i>Bookmarks</h3>
            <div id="rdBmList">
                @foreach($bookmarks as $bm)
                    <div class="rd-bm" data-id="{{ $bm->id }}">
                        <a href="{{ $bm->url() }}" class="min-w-0 flex-grow-1 text-decoration-none">
                            <span class="d-block fw-bold small">{{ $bm->placeLabel() }}</span>
                            <span class="d-block small rd-muted text-truncate">{{ $bm->note ?: 'No note' }}</span>
                        </a>
                        <button type="button" class="btn btn-sm px-2 rd-bm-del" data-url="{{ route('library.bookmarks.destroy', $bm) }}" aria-label="Delete bookmark {{ $bm->placeLabel() }}"><i class="bi bi-trash3"></i></button>
                    </div>
                @endforeach
            </div>
            <p class="small rd-muted mb-0 {{ $bookmarks->isEmpty() ? '' : 'd-none' }}" id="rdBmEmpty">No bookmarks in this {{ strtolower($book->typeLabel()) }} yet.</p>
        </div>
    </div>

    @include('library.partials.listen-bar', ['book' => $book, 'chapter' => $chapter, 'isComic' => $isComic])

    <div class="rd-toast" id="rdToast" role="status" aria-live="polite" hidden></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/library-reader.js') }}?v={{ @filemtime(public_path('js/modules/library-reader.js')) }}"></script>
<script src="{{ asset('js/modules/library-listen.js') }}?v={{ @filemtime(public_path('js/modules/library-listen.js')) }}"></script>
@endpush
