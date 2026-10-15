@extends('platform.layouts.console')
@section('title', 'Locations & services — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-geo-alt"></i> Operations</span>
        <h1>Locations & services</h1>
        <p>Offices, branches and wards — open one to edit its services, documents and counters.</p>
    </div>
    @if($organizations->isNotEmpty())
        <a href="{{ route('platform.manage.locations.create') }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i> Add location</a>
    @endif
</div>

<section class="pk-card pf-panel reveal" aria-label="Locations">
    <div class="pf-panel-head">
        <form method="GET" class="pf-filter w-100" role="search">
            <label class="visually-hidden" for="locQ">Search locations</label>
            <input id="locQ" class="form-control" style="max-width:260px" type="search" name="q" value="{{ $q }}" placeholder="Search name or area">
            @if($organizations->count() > 1)
                <label class="visually-hidden" for="locOrg">Organization</label>
                <select id="locOrg" name="organization" class="form-select" style="max-width:260px">
                    <option value="">All organizations</option>
                    @foreach($organizations as $o)<option value="{{ $o->id }}" @selected($orgId === $o->id)>{{ $o->name }}</option>@endforeach
                </select>
            @endif
            <button class="btn btn-ghost" data-no-busy><i class="bi bi-funnel me-1"></i>Filter</button>
            @if($q || $orgId)<a href="{{ route('platform.manage.locations') }}" class="small fw-semibold">Clear</a>@endif
        </form>
    </div>
    @if($locations->isEmpty())
        <div class="pf-empty"><span class="pk-icon mb-2"><i class="bi bi-geo-alt"></i></span><h3 class="h6 mb-1">No locations found</h3><p class="pk-muted small mb-0">{{ $q || $orgId ? 'Try a different search.' : 'Add your first location to start taking queues.' }}</p></div>
    @else
        <div class="table-responsive">
            <table class="table pf-tbl pf-stack">
                <thead><tr><th>Location</th><th>Organization</th><th>Services</th><th>Counters</th><th>Nearby</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach($locations as $loc)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="pk-icon violet" style="width:36px;height:36px;font-size:.95rem"><i class="bi {{ $loc->icon }}"></i></span>
                                <div class="min-w-0"><div class="fw-bold">{{ $loc->name }}</div><div class="sub">{{ $loc->area }} · {{ $loc->type }}</div></div>
                            </div>
                        </td>
                        <td data-label="Organization">{{ $orgNames[$loc->organization_id] ?? '—' }}</td>
                        <td data-label="Services">{{ $loc->services_count }}</td>
                        <td data-label="Counters">{{ $loc->counters_count }}</td>
                        <td data-label="Nearby">{{ $loc->places_count }}</td>
                        <td class="text-md-end"><a href="{{ route('platform.manage.locations.edit', $loc) }}" class="btn btn-ghost btn-xs">Manage <i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($locations->hasPages())<div class="pf-pager">{{ $locations->links('pagination::bootstrap-5') }}</div>@endif
    @endif
</section>
@endsection
