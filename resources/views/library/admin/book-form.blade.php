@extends('layouts.app')
@section('title', ($book->exists ? 'Edit '.$book->title : 'New item').' — Admin')

@include('library.partials._styles')

@section('content')
<header class="pk-page-head lib-head">
    <div class="container">
        <a href="{{ route('library.admin.index') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Manage the library</a>
        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <h1 class="mb-0">{{ $book->exists ? 'Edit item' : 'New item' }}</h1>
            @if($book->exists)
                @if($book->is_active)<span class="pk-status called">Live</span>@else<span class="pk-status done">Hidden</span>@endif
                <form method="post" action="{{ route('library.admin.books.toggle', $book) }}">@csrf<button class="btn btn-sm btn-ghost">{{ $book->is_active ? 'Disable' : 'Enable' }}</button></form>
                <a href="{{ route('library.book', $book) }}" class="btn btn-sm btn-ghost"><i class="bi bi-eye me-1"></i>Preview</a>
            @endif
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    @if($errors->any() && ! $errors->has('queue'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>Please fix the highlighted fields.</div>
    @endif
    <div class="row g-4">
        <div class="col-lg-{{ $book->exists ? '5' : '8' }}">
            <form method="post" action="{{ $book->exists ? route('library.admin.books.update', $book) : route('library.admin.books.store') }}" class="pk-card p-3 p-md-4 d-grid gap-3">
                @csrf
                @if($book->exists) @method('PUT') @endif
                <h2 class="lib-section-title"><i class="bi bi-info-circle"></i> Details</h2>
                @php
                    $field = fn ($n, $d = null) => old($n, $d);
                    $err = fn ($n) => $errors->has($n) ? 'is-invalid' : '';
                @endphp
                <div><label class="form-label" for="bTitle">Title</label><input id="bTitle" name="title" class="form-control {{ $err('title') }}" required maxlength="160" value="{{ $field('title', $book->title) }}">@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div><label class="form-label" for="bSlug">URL slug</label><input id="bSlug" name="slug" class="form-control {{ $err('slug') }}" required maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ $field('slug', $book->slug) }}">@error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div><label class="form-label" for="bAuthor">Author</label><input id="bAuthor" name="author" class="form-control {{ $err('author') }}" required maxlength="160" value="{{ $field('author', $book->author) }}">@error('author')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label" for="bCat">Category</label>
                        <select id="bCat" name="book_category_id" class="form-select {{ $err('book_category_id') }}" required>
                            @foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) $field('book_category_id', $book->book_category_id) === $c->id)>{{ $c->name }}{{ $c->is_active ? '' : ' (hidden)' }}</option>@endforeach
                        </select></div>
                    <div class="col-sm-3"><label class="form-label" for="bType">Type</label>
                        <select id="bType" name="type" class="form-select {{ $err('type') }}">
                            @foreach($book->isComic() ? ['comic'] : array_diff(\App\Models\Book::TYPES, ['comic']) as $t)<option value="{{ $t }}" @selected($field('type', $book->type) === $t)>{{ ucfirst($t) }}</option>@endforeach
                        </select></div>
                    <div class="col-sm-3"><label class="form-label" for="bLang">Language</label>
                        <select id="bLang" name="language" class="form-select">
                            @foreach(\App\Models\Book::LANGUAGES as $code => $name)<option value="{{ $code }}" @selected($field('language', $book->language) === $code)>{{ $name }}</option>@endforeach
                        </select></div>
                </div>
                <div><label class="form-label" for="bDesc">Description</label><textarea id="bDesc" name="description" class="form-control" rows="3" maxlength="1000">{{ $field('description', $book->description) }}</textarea></div>
                <div><label class="form-label" for="bLic">Licence / source line</label><input id="bLic" name="license" class="form-control {{ $err('license') }}" required maxlength="255" placeholder="e.g. Public domain (author, year) or Original work © …" value="{{ $field('license', $book->license) }}">@error('license')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Only public-domain, original or properly licensed content.</div></div>
                <div class="row g-2 align-items-end">
                    <div class="col-5"><label class="form-label" for="bPal">Cover colour</label>
                        <select id="bPal" name="palette" class="form-select">@foreach(array_keys(\App\Services\Library\CoverArt::PALETTES) as $p)<option value="{{ $p }}" @selected($field('palette', $book->cover['palette'] ?? 'indigo') === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
                    <div class="col-5"><label class="form-label" for="bMot">Cover motif</label>
                        <select id="bMot" name="motif" class="form-select">@foreach(\App\Services\Library\CoverArt::MOTIFS as $m)<option value="{{ $m }}" @selected($field('motif', $book->cover['motif'] ?? 'book') === $m)>{{ ucfirst($m) }}</option>@endforeach</select></div>
                    @if($book->exists)<div class="col-2">@include('library.partials.cover', ['book' => $book, 'size' => 'sm'])</div>@endif
                </div>
                <div class="form-check"><input type="hidden" name="is_featured" value="0"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="bFeat" @checked($field('is_featured', $book->is_featured))><label class="form-check-label" for="bFeat">Featured on the library home</label></div>
                <button class="btn btn-pk">{{ $book->exists ? 'Save details' : 'Create item' }}</button>
            </form>
        </div>

        @if($book->exists)
        <div class="col-lg-7">
            <section class="pk-card p-3 p-md-4 mb-4">
                <h2 class="lib-section-title mb-3"><i class="bi bi-list-ol"></i> Chapters <span class="pk-muted fw-semibold">({{ $book->chapters->count() }} · {{ $book->readingTimeLabel() }})</span></h2>
                @if($book->isComic())
                    <p class="small pk-muted">Comic pages are drawn from seeded panel data and can’t be edited here.</p>
                @endif
                @foreach($book->chapters as $ch)
                    <details class="border rounded-3 mb-2 px-3 py-2" style="border-color:var(--pk-line)!important">
                        <summary class="fw-bold" style="cursor:pointer">{{ $ch->position }}. {{ $ch->title }} <span class="small pk-muted fw-semibold">· {{ $ch->isComic() ? $ch->pageCount().' pages' : $ch->word_count.' words' }}</span></summary>
                        @unless($ch->isComic())
                        <form method="post" action="{{ route('library.admin.chapters.update', [$book, $ch]) }}" class="d-grid gap-2 mt-2">
                            @csrf @method('PUT')
                            <label class="form-label small mb-0" for="ct{{ $ch->id }}">Title</label>
                            <input id="ct{{ $ch->id }}" name="title" class="form-control form-control-sm" value="{{ $ch->title }}" required maxlength="160">
                            <label class="form-label small mb-0" for="cb{{ $ch->id }}">Text</label>
                            <textarea id="cb{{ $ch->id }}" name="body" class="form-control form-control-sm" rows="10" required maxlength="60000" @if($book->language === 'ne') lang="ne" @endif>{{ $ch->body }}</textarea>
                            <div class="d-flex justify-content-between align-items-center"><span class="form-text">Blank line = new paragraph · “## ” heading · “> ” quote</span><button class="btn btn-sm btn-pk">Save chapter</button></div>
                        </form>
                        @endunless
                    </details>
                @endforeach
            </section>
            @unless($book->isComic())
            <form method="post" action="{{ route('library.admin.chapters.store', $book) }}" class="pk-card p-3 p-md-4 d-grid gap-2">
                @csrf
                <h2 class="lib-section-title mb-1"><i class="bi bi-plus-circle"></i> Add a chapter</h2>
                <label class="form-label small mb-0" for="ncT">Title</label>
                <input id="ncT" name="title" class="form-control" required maxlength="160" value="{{ old('title') }}">
                <label class="form-label small mb-0" for="ncB">Text</label>
                <textarea id="ncB" name="body" class="form-control" rows="8" required maxlength="60000">{{ old('body') }}</textarea>
                <div class="form-text">Plain text only — it is always displayed escaped. Blank line = new paragraph; “## ” starts a heading; “> ” a quote.</div>
                <button class="btn btn-pk">Add chapter</button>
            </form>
            @endunless
        </div>
        @endif
    </div>
</div>
@endsection
