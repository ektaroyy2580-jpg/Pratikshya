{{-- Ops summary card for the admin dashboard. No variables needed; renders nothing for non-admins. --}}
@if(auth()->user()?->isAdmin())
    @php
        $opsUnresolved = \App\Models\OperationalIncident::unresolved()->count();
        $opsUrgent = \App\Models\OperationalIncident::unresolved()->whereIn('priority', ['high', 'critical'])->count();
        $opsLive = \App\Models\ServiceAnnouncement::live()->count();
    @endphp
    <section class="pk-card p-4" aria-labelledby="opsAdminHead">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 id="opsAdminHead" class="h6 fw-bold mb-0 d-flex align-items-center gap-2"><i class="bi bi-broadcast pk-muted"></i> Office operations</h2>
            <a href="{{ route('ops.overview') }}" class="small fw-bold text-decoration-none">Overview <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-2 text-center">
            <div class="col-4"><div class="fw-bold fs-4">{{ $opsUnresolved }}</div><div class="small pk-muted">Unresolved</div></div>
            <div class="col-4"><div class="fw-bold fs-4" style="color:{{ $opsUrgent ? 'var(--pk-danger)' : 'inherit' }}">{{ $opsUrgent }}</div><div class="small pk-muted">High / critical</div></div>
            <div class="col-4"><div class="fw-bold fs-4">{{ $opsLive }}</div><div class="small pk-muted">Live notices</div></div>
        </div>
    </section>
@endif
