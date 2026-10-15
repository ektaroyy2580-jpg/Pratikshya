{{--
    Ops quick card for the staff console. Include with ['service' => $service].
    Renders nothing unless the viewer operates the service's location.
--}}
@php
    $opsUser = auth()->user();
    $opsOk = isset($service) && $service && \App\Services\Ops\OpsAccess::canOperate($opsUser, $service->location_id);
    if ($opsOk) {
        $opsRecent = \App\Models\OperationalIncident::where('location_id', $service->location_id)->unresolved()
            ->with('counter')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest('reported_at')->limit(3)->get();
        $opsOpenCount = \App\Models\OperationalIncident::where('location_id', $service->location_id)->unresolved()->count();
        $opsLive = \App\Models\ServiceAnnouncement::live()->forService($service)->count();
    }
@endphp
@if($opsOk)
    @once
        @push('head')
            <link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
        @endpush
    @endonce
    <section class="pk-card p-4" aria-labelledby="opsCardHead">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <h2 id="opsCardHead" class="h6 fw-bold mb-0 d-flex align-items-center gap-2"><i class="bi bi-broadcast pk-muted"></i> Office operations</h2>
            <a href="{{ route('ops.incidents.index', ['location' => $service->location_id]) }}" class="small fw-bold text-decoration-none">All <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="d-grid gap-2 mb-3" style="grid-template-columns: 1fr 1fr;">
            <a href="{{ route('ops.incidents.create', ['location' => $service->location_id, 'service' => $service->id]) }}" class="btn btn-ghost btn-sm"><i class="bi bi-exclamation-triangle me-1"></i>Report incident</a>
            <a href="{{ route('ops.notices.index', ['location' => $service->location_id, 'service' => $service->id]) }}" class="btn btn-ghost btn-sm"><i class="bi bi-megaphone me-1"></i>Publish notice</a>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-2 small">
            <span class="chip" style="{{ $opsOpenCount ? 'background:var(--pk-danger-tint);color:var(--pk-danger)' : 'background:var(--pk-success-tint);color:var(--pk-success)' }}"><i class="bi {{ $opsOpenCount ? 'bi-exclamation-octagon' : 'bi-check2-circle' }}"></i>{{ $opsOpenCount }} open incident{{ $opsOpenCount === 1 ? '' : 's' }}</span>
            <span class="chip"><i class="bi bi-megaphone"></i>{{ $opsLive }} live notice{{ $opsLive === 1 ? '' : 's' }}</span>
        </div>
        @foreach($opsRecent as $i)
            <a href="{{ route('ops.incidents.show', $i) }}" class="d-flex align-items-center justify-content-between gap-2 py-2 border-top text-decoration-none" style="color:inherit">
                <span class="min-w-0 small"><i class="bi {{ $i->categoryIcon() }} me-1 pk-muted"></i><span class="fw-bold">{{ $i->categoryLabel() }}</span>@if($i->counter)<span class="pk-muted"> · C{{ $i->counter->number }}</span>@endif<span class="pk-muted d-block" style="font-size:.76rem">{{ $i->reported_at?->diffForHumans() }}</span></span>
                <span class="prio prio-{{ $i->priority }} flex-shrink-0">{{ $i->priority }}</span>
            </a>
        @endforeach
        <a href="{{ route('ops.qr.poster', $service) }}" class="small fw-bold text-decoration-none d-inline-flex align-items-center gap-1 mt-2"><i class="bi bi-qr-code"></i> QR poster for this queue</a>
    </section>
@endif
