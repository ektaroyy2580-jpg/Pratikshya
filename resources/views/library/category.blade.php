@extends('layouts.app')
@section('title', $category->name.' — E-Library')

@include('library.partials._styles')

@section('content')
<header class="pk-page-head lib-head">
    <div class="container">
        <a href="{{ route('library.home') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>E-Library</a>
        <div class="d-flex align-items-center gap-3 mt-3">
            <span class="pk-icon violet" style="width:56px;height:56px;font-size:1.5rem"><i class="bi {{ $category->icon }}"></i></span>
            <div>
                <h1 class="mb-0">{{ $category->name }} <span class="pk-muted fw-semibold fs-5" lang="ne">{{ $category->name_ne }}</span></h1>
                <p class="pk-muted mb-0 fw-semibold">{{ $category->description }} · {{ $books->total() }} {{ \Illuminate\Support\Str::plural('item', $books->total()) }}</p>
            </div>
        </div>
    </div>
</header>

<div class="container py-4 py-md-5">
    @if($category->kind === 'periodical')
        <div class="alert alert-warning d-flex gap-2 align-items-start"><i class="bi bi-info-circle-fill mt-1"></i><div>News and blog items here are <strong>fictional sample content</strong> written for this demo — not real reporting.</div></div>
    @endif

    @if($books->isEmpty())
        <div class="pk-card lib-empty">
            <span class="pk-icon"><i class="bi bi-inbox"></i></span>
            <h2 class="h5">Nothing here yet</h2>
            <p class="mb-3">New titles in {{ $category->name }} will appear here.</p>
            <a href="{{ route('library.home') }}" class="btn btn-ghost">Back to the library</a>
        </div>
    @else
        <div class="lib-grid">
            @foreach($books as $i => $b)
                @include('library.partials.book-card', ['book' => $b, 'delay' => min($i, 8) * .04])
            @endforeach
        </div>
        <div class="mt-4">{{ $books->links('pagination::bootstrap-5') }}</div>
    @endif

    <h2 class="lib-section-title mt-5 mb-3"><i class="bi bi-grid"></i> Other genres</h2>
    <div class="d-flex flex-wrap gap-2">
        @foreach($others as $o)
            <a href="{{ route('library.category', $o) }}" class="chip text-decoration-none"><i class="bi {{ $o->icon }}"></i> {{ $o->name }}</a>
        @endforeach
    </div>
</div>
@endsection
