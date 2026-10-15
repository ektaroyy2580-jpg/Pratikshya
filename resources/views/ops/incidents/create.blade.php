@extends('layouts.app')
@section('title', 'Report incident — Office operations — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $cats = \App\Models\OperationalIncident::CATEGORIES;
    $oldLoc = (int) old('location_id', $prefill['location_id']);
    $oldCat = old('category', $prefill['category'] ?? 'counter_closed');
    $oldPrio = old('priority', 'medium');
    $tree = $locations->mapWithKeys(fn ($l) => [$l->id => [
        'services' => $l->services->map(fn ($s) => ['id' => $s->id, 'name' => $s->prefix.' · '.$s->name])->values(),
        'counters' => $l->counters->sortBy('number')->map(fn ($c) => ['id' => $c->id, 'service_id' => $c->service_id, 'name' => 'Counter '.$c->number.($c->is_open ? '' : ' (closed)')])->values(),
    ]]);
@endphp
@include('ops.partials.console-head', ['active' => 'create', 'title' => 'Report an incident', 'subtitle' => 'Logged internally with your name and time. Nothing here is shown to the public.'])

<div class="container py-4 py-lg-5">
    <a href="{{ route('ops.incidents.index') }}" class="pk-breadcrumb d-inline-block mb-3"><i class="bi bi-arrow-left me-1"></i>All incidents</a>

    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i>
            <div><b>Please fix the following:</b><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        </div>
    @endif

    <form method="POST" action="{{ route('ops.incidents.store') }}" enctype="multipart/form-data" class="row g-4" id="incForm" novalidate>
        @csrf
        <div class="col-lg-8">
            <div class="pk-card p-4 reveal">
                <h2 class="h6 fw-bold mb-3">What happened?</h2>
                <fieldset class="mb-4">
                    <legend class="form-label">Category</legend>
                    <div class="cat-grid">
                        @foreach($cats as $key => [$label, $icon])
                            <div class="cat-opt {{ $key === 'security' ? 'sec' : '' }}">
                                <input type="radio" name="category" id="cat-{{ $key }}" value="{{ $key }}" @checked($oldCat === $key) required>
                                <label for="cat-{{ $key }}"><i class="bi {{ $icon }}"></i>{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mb-3">
                    <label for="description" class="form-label">Internal description</label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" maxlength="2000" required placeholder="e.g. Receipt printer at Counter 2 jammed, paper feed broken. Customers asked to bring photocopies.">{{ old('description') }}</textarea>
                    <div class="form-text"><i class="bi bi-lock me-1"></i>Staff-only. Public notices are written separately and never copy this text.</div>
                </div>

                <fieldset class="mb-3">
                    <legend class="form-label">Priority</legend>
                    <div class="prio-seg">
                        @foreach(\App\Models\OperationalIncident::PRIORITIES as $p)
                            <input type="radio" class="btn-check" name="priority" id="prio-{{ $p }}" value="{{ $p }}" @checked($oldPrio === $p)>
                            <label class="btn l-{{ $p }}" for="prio-{{ $p }}">{{ ucfirst($p) }}</label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="isInternal" name="is_internal" value="1" @checked(old('is_internal'))>
                    <label class="form-check-label fw-semibold" for="isInternal">Internal / confidential</label>
                    <div class="form-text" id="internalHelp">Internal incidents can never be published as public notices. Security incidents are always internal.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="pk-card p-4 reveal" style="--d:.06s">
                <h2 class="h6 fw-bold mb-3">Where?</h2>
                <div class="mb-3">
                    <label for="locationSel" class="form-label">Location</label>
                    <select id="locationSel" name="location_id" class="form-select" required>
                        @foreach($locations as $l)<option value="{{ $l->id }}" @selected($oldLoc === $l->id)>{{ $l->name }}</option>@endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="serviceSel" class="form-label">Affected service <span class="pk-muted fw-normal">(optional)</span></label>
                    <select id="serviceSel" name="service_id" class="form-select" data-selected="{{ old('service_id', $prefill['service_id']) }}"><option value="">Not service-specific</option></select>
                </div>
                <div class="mb-3">
                    <label for="counterSel" class="form-label">Counter <span class="pk-muted fw-normal">(optional)</span></label>
                    <select id="counterSel" name="counter_id" class="form-select" data-selected="{{ old('counter_id', $prefill['counter_id']) }}"><option value="">No specific counter</option></select>
                </div>
                <div class="mb-3">
                    <label for="equipment" class="form-label">Equipment <span class="pk-muted fw-normal">(optional)</span></label>
                    <input id="equipment" name="equipment" class="form-control" maxlength="120" value="{{ old('equipment') }}" placeholder="e.g. Receipt printer">
                </div>
                <div class="mb-3">
                    <label for="tech" class="form-label">Assigned technician <span class="pk-muted fw-normal">(optional)</span></label>
                    <input id="tech" name="assigned_technician" class="form-control" maxlength="120" value="{{ old('assigned_technician') }}" placeholder="Name or team">
                </div>

                <label class="form-label" for="photo">Photo <span class="pk-muted fw-normal">(optional)</span></label>
                <label class="drop" for="photo" id="drop">
                    <i class="bi bi-camera fs-4 pk-muted d-block"></i>
                    <span class="small fw-semibold" id="dropText">Tap to add a photo</span>
                    <span class="d-block small pk-muted">JPG, PNG or WebP · max 2 MB · stored privately</span>
                    <img alt="" class="pv d-none" id="photoPv">
                </label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="visually-hidden">
                <div class="small text-danger mt-1" id="photoErr" role="alert">@error('photo'){{ $message }}@enderror</div>

                <div class="d-grid mt-4">
                    <button class="btn btn-pk" id="submitBtn"><i class="bi bi-send me-1"></i>Report incident</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const tree = @json($tree);
    const loc = document.getElementById('locationSel'), svc = document.getElementById('serviceSel'), ctr = document.getElementById('counterSel');
    const fill = (sel, items, first) => {
        const want = sel.dataset.selected;
        sel.innerHTML = '';
        sel.add(new Option(first, ''));
        items.forEach(i => sel.add(new Option(i.name, i.id, false, String(i.id) === want)));
    };
    const refresh = () => {
        const t = tree[loc.value] || { services: [], counters: [] };
        fill(svc, t.services, 'Not service-specific');
        fill(ctr, t.counters, 'No specific counter');
    };
    loc.addEventListener('change', () => { svc.dataset.selected = ''; ctr.dataset.selected = ''; refresh(); });
    ctr.addEventListener('change', () => {
        const c = (tree[loc.value]?.counters || []).find(c => String(c.id) === ctr.value);
        if (c && c.service_id && !svc.value) svc.value = c.service_id;
    });
    refresh();

    // Security incidents are always internal.
    const internal = document.getElementById('isInternal');
    const syncInternal = () => {
        const sec = document.getElementById('cat-security').checked;
        if (sec) internal.checked = true;
        internal.disabled = sec;
    };
    document.querySelectorAll('input[name=category]').forEach(r => r.addEventListener('change', syncInternal));
    syncInternal();
    document.getElementById('incForm').addEventListener('submit', (e) => {
        internal.disabled = false; // disabled inputs are not submitted
        const d = document.getElementById('description');
        if (d.value.trim().length < 5) { e.preventDefault(); d.classList.add('is-invalid'); d.focus(); return; }
        const b = document.getElementById('submitBtn'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reporting…';
    });

    // Photo preview + client-side checks (the server validates again).
    const input = document.getElementById('photo'), pv = document.getElementById('photoPv'), err = document.getElementById('photoErr'), txt = document.getElementById('dropText');
    input.addEventListener('change', () => {
        err.textContent = ''; pv.classList.add('d-none');
        const f = input.files[0]; if (!f) { txt.textContent = 'Tap to add a photo'; return; }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { err.textContent = 'Please choose a JPG, PNG or WebP image.'; input.value = ''; return; }
        if (f.size > 2 * 1024 * 1024) { err.textContent = 'That photo is larger than 2 MB.'; input.value = ''; return; }
        txt.textContent = f.name; pv.src = URL.createObjectURL(f); pv.classList.remove('d-none');
    });
    const drop = document.getElementById('drop');
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('drag'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('drag'); }));
    drop.addEventListener('drop', e => { if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; input.dispatchEvent(new Event('change')); } });
})();
</script>
@endpush
