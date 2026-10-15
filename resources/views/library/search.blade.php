@extends('layouts.app')
@section('title', ($q !== '' ? '“'.$q.'” — ' : '').'Search the E-Library')

@include('library.partials._styles')

@section('content')
<header class="pk-page-head lib-head">
    <div class="container">
        <a href="{{ route('library.home') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>E-Library</a>
        <h1 class="mt-3 mb-3">{{ $q !== '' ? 'Results for “'.$q.'”' : ($audio ? 'Audiobooks' : 'Search the library') }}</h1>
        <form action="{{ route('library.search') }}" method="get" role="search" class="d-flex flex-wrap gap-2 align-items-center" data-suggest-url="{{ route('library.search') }}">
            <div class="lib-search flex-grow-1" style="min-width:240px">
                <label for="libQ" class="visually-hidden">Search by title, author or category</label>
                <i class="bi bi-search"></i>
                <input id="libQ" name="q" type="search" value="{{ $q }}" class="form-control" placeholder="Title, author or genre…" maxlength="80" autocomplete="off"
                       role="combobox" aria-expanded="false" aria-controls="libSuggest" aria-autocomplete="list">
                <div id="libSuggest" class="lib-suggest" role="listbox" aria-label="Suggestions"></div>
            </div>
            <label for="libType" class="visually-hidden">Type</label>
            <select id="libType" name="type" class="form-select w-auto">
                <option value="">All types</option>
                @foreach(\App\Models\Book::TYPES as $t)
                    <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            <label for="libLang" class="visually-hidden">Language</label>
            <select id="libLang" name="lang" class="form-select w-auto">
                <option value="">Any language</option>
                @foreach(\App\Models\Book::LANGUAGES as $code => $name)
                    <option value="{{ $code }}" @selected($lang === $code)>{{ $name }}</option>
                @endforeach
            </select>
            <label class="lib-filter-audio" for="libAudio"><input id="libAudio" type="checkbox" name="audio" value="1" @checked($audio)><i class="bi bi-headphones"></i> Audiobooks</label>
            <button class="btn btn-pk"><i class="bi bi-search me-1"></i>Search</button>
        </form>
        @if($errors->any())
            <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
        @endif
    </div>
</header>

<div class="container py-4 py-md-5">
    <p class="pk-muted fw-semibold" aria-live="polite">{{ $results->total() }} {{ \Illuminate\Support\Str::plural('result', $results->total()) }}</p>
    @if($results->isEmpty())
        <div class="pk-card lib-empty">
            <span class="pk-icon amber"><i class="bi bi-search"></i></span>
            <h2 class="h5">No matches</h2>
            <p class="mb-3">Try a shorter word, an author such as “Doyle”, or a genre like “mystery” or “नेपाली”.</p>
            <a href="{{ route('library.home') }}" class="btn btn-ghost">Browse genres</a>
        </div>
    @else
        <div class="lib-grid">
            @foreach($results as $i => $b)
                @include('library.partials.book-card', ['book' => $b, 'delay' => min($i, 8) * .04])
            @endforeach
        </div>
        <div class="mt-4">{{ $results->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/library.js') }}?v={{ @filemtime(public_path('js/modules/library.js')) }}"></script>
@endpush
