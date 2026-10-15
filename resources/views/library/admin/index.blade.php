@extends('layouts.app')
@section('title', 'Manage E-Library — Admin')

@include('library.partials._styles')

@section('content')
<header class="pk-page-head lib-head">
    <div class="container d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div>
            <a href="{{ route('library.home') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>E-Library</a>
            <h1 class="mt-3 mb-1">Manage the library</h1>
            <p class="pk-muted fw-semibold mb-0">Categories and items. Nothing is deleted — disabling hides an item but keeps everyone’s saved progress.</p>
        </div>
        <a href="{{ route('library.admin.books.create') }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i>New item</a>
    </div>
</header>

<div class="container py-4 py-md-5">
    @if($errors->any() && ! $errors->has('queue'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>{{ $errors->first() }}</div>
    @endif

    <section class="pk-card p-3 p-md-4 mb-4" aria-labelledby="itemsTitle">
        <h2 id="itemsTitle" class="lib-section-title mb-3"><i class="bi bi-collection"></i> Items <span class="pk-muted fw-semibold">({{ $books->count() }})</span></h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Item</th><th>Category</th><th class="d-none d-md-table-cell">Type</th><th class="d-none d-md-table-cell text-end">Parts</th><th class="d-none d-lg-table-cell text-end">Readers</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach($books as $b)
                    <tr>
                        <td class="min-w-0">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:34px;flex-shrink:0">@include('library.partials.cover', ['book' => $b, 'size' => 'sm'])</div>
                                <div class="min-w-0">
                                    <a href="{{ route('library.book', $b) }}" class="fw-bold text-decoration-none d-block text-truncate" style="max-width:260px" @if($b->language === 'ne') lang="ne" @endif>{{ $b->title }}</a>
                                    <span class="small pk-muted text-truncate d-block" style="max-width:260px">{{ $b->author }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="small fw-semibold">{{ $b->category->name }}</td>
                        <td class="d-none d-md-table-cell small">{{ $b->typeLabel() }}</td>
                        <td class="d-none d-md-table-cell text-end small">{{ $b->chapters_count }}</td>
                        <td class="d-none d-lg-table-cell text-end small">{{ $b->readers_count }}</td>
                        <td>@if($b->is_active)<span class="pk-status called">Live</span>@else<span class="pk-status done">Hidden</span>@endif</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('library.admin.books.edit', $b) }}" class="btn btn-sm btn-ghost px-2" aria-label="Edit {{ $b->title }}"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="{{ route('library.admin.books.toggle', $b) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-ghost px-2" aria-label="{{ $b->is_active ? 'Disable' : 'Enable' }} {{ $b->title }}" title="{{ $b->is_active ? 'Disable' : 'Enable' }}"><i class="bi {{ $b->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="pk-card p-3 p-md-4" aria-labelledby="catsTitle">
                <h2 id="catsTitle" class="lib-section-title mb-3"><i class="bi bi-tags"></i> Categories</h2>
                @foreach($categories as $c)
                    <details class="border rounded-3 mb-2 px-3 py-2" style="border-color:var(--pk-line)!important">
                        <summary class="d-flex align-items-center gap-2 flex-wrap" style="cursor:pointer;list-style:none">
                            <i class="bi {{ $c->icon }} text-primary"></i>
                            <span class="fw-bold">{{ $c->name }}</span><span class="small pk-muted" lang="ne">{{ $c->name_ne }}</span>
                            <span class="small pk-muted">· {{ $c->books_count }} items · {{ $c->kind }}</span>
                            <span class="ms-auto">@if($c->is_active)<span class="pk-status called">Visible</span>@else<span class="pk-status done">Hidden</span>@endif</span>
                        </summary>
                        <form method="post" action="{{ route('library.admin.categories.update', $c) }}" class="row g-2 mt-2">
                            @csrf @method('PUT')
                            <div class="col-sm-6"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" value="{{ $c->name }}" required maxlength="80"></div>
                            <div class="col-sm-6"><label class="form-label small">Nepali name</label><input name="name_ne" class="form-control form-control-sm" value="{{ $c->name_ne }}" maxlength="80" lang="ne"></div>
                            <div class="col-sm-4"><label class="form-label small">Slug</label><input name="slug" class="form-control form-control-sm" value="{{ $c->slug }}" required maxlength="60" pattern="[a-z0-9]+(-[a-z0-9]+)*"></div>
                            <div class="col-sm-4"><label class="form-label small">Kind</label><select name="kind" class="form-select form-select-sm"><option value="genre" @selected($c->kind === 'genre')>Genre</option><option value="periodical" @selected($c->kind === 'periodical')>News / blogs / articles</option></select></div>
                            <div class="col-sm-4"><label class="form-label small">Icon (bi-*)</label><input name="icon" class="form-control form-control-sm" value="{{ $c->icon }}" maxlength="40" pattern="bi-[a-z0-9-]+"></div>
                            <div class="col-12"><label class="form-label small">Description</label><input name="description" class="form-control form-control-sm" value="{{ $c->description }}" maxlength="255"></div>
                            <div class="col-12 d-flex gap-2 justify-content-end">
                                <button class="btn btn-sm btn-pk">Save</button>
                            </div>
                        </form>
                        <form method="post" action="{{ route('library.admin.categories.toggle', $c) }}" class="text-end mt-2 mb-1">
                            @csrf
                            <button class="btn btn-sm btn-ghost">{{ $c->is_active ? 'Hide category' : 'Show category' }}</button>
                        </form>
                    </details>
                @endforeach
            </section>
        </div>
        <div class="col-lg-4">
            <section class="pk-card p-3 p-md-4" aria-labelledby="newCatTitle">
                <h2 id="newCatTitle" class="lib-section-title mb-3"><i class="bi bi-plus-circle"></i> New category</h2>
                <form method="post" action="{{ route('library.admin.categories.store') }}" class="d-grid gap-2">
                    @csrf
                    <div><label class="form-label small" for="ncName">Name</label><input id="ncName" name="name" class="form-control" required maxlength="80" value="{{ old('name') }}"></div>
                    <div><label class="form-label small" for="ncNe">Nepali name</label><input id="ncNe" name="name_ne" class="form-control" maxlength="80" lang="ne" value="{{ old('name_ne') }}"></div>
                    <div><label class="form-label small" for="ncSlug">Slug</label><input id="ncSlug" name="slug" class="form-control" required maxlength="60" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="e.g. poetry" value="{{ old('slug') }}"></div>
                    <div><label class="form-label small" for="ncKind">Kind</label><select id="ncKind" name="kind" class="form-select"><option value="genre">Genre</option><option value="periodical">News / blogs / articles</option></select></div>
                    <div><label class="form-label small" for="ncDesc">Description</label><input id="ncDesc" name="description" class="form-control" maxlength="255" value="{{ old('description') }}"></div>
                    <button class="btn btn-pk mt-1">Create category</button>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection
