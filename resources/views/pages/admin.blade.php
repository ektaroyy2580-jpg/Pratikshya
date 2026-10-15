@extends('layouts.app')
@section('title', 'Admin Dashboard — प्रतीक्षा')

@push('head')
<style>
    .admin-head { padding: 2rem 0 1.75rem; }
    .min-w-0 { min-width: 0; }
    .admin-head h1 { font-size: clamp(1.5rem, 2.6vw, 2rem); }
    .sec-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem 1rem; margin-bottom: 1rem; }
    .sec-head h2 { font-size: 1.05rem; margin: 0; display: flex; align-items: center; gap: .5rem; }
    .demo-chip { background: #eef0f6; color: var(--pk-muted); }

    /* KPI tiles */
    .kpi { padding: 1.1rem 1.15rem; height: 100%; display: flex; flex-direction: column; gap: .9rem; }
    .kpi .pk-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 10px; }
    .kpi .v { font-size: 1.75rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; font-variant-numeric: tabular-nums; }
    .kpi .l { font-size: .78rem; font-weight: 600; color: var(--pk-muted); margin-top: .35rem; }

    .peak { position: relative; overflow: hidden; color: #fff; border: 0; background:
            radial-gradient(420px 220px at 100% 0%, rgba(112, 70, 232, .5), transparent 70%),
            radial-gradient(380px 220px at 0% 100%, rgba(47, 91, 234, .45), transparent 70%), #0f1535; }
    .peak .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: rgba(226, 231, 255, .7); }
    .peak .v { font-size: 2.8rem; font-weight: 800; letter-spacing: -.04em; line-height: 1; }
    .peak p { color: rgba(226, 231, 255, .75); }
    .role-list { list-style: none; padding: 0; margin: 0; display: grid; gap: .9rem; }
    .role-list li { display: flex; gap: .8rem; align-items: flex-start; font-size: .9rem; color: var(--pk-ink-2); }
    .role-list .pk-icon { width: 34px; height: 34px; font-size: .95rem; border-radius: 10px; }

    /* Tables */
    .tbl-card { overflow: hidden; }
    .tbl-card .sec-head { padding: 1.15rem 1.35rem; margin: 0; border-bottom: 1px solid var(--pk-line); }
    .tbl-pk { font-size: .9rem; margin: 0; }
    .tbl-pk thead th { background: var(--pk-bg); border-bottom: 1px solid var(--pk-line); white-space: nowrap; }
    .tbl-pk > :not(caption) > * > * { padding: .95rem 1rem; }
    .tbl-pk > :not(caption) > * > *:first-child { padding-left: 1.35rem; }
    .tbl-pk > :not(caption) > * > *:last-child { padding-right: 1.35rem; }
    .tbl-pk tbody tr { transition: background .2s; }
    .tbl-pk tbody tr:hover { background: #fbfcff; }
    .tbl-pk tbody tr:last-child > * { border-bottom: 0; }
    .avatar { width: 36px; height: 36px; border-radius: 50%; display: inline-grid; place-items: center; font-weight: 800; font-size: .85rem; flex-shrink: 0;
        background: var(--pk-tint); color: var(--pk-blue); }
    .avatar.admin { background: var(--pk-ink); color: #fff; }
    .avatar.staff { background: var(--pk-tint-violet); color: var(--pk-violet); }
    .role-badge { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; font-weight: 700; font-size: .76rem; padding: .28rem .65rem; }
    .role-user { background: #eef0f6; color: var(--pk-ink-2); }
    .role-staff { background: var(--pk-tint-violet); color: var(--pk-violet); }
    .role-admin { background: var(--pk-ink); color: #fff; }
    .inline-form { display: flex; gap: .5rem; }
    .inline-form .form-select { padding-top: .42rem; padding-bottom: .42rem; font-size: .86rem; min-width: 0; }
    .inline-form .btn { font-size: .82rem; padding: .42rem .85rem; flex-shrink: 0; }
    .inline-form .btn-ghost { box-shadow: none; }
    .assign-pill { display: inline-flex; align-items: center; gap: .35rem; background: #fff; border: 1px solid var(--pk-line); border-radius: 999px;
        padding: .18rem .25rem .18rem .65rem; font-size: .8rem; font-weight: 600; color: var(--pk-ink-2); margin: 0 .3rem .4rem 0; }
    .assign-pill > i { color: var(--pk-violet); }
    .assign-pill button { border: 0; background: transparent; color: var(--pk-muted); width: 22px; height: 22px; border-radius: 50%; display: inline-grid; place-items: center; padding: 0; transition: background .2s, color .2s; }
    .assign-pill button:hover, .assign-pill button:focus-visible { background: var(--pk-danger-tint); color: var(--pk-danger); }
    .act-badge { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .74rem; font-weight: 600; background: var(--pk-bg); border: 1px solid var(--pk-line); color: var(--pk-ink-2); padding: .18rem .5rem; border-radius: 6px; white-space: nowrap; }
    .meta { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .74rem; color: var(--pk-muted); max-width: 280px; word-break: break-word; }

    /* Mobile: rows become stacked cards */
    @media (max-width: 767.98px) {
        .tbl-stack thead { display: none; }
        .tbl-stack, .tbl-stack tbody, .tbl-stack tr, .tbl-stack td { display: block; width: 100%; }
        .tbl-stack tr { padding: 1rem 1.1rem; border-bottom: 1px solid var(--pk-line); }
        .tbl-stack tbody tr:last-child { border-bottom: 0; }
        .tbl-pk.tbl-stack > :not(caption) > * > * { padding: .3rem 0 !important; border: 0; }
        .tbl-stack td[data-label]::before { content: attr(data-label); display: block; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--pk-muted); margin-bottom: .3rem; }
        .tbl-stack td.td-first { padding-bottom: .6rem !important; }
        .tbl-stack td.td-inline { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .tbl-stack td.td-inline::before { margin: 0; }
        .tbl-card .sec-head { padding: 1rem 1.1rem; }
        .meta { max-width: none; }
    }
</style>
@endpush

@section('content')
<header class="pk-page-head admin-head">
    <div class="container d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div class="fade-up">
            <span class="pk-eyebrow mb-2"><i class="bi bi-shield-lock"></i> Admin</span>
            <h1 class="mb-1">Admin dashboard</h1>
            <div class="pk-muted">Users, roles, staff assignments and the audit trail.</div>
        </div>
        <a href="{{ route('staff') }}" class="btn btn-ghost fade-up" style="animation-delay:.05s"><i class="bi bi-person-badge me-1"></i> Open staff console</a>
    </div>
</header>

<div class="container py-4 py-lg-5">
    {{-- Success and queue errors are shown by the layout toast; other validation errors appear here. --}}
    @if($errors->any() && ! $errors->has('queue'))
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="sec-head">
        <h2><i class="bi bi-speedometer2 pk-muted"></i> Overview</h2>
        <span class="chip demo-chip"><i class="bi bi-database"></i> Local demo data · not live operational statistics</span>
    </div>

    @php
        $kpis = [
            ['users', 'Users', 'bi-people', '', 0],
            ['active_queues', 'Active queues', 'bi-collection', 'violet', 0],
            ['waiting', 'Waiting now', 'bi-hourglass-split', 'amber', 0],
            ['live_entries', 'Live entries', 'bi-broadcast', 'rose', 0],
            ['completed', 'Completed', 'bi-check2-circle', 'green', 0],
            ['avg_service', 'Avg service (min)', 'bi-stopwatch', '', 1],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($kpis as $i => [$key, $label, $icon, $cls, $dec])
            @php $val = (float) ($stats[$key] ?? 0); @endphp
            <div class="col-6 col-md-4 col-xl-2 reveal" style="--d:{{ $i * 0.04 }}s">
                <div class="pk-card kpi">
                    <span class="pk-icon {{ $cls }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                    <div>
                        <div class="v js-count" data-target="{{ $val }}" data-decimals="{{ $dec }}">{{ number_format($val, $dec) }}</div>
                        <div class="l">{{ $label }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4 mb-lg-5">
        <div class="col-lg-4 reveal">
            <div class="pk-card peak p-4 h-100">
                <div class="lbl mb-3"><i class="bi bi-graph-up-arrow me-1"></i> Peak hour · last 30 days</div>
                @if($peak)
                    <div class="v mb-2">{{ str_pad($peak['hour'], 2, '0', STR_PAD_LEFT) }}:00</div>
                    <p class="small mb-0">{{ $peak['c'] }} {{ \Illuminate\Support\Str::plural('ticket', (int) $peak['c']) }} joined in this hour, the busiest in the demo data.</p>
                @else
                    <div class="v mb-2">—</div>
                    <p class="small mb-0">Not enough ticket history yet to find a peak hour.</p>
                @endif
            </div>
        </div>
        <div class="col-lg-8 reveal" style="--d:.06s">
            <div class="pk-card p-4 h-100">
                <h2 class="h6 fw-bold mb-3">How roles work</h2>
                <ul class="role-list">
                    <li><span class="pk-icon"><i class="bi bi-person"></i></span><div><b class="text-body">User</b><br><span class="pk-muted">Joins queues and tracks tickets.</span></div></li>
                    <li><span class="pk-icon violet"><i class="bi bi-person-badge"></i></span><div><b class="text-body">Staff</b><br><span class="pk-muted">Runs queues only at the locations they are assigned to below.</span></div></li>
                    <li><span class="pk-icon" style="background:#e9ebf5;color:var(--pk-ink)"><i class="bi bi-shield-lock"></i></span><div><b class="text-body">Admin</b><br><span class="pk-muted">Manages roles and assignments. Every change is written to the audit log.</span></div></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ================= Users & roles ================= --}}
    <section class="pk-card tbl-card mb-4 mb-lg-5 reveal" aria-labelledby="usersHead">
        <div class="sec-head">
            <h2 id="usersHead"><i class="bi bi-person-gear pk-muted"></i> Users &amp; roles <span class="chip demo-chip py-0">{{ $users->count() }}</span></h2>
            <span class="small pk-muted">Admins can access every location</span>
        </div>
        <table class="table tbl-pk tbl-stack align-middle">
            <thead>
                <tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Change role</th><th scope="col">Location assignments</th><th scope="col">Joined</th></tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td class="td-first">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar {{ $u->role }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <div class="fw-bold text-truncate">{{ $u->name }}</div>
                                    <div class="small pk-muted text-truncate">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="td-inline" data-label="Role"><span class="role-badge role-{{ $u->role }}">{{ ucfirst($u->role) }}</span></td>
                        <td data-label="Change role" style="min-width: 190px">
                            <form method="POST" action="{{ route('admin.role', $u) }}" class="inline-form">
                                @csrf
                                <label for="role-{{ $u->id }}" class="visually-hidden">Role for {{ $u->name }}</label>
                                <select id="role-{{ $u->id }}" name="role" class="form-select form-select-sm">
                                    @foreach(['user', 'staff', 'admin'] as $r)
                                        <option value="{{ $r }}" @selected($u->role === $r)>{{ ucfirst($r) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-ghost btn-sm">Save</button>
                            </form>
                        </td>
                        <td data-label="Location assignments" style="min-width: 280px">
                            @if($u->role === 'staff')
                                <div>
                                    @forelse($u->staffAssignments as $a)
                                        <span class="assign-pill">
                                            <i class="bi bi-geo-alt-fill"></i> {{ $a->location->name ?? 'Unknown location' }}
                                            <form method="POST" action="{{ route('admin.unassign', $a) }}" class="d-inline js-confirm" data-confirm="Remove {{ $u->name }} from {{ $a->location->name ?? 'this location' }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" aria-label="Remove {{ $u->name }} from {{ $a->location->name ?? 'location' }}"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </span>
                                    @empty
                                        <div class="small pk-muted mb-2">No locations assigned yet.</div>
                                    @endforelse
                                </div>
                                @php $assignedIds = $u->staffAssignments->pluck('location_id')->filter()->all(); @endphp
                                <form method="POST" action="{{ route('admin.assign', $u) }}" class="inline-form">
                                    @csrf
                                    <label for="loc-{{ $u->id }}" class="visually-hidden">Assign {{ $u->name }} to location</label>
                                    <select id="loc-{{ $u->id }}" name="location_id" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>Assign location…</option>
                                        @foreach($locations as $l)
                                            <option value="{{ $l->id }}" @disabled(in_array($l->id, $assignedIds))>{{ $l->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-pk btn-sm">Assign</button>
                                </form>
                            @elseif($u->role === 'admin')
                                <span class="small pk-muted"><i class="bi bi-globe2 me-1"></i>All locations</span>
                            @else
                                <span class="small pk-muted">—</span>
                            @endif
                        </td>
                        <td class="td-inline small pk-muted text-nowrap" data-label="Joined">{{ $u->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center pk-muted py-4">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- ================= Audit log ================= --}}
    <section class="pk-card tbl-card reveal" aria-labelledby="auditHead">
        <div class="sec-head">
            <h2 id="auditHead"><i class="bi bi-journal-text pk-muted"></i> Audit log</h2>
            <span class="small pk-muted">Most recent 15 entries</span>
        </div>
        <table class="table tbl-pk tbl-stack align-middle">
            <thead>
                <tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Subject</th><th scope="col">Details</th><th scope="col">IP</th></tr>
            </thead>
            <tbody>
                @forelse($audit->take(15) as $log)
                    <tr>
                        <td class="td-inline small text-nowrap" data-label="When" title="{{ $log->created_at?->toDateTimeString() }}">{{ $log->created_at?->diffForHumans() }}</td>
                        <td class="td-inline small fw-semibold" data-label="Actor">{{ $log->actor->name ?? 'System' }}</td>
                        <td class="td-inline" data-label="Action"><span class="act-badge">{{ $log->action }}</span></td>
                        <td class="td-inline small" data-label="Subject">{{ $log->subject_type ? class_basename($log->subject_type) : '—' }}@if($log->subject_id) <span class="pk-muted">#{{ $log->subject_id }}</span>@endif</td>
                        <td class="meta" data-label="Details">
                            @if(!empty($log->meta))
                                @foreach((array) $log->meta as $k => $v)
                                    <div>{{ $k }}: {{ is_scalar($v) || is_null($v) ? var_export($v, true) : json_encode($v) }}</div>
                                @endforeach
                            @else
                                —
                            @endif
                        </td>
                        <td class="td-inline small pk-muted" data-label="IP">{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center pk-muted py-4">No audit entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- Module summaries --}}
    <section class="mt-4">
        <h2 class="h5 mb-3">Modules</h2>
        <div class="row g-4">
            @foreach(['platform.partials.admin-card', 'integrity.partials.admin-card', 'ops.partials.admin-card', 'games.partials.admin-card', 'ads.partials.admin-card'] as $card)
                @if(view()->exists($card))<div class="col-lg-6">@include($card)</div>@endif
            @endforeach
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('form.js-confirm').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // One-shot count-up for KPI numbers (skipped for reduced motion).
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.js-count').forEach(function (el) {
        var target = parseFloat(el.dataset.target) || 0, dec = parseInt(el.dataset.decimals || '0', 10);
        if (reduce || target === 0) return;
        var start = null, dur = 700;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1), eased = 1 - Math.pow(1 - p, 3);
            el.textContent = (target * eased).toLocaleString(undefined, { minimumFractionDigits: dec, maximumFractionDigits: dec });
            if (p < 1) requestAnimationFrame(step);
        }
        el.textContent = (0).toFixed(dec);
        requestAnimationFrame(step);
    });
})();
</script>
@endpush
