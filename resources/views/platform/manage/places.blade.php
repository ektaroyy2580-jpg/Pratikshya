@extends('platform.layouts.console')
@section('title', 'Nearby places & listings — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-shop"></i> Operations</span>
        <h1>Nearby places & business listings</h1>
        <p>Cafés, parks, print shops and partner businesses shown to people waiting nearby.</p>
    </div>
    @if($locations->isNotEmpty())
        <a href="{{ route('platform.manage.places.create', array_filter(['location' => $locId])) }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i> Add place</a>
    @endif
</div>

<section class="pk-card pf-panel reveal" aria-label="Places">
    <div class="pf-panel-head">
        <form method="GET" class="pf-filter w-100" role="search">
            <label class="visually-hidden" for="plQ">Search</label>
            <input id="plQ" class="form-control" style="max-width:220px" type="search" name="q" value="{{ $q }}" placeholder="Name or category">
            <label class="visually-hidden" for="plLoc">Location</label>
            <select id="plLoc" name="location" class="form-select" style="max-width:240px">
                <option value="">All locations</option>
                @foreach($locations as $l)<option value="{{ $l->id }}" @selected($locId === $l->id)>{{ $l->name }}</option>@endforeach
            </select>
            <div class="form-check ms-1">
                <input class="form-check-input" type="checkbox" id="plList" name="type" value="listings" @checked($listings)>
                <label class="form-check-label small fw-semibold" for="plList">Business listings only</label>
            </div>
            <button class="btn btn-ghost" data-no-busy><i class="bi bi-funnel me-1"></i>Filter</button>
        </form>
    </div>
    @if($places->isEmpty())
        <div class="pf-empty"><span class="pk-icon mb-2"><i class="bi bi-shop"></i></span><h3 class="h6 mb-1">Nothing here yet</h3><p class="pk-muted small mb-0">Add places so waiting users can make good use of their time.</p></div>
    @else
        <div class="table-responsive">
            <table class="table pf-tbl pf-stack">
                <thead><tr><th>Place</th><th>Near</th><th>Walk</th><th>Rating</th><th>Type</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach($places as $p)
                    <tr>
                        <td><div class="d-flex align-items-center gap-3"><span class="fs-4" aria-hidden="true">{{ $p->emoji }}</span><div class="min-w-0"><div class="fw-bold">{{ $p->name }}</div><div class="sub">{{ $p->category }} · {{ \Illuminate\Support\Str::limit($p->tip, 60) }}</div></div></div></td>
                        <td data-label="Near">{{ $locNames[$p->location_id] ?? '—' }}</td>
                        <td data-label="Walk">{{ $p->walk_minutes }} min</td>
                        <td data-label="Rating"><i class="bi bi-star-fill text-warning"></i> {{ number_format($p->rating, 1) }}</td>
                        <td data-label="Type">@if($p->is_business_listing)<span class="pf-badge violet"><i class="bi bi-patch-check"></i> Listing</span>@else<span class="pf-badge muted">Place</span>@endif</td>
                        <td class="text-md-end"><a href="{{ route('platform.manage.places.edit', $p) }}" class="btn btn-ghost btn-xs">Edit</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($places->hasPages())<div class="pf-pager">{{ $places->links('pagination::bootstrap-5') }}</div>@endif
    @endif
</section>
@endsection
