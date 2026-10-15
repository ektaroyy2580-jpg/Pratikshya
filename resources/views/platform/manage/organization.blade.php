@extends('platform.layouts.console')
@section('title', $org->name.' — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <a href="{{ route('platform.manage.organizations') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left"></i> Organizations</a>
        <h1>{{ $org->name }}</h1>
        <p>{{ ucfirst($org->type) }} · {{ $org->locations->count() }} {{ \Illuminate\Support\Str::plural('location', $org->locations->count()) }} @if($org->is_demo)<span class="pf-demo ms-1">Demo organization</span>@endif</p>
    </div>
    <a href="{{ route('platform.manage.locations.create') }}" class="btn btn-ghost"><i class="bi bi-plus-lg me-1"></i> Add location</a>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="POST" action="{{ route('platform.manage.organizations.update', $org) }}" class="pk-card pf-panel pf-form reveal">
            @csrf
            <div class="pf-panel-head"><h2><i class="bi bi-sliders text-primary"></i> Details & rules</h2></div>
            <div class="pf-panel-body">
                @include('platform.manage.partials.org-fields', ['org' => $org])
                <div class="d-flex justify-content-end mt-3"><button class="btn btn-pk"><i class="bi bi-check2 me-1"></i> Save changes</button></div>
            </div>
        </form>

        <section class="pk-card pf-panel mt-3 reveal" aria-labelledby="locH">
            <div class="pf-panel-head"><h2 id="locH"><i class="bi bi-geo-alt text-primary"></i> Locations</h2></div>
            @forelse($org->locations as $loc)
                <a href="{{ route('platform.manage.locations.edit', $loc) }}" class="d-flex align-items-center gap-3 px-3 px-md-4 py-3 border-top text-decoration-none text-reset" style="border-color:#f0f1f7 !important">
                    <span class="pk-icon violet" style="width:36px;height:36px;font-size:.95rem"><i class="bi {{ $loc->icon }}"></i></span>
                    <div class="min-w-0 flex-grow-1"><div class="fw-bold text-truncate">{{ $loc->name }}</div><div class="small pk-muted">{{ $loc->area }} · {{ $loc->services_count }} services</div></div>
                    <i class="bi bi-chevron-right pk-muted"></i>
                </a>
            @empty
                <div class="pf-empty"><p class="pk-muted small mb-0">No locations yet.</p></div>
            @endforelse
        </section>
    </div>

    <div class="col-xl-5">
        <section class="pk-card pf-panel reveal" style="--d:.06s" aria-labelledby="memH">
            <div class="pf-panel-head"><h2 id="memH"><i class="bi bi-person-gear text-primary"></i> Organization admins</h2></div>
            @forelse($org->members as $m)
                <div class="d-flex align-items-center gap-3 px-3 px-md-4 py-3 border-top" style="border-color:#f0f1f7 !important">
                    <span class="pk-icon violet" style="width:34px;height:34px;font-size:.85rem;border-radius:50%">{{ mb_strtoupper(mb_substr($m->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-grow-1"><div class="fw-semibold text-truncate">{{ $m->name }}</div><div class="small pk-muted text-truncate">{{ $m->email }}</div></div>
                    @if($canManageMembers)
                        <form method="POST" action="{{ route('platform.manage.organizations.members.remove', [$org, $m]) }}" data-confirm="Remove {{ $m->name }} as organization admin?">
                            @csrf<button class="btn btn-ghost btn-xs" aria-label="Remove {{ $m->name }}"><i class="bi bi-x-lg"></i></button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="px-4 pb-3 pt-3 small pk-muted">No organization admins yet{{ $canManageMembers ? '' : '.' }}</div>
            @endforelse
            @if($canManageMembers)
                <form method="POST" action="{{ route('platform.manage.organizations.members.add', $org) }}" class="pf-panel-body border-top pf-form">
                    @csrf
                    <label class="form-label" for="memEmail">Add admin by account email</label>
                    <div class="d-flex gap-2">
                        <input class="form-control" id="memEmail" type="email" name="email" placeholder="person@example.com" required>
                        <button class="btn btn-pk flex-shrink-0">Add</button>
                    </div>
                    <div class="form-text">Organization admins manage this organization only. They cannot change roles.</div>
                </form>
            @else
                <div class="pf-panel-body border-top small pk-muted"><i class="bi bi-lock me-1"></i>Only platform admins can add or remove organization admins.</div>
            @endif
        </section>

        @if($canDelete)
            <section class="pk-card pf-panel mt-3 reveal" style="--d:.1s">
                <div class="pf-panel-head"><h2><i class="bi bi-trash3 text-danger"></i> Delete organization</h2></div>
                <div class="pf-panel-body">
                    @if($org->locations->isNotEmpty())
                        <p class="small pk-muted mb-0">Move this organization's locations to another organization first. Queue data is never deleted from here.</p>
                    @else
                        <form method="POST" action="{{ route('platform.manage.organizations.delete', $org) }}" data-confirm="Delete this organization?">
                            @csrf<button class="btn btn-outline-danger btn-sm">Delete organization</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
