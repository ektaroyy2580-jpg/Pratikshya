{{-- Platform: entry card for the admin dashboard. Self-contained; renders nothing for non-managers. --}}
@php $pfAc = app(\App\Services\Platform\OrgAccess::class); $pfMe = auth()->user(); @endphp
@if($pfMe && $pfAc->isManager($pfMe))
<section class="pk-card p-3 p-md-4" aria-label="Management console">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="pk-icon violet"><i class="bi bi-grid-1x2"></i></span>
        <div class="min-w-0"><h2 class="h6 mb-0">Management console</h2><div class="small pk-muted">Organizations, locations, services, counters, staff, places{{ $pfAc->isPlatformAdmin($pfMe) ? ', users, audit & AI' : '' }}.</div></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('platform.manage.index') }}" class="btn btn-pk btn-sm">Open console</a>
        @if($pfAc->isPlatformAdmin($pfMe))
            <a href="{{ route('platform.analytics') }}" class="btn btn-ghost btn-sm"><i class="bi bi-graph-up me-1"></i>Analytics</a>
            <a href="{{ route('platform.manage.audit') }}" class="btn btn-ghost btn-sm"><i class="bi bi-journal-text me-1"></i>Audit log</a>
        @endif
    </div>
</section>
@endif
