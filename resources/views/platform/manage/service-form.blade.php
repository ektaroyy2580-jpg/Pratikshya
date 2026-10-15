@extends('platform.layouts.console')
@section('title', ($service->exists ? $service->name : 'New service').' — प्रतीक्षा')

@section('console')
@php
    $editing = $service->exists;
    $docsText = old('documents_text', implode("\n", $service->documents ?? []));
    $flags = [
        ['is_active', 'Active', 'Visible to the public and accepting new tokens.', $editing ? $service->is_active : true],
        ['is_paused', 'Paused', 'Temporarily stop calling tokens (e.g. lunch break or system outage). Users see a paused notice.', $editing ? $service->is_paused : false],
        ['allow_exchange', 'Allow voluntary queue exchange', 'Users may request to swap tokens within this service, subject to organization rules and approvals.', $editing ? $service->allow_exchange : true],
        ['requires_all_documents', 'Require all documents before joining', 'Users must confirm every document in the checklist to join.', $editing ? $service->requires_all_documents : false],
    ];
@endphp
<div class="pf-title fade-up">
    <div>
        <a href="{{ route('platform.manage.locations.edit', $location) }}" class="pk-breadcrumb"><i class="bi bi-arrow-left"></i> {{ $location->name }}</a>
        <h1>{{ $editing ? $service->name : 'New service' }}</h1>
        <p>{{ $editing ? 'Now serving '.$service->token($service->current_number).' · last issued '.$service->token($service->last_number) : 'Tokens start at 1 with the prefix you choose.' }}</p>
    </div>
    @if($editing && Route::has('service'))
        <a href="{{ route('service', $service) }}" class="btn btn-ghost" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i> Public page</a>
    @endif
</div>

<form method="POST" action="{{ $editing ? route('platform.manage.services.update', $service) : route('platform.manage.services.store', $location) }}" class="pf-form" id="svcForm">
    @csrf
    <div class="row g-3">
        <div class="col-xl-7">
            <section class="pk-card pf-panel reveal">
                <div class="pf-panel-head"><h2><i class="bi bi-ticket-perforated text-primary"></i> Queue setup</h2></div>
                <div class="pf-panel-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="s_name">Service name</label>
                            <input class="form-control @error('name') is-invalid @enderror" id="s_name" name="name" value="{{ old('name', $service->name) }}" required maxlength="120">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="s_prefix">Token prefix</label>
                            <input class="form-control text-uppercase @error('prefix') is-invalid @enderror" id="s_prefix" name="prefix" value="{{ old('prefix', $service->prefix) }}" required maxlength="1" pattern="[A-Za-z]" aria-describedby="prefixHelp">
                            <div class="form-text" id="prefixHelp">One letter, e.g. D → D-24</div>
                            @error('prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="s_counter">Default counter</label>
                            <input class="form-control @error('counter') is-invalid @enderror" id="s_counter" type="number" min="1" max="99" name="counter" value="{{ old('counter', $service->counter) }}" required>
                            @error('counter')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="s_avg">Avg minutes / person</label>
                            <input class="form-control @error('avg_minutes') is-invalid @enderror" id="s_avg" type="number" min="1" max="240" name="avg_minutes" value="{{ old('avg_minutes', $service->avg_minutes) }}" required>
                            @error('avg_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="pk-card pf-panel mt-3 reveal" aria-labelledby="docH">
                <div class="pf-panel-head"><h2 id="docH"><i class="bi bi-file-earmark-check text-primary"></i> Required documents</h2><span class="small pk-muted" id="docCount"></span></div>
                <div class="pf-panel-body">
                    <div id="docEditor" class="d-none">
                        <ul class="list-unstyled d-grid gap-2 mb-2" id="docList" aria-live="polite"></ul>
                        <div class="d-flex gap-2">
                            <label class="visually-hidden" for="docNew">New document</label>
                            <input class="form-control" id="docNew" placeholder="e.g. Citizenship certificate copy" maxlength="120">
                            <button type="button" class="btn btn-ghost flex-shrink-0" id="docAdd"><i class="bi bi-plus-lg"></i> Add</button>
                        </div>
                    </div>
                    <div id="docFallback">
                        <label class="form-label" for="documents_text">One document per line</label>
                        <textarea class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" id="documents_text" name="documents_text" rows="5">{{ $docsText }}</textarea>
                    </div>
                    @error('documents')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    @error('documents.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="form-text mt-2">Up to 15 items. Users tick these before joining; staff verify them at the counter.</div>
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="pk-card pf-panel reveal" style="--d:.06s">
                <div class="pf-panel-head"><h2><i class="bi bi-toggles text-primary"></i> Status & rules</h2></div>
                <div class="pf-panel-body">
                    @foreach($flags as [$name, $label, $desc, $value])
                        <div class="pf-switch">
                            <div><div class="t">{{ $label }}</div><div class="d">{{ $desc }}</div></div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="f_{{ $name }}" name="{{ $name }}" value="1" aria-label="{{ $label }}" @checked(old($name, $value))>
                            </div>
                        </div>
                    @endforeach
                    <p class="small pk-muted mb-0 mt-2"><i class="bi bi-shield-lock me-1"></i>Token numbers are managed only by the queue engine and cannot be edited here.</p>
                </div>
            </section>
            <div class="d-grid mt-3"><button class="btn btn-pk" type="submit"><i class="bi bi-check2 me-1"></i> {{ $editing ? 'Save service' : 'Create service' }}</button></div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
// Documents list editor (progressive enhancement over the textarea).
(() => {
    const ta = document.getElementById('documents_text');
    const editor = document.getElementById('docEditor');
    const list = document.getElementById('docList');
    const input = document.getElementById('docNew');
    const count = document.getElementById('docCount');
    let docs = ta.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    document.getElementById('docFallback').classList.add('d-none');
    editor.classList.remove('d-none');

    const sync = () => { ta.value = docs.join('\n'); count.textContent = docs.length + ' / 15'; };
    const render = () => {
        list.innerHTML = '';
        if (!docs.length) {
            const li = document.createElement('li');
            li.className = 'small pk-muted';
            li.textContent = 'No documents required yet.';
            list.appendChild(li);
        }
        docs.forEach((d, i) => {
            const li = document.createElement('li');
            li.className = 'd-flex align-items-center gap-2 p-2 rounded-3 border bg-white fade-up';
            const icon = document.createElement('i'); icon.className = 'bi bi-file-earmark-text text-primary ms-1';
            const span = document.createElement('span'); span.className = 'flex-grow-1 small fw-semibold'; span.textContent = d;
            const mk = (cls, label, fn, disabled) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-ghost btn-xs'; b.innerHTML = '<i class="bi ' + cls + '"></i>'; b.setAttribute('aria-label', label); b.disabled = disabled; if (disabled) b.style.visibility = 'hidden'; b.onclick = fn; return b; };
            li.append(icon, span,
                mk('bi-arrow-up', 'Move up: ' + d, () => { [docs[i - 1], docs[i]] = [docs[i], docs[i - 1]]; render(); }, i === 0),
                mk('bi-x-lg', 'Remove: ' + d, () => { docs.splice(i, 1); render(); }, false));
            list.appendChild(li);
        });
        sync();
    };
    const add = () => {
        const v = input.value.trim();
        if (!v || docs.length >= 15 || docs.includes(v)) { input.classList.add('is-invalid'); setTimeout(() => input.classList.remove('is-invalid'), 900); return; }
        docs.push(v.slice(0, 120)); input.value = ''; render(); input.focus();
    };
    document.getElementById('docAdd').onclick = add;
    input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); add(); } });
    render();
})();
</script>
@endpush
