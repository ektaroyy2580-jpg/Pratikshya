@extends('platform.layouts.console')
@section('title', ($location->exists ? $location->name : 'New location').' — प्रतीक्षा')

@section('console')
@php $editing = $location->exists; @endphp
<div class="pf-title fade-up">
    <div>
        <a href="{{ route('platform.manage.locations') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left"></i> Locations</a>
        <h1>{{ $editing ? $location->name : 'New location' }}</h1>
        <p>{{ $editing ? (($org->name ?? 'No organization').' · '.$location->area) : 'Pin the office on the map and choose its organization.' }}</p>
    </div>
    @if($editing && Route::has('service') && $location->services->isNotEmpty())
        <a href="{{ route('service', $location->services->first()) }}" class="btn btn-ghost" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i> View public page</a>
    @endif
</div>

<form method="POST" action="{{ $editing ? route('platform.manage.locations.update', $location) : route('platform.manage.locations.store') }}" class="pk-card pf-panel pf-form reveal">
    @csrf
    <div class="pf-panel-head"><h2><i class="bi bi-pin-map text-primary"></i> Location details</h2></div>
    <div class="pf-panel-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="l_name">Name</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="l_name" name="name" value="{{ old('name', $location->name) }}" required maxlength="120">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-7">
                        <label class="form-label" for="l_area">Area</label>
                        <input class="form-control @error('area') is-invalid @enderror" id="l_area" name="area" value="{{ old('area', $location->area) }}" placeholder="e.g. Baneshwor, Kathmandu" required maxlength="120">
                        @error('area')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="l_type">Type label</label>
                        <input class="form-control @error('type') is-invalid @enderror" id="l_type" name="type" value="{{ old('type', $location->type) }}" placeholder="Government" required maxlength="40" list="locTypes">
                        <datalist id="locTypes"><option value="Government"><option value="Bank"><option value="Hospital"><option value="Ward Office"><option value="Private"></datalist>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-7">
                        <label class="form-label" for="l_org">Organization</label>
                        <select class="form-select @error('organization_id') is-invalid @enderror" id="l_org" name="organization_id" required>
                            @foreach($organizations as $o)<option value="{{ $o->id }}" @selected((int) old('organization_id', $location->organization_id) === $o->id)>{{ $o->name }}</option>@endforeach
                        </select>
                        @error('organization_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="l_icon">Icon</label>
                        <select class="form-select" id="l_icon" name="icon">
                            @foreach(['bi-building-fill' => 'Building', 'bi-car-front-fill' => 'Transport', 'bi-passport-fill' => 'Passport', 'bi-bank2' => 'Bank', 'bi-hospital-fill' => 'Hospital', 'bi-house-door-fill' => 'Ward', 'bi-shop' => 'Shop'] as $ic => $lbl)
                                <option value="{{ $ic }}" @selected(old('icon', $location->icon) === $ic)>{{ $lbl }}</option>
                            @endforeach
                            @if($location->icon && ! in_array($location->icon, ['bi-building-fill', 'bi-car-front-fill', 'bi-passport-fill', 'bi-bank2', 'bi-hospital-fill', 'bi-house-door-fill', 'bi-shop'], true))
                                <option value="{{ $location->icon }}" selected>{{ $location->icon }}</option>
                            @endif
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                @include('platform.manage.partials.map-picker', ['lat' => $location->lat, 'lng' => $location->lng])
            </div>
        </div>
        <div class="d-flex justify-content-end mt-3"><button class="btn btn-pk"><i class="bi bi-check2 me-1"></i> {{ $editing ? 'Save location' : 'Create location' }}</button></div>
    </div>
</form>

@if($editing)
    <section class="pk-card pf-panel mt-3 reveal" aria-labelledby="svcH">
        <div class="pf-panel-head">
            <h2 id="svcH"><i class="bi bi-ticket-perforated text-primary"></i> Services</h2>
            <a href="{{ route('platform.manage.services.create', $location) }}" class="btn btn-ghost btn-xs"><i class="bi bi-plus-lg"></i> Add service</a>
        </div>
        @if($location->services->isEmpty())
            <div class="pf-empty"><p class="pk-muted small mb-0">No services yet. Add one to start issuing tokens.</p></div>
        @else
            <div class="table-responsive">
                <table class="table pf-tbl pf-stack">
                    <thead><tr><th>Service</th><th>Prefix</th><th>Counter</th><th>Avg</th><th>Docs</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @foreach($location->services as $s)
                        <tr>
                            <td><div class="fw-bold">{{ $s->name }}</div><div class="sub">Now serving {{ $s->token($s->current_number) }} · {{ $s->waitingCount() }} waiting</div></td>
                            <td data-label="Prefix"><span class="pf-act">{{ $s->prefix }}</span></td>
                            <td data-label="Counter">{{ $s->counter }}</td>
                            <td data-label="Avg minutes">{{ $s->avg_minutes }} min</td>
                            <td data-label="Documents">{{ count($s->documents ?? []) }}</td>
                            <td data-label="Status">
                                @if(! $s->is_active)<span class="pf-badge muted">Inactive</span>
                                @elseif($s->is_paused)<span class="pf-badge warn"><i class="bi bi-pause-fill"></i> Paused</span>
                                @else<span class="pf-badge ok">Live</span>@endif
                            </td>
                            <td class="text-md-end"><a href="{{ route('platform.manage.services.edit', $s) }}" class="btn btn-ghost btn-xs">Edit</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="pk-card pf-panel mt-3 reveal" aria-labelledby="ctrH">
        <div class="pf-panel-head"><h2 id="ctrH"><i class="bi bi-door-open text-primary"></i> Counters</h2><span class="small pk-muted">{{ $location->counters->where('is_open', true)->count() }} of {{ $location->counters->count() }} open</span></div>
        @if($location->counters->isNotEmpty())
            <div class="table-responsive">
                <table class="table pf-tbl pf-stack">
                    <thead><tr><th>Counter</th><th>Label & service</th><th>State</th><th class="text-end">Open / close</th></tr></thead>
                    <tbody>
                    @foreach($location->counters as $c)
                        <tr>
                            <td><span class="fw-bold fs-5">{{ $c->number }}</span></td>
                            <td data-label="Label & service">
                                <form method="POST" action="{{ route('platform.manage.counters.update', $c) }}" class="d-flex flex-wrap gap-2">
                                    @csrf
                                    <label class="visually-hidden" for="cl{{ $c->id }}">Label</label>
                                    <input class="form-control form-control-sm" style="max-width:150px" id="cl{{ $c->id }}" name="label" value="{{ $c->label }}" maxlength="60">
                                    <label class="visually-hidden" for="cs{{ $c->id }}">Service</label>
                                    <select class="form-select form-select-sm" style="max-width:220px" id="cs{{ $c->id }}" name="service_id">
                                        <option value="">Any service</option>
                                        @foreach($location->services as $s)<option value="{{ $s->id }}" @selected($c->service_id === $s->id)>{{ $s->name }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-ghost btn-xs">Save</button>
                                </form>
                            </td>
                            <td data-label="State">@if($c->is_open)<span class="pf-badge ok"><i class="bi bi-circle-fill" style="font-size:.45rem"></i> Open</span>@else<span class="pf-badge muted">Closed</span>@endif</td>
                            <td class="text-md-end">
                                <form method="POST" action="{{ route('platform.manage.counters.toggle', $c) }}" @if($c->is_open) data-confirm="Close counter {{ $c->number }}? Staff will not be able to call tokens to it." @endif>
                                    @csrf<button class="btn {{ $c->is_open ? 'btn-ghost' : 'btn-pk' }} btn-xs">{{ $c->is_open ? 'Close' : 'Open' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <form method="POST" action="{{ route('platform.manage.counters.store', $location) }}" class="pf-panel-body border-top pf-form">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-4 col-md-2"><label class="form-label" for="nc_num">Number</label><input class="form-control" id="nc_num" type="number" min="1" max="99" name="number" value="{{ ($location->counters->max('number') ?? 0) + 1 }}" required></div>
                <div class="col-8 col-md-4"><label class="form-label" for="nc_label">Label</label><input class="form-control" id="nc_label" name="label" placeholder="Counter name (optional)" maxlength="60"></div>
                <div class="col-12 col-md-4"><label class="form-label" for="nc_svc">Service</label>
                    <select class="form-select" id="nc_svc" name="service_id"><option value="">Any service</option>@foreach($location->services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                </div>
                <div class="col-12 col-md-2 d-grid"><button class="btn btn-pk"><i class="bi bi-plus-lg"></i> Add</button></div>
            </div>
        </form>
    </section>

    <div class="d-flex flex-wrap gap-2 mt-3">
        <a href="{{ route('platform.manage.places', ['location' => $location->id]) }}" class="btn btn-ghost btn-sm"><i class="bi bi-shop me-1"></i> Nearby places & listings</a>
        <a href="{{ route('platform.manage.staff') }}" class="btn btn-ghost btn-sm"><i class="bi bi-person-badge me-1"></i> Staff assignments</a>
    </div>
@endif
@endsection
