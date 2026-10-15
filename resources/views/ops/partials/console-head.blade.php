{{-- Ops console header with section tabs. Params: $active (incidents|notices|qr|overview|create), $title, $subtitle (optional) --}}
@php
    $opsUser = auth()->user();
    $opsOpen = \App\Models\OperationalIncident::visibleTo($opsUser)->unresolved()->count();
    $tabs = [
        ['incidents', 'Incidents', 'bi-exclamation-triangle', route('ops.incidents.index'), $opsOpen],
        ['notices', 'Public notices', 'bi-megaphone', route('ops.notices.index'), null],
        ['qr', 'QR posters', 'bi-qr-code', route('ops.qr.index'), null],
    ];
    if ($opsUser->isAdmin()) {
        $tabs[] = ['overview', 'Overview', 'bi-bar-chart-line', route('ops.overview'), null];
    }
@endphp
<header class="pk-page-head ops-head">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="fade-up min-w-0">
                <span class="pk-eyebrow mb-2"><i class="bi bi-broadcast"></i> Office operations</span>
                <h1 class="mb-1">{{ $title }}</h1>
                @if(!empty($subtitle))<div class="pk-muted">{{ $subtitle }}</div>@endif
            </div>
            <div class="d-flex flex-wrap gap-2 fade-up" style="animation-delay:.05s">
                <a href="{{ route('staff') }}" class="btn btn-ghost btn-sm"><i class="bi bi-person-badge me-1"></i>Queue console</a>
                @if(($active ?? '') !== 'create')
                    <a href="{{ route('ops.incidents.create') }}" class="btn btn-pk btn-sm"><i class="bi bi-plus-lg me-1"></i>Report incident</a>
                @endif
            </div>
        </div>
        <nav class="ops-tabs" aria-label="Operations sections">
            @foreach($tabs as [$key, $label, $icon, $href, $cnt])
                <a href="{{ $href }}" class="{{ ($active ?? '') === $key ? 'active' : '' }}" @if(($active ?? '') === $key) aria-current="page" @endif>
                    <i class="bi {{ $icon }}"></i>{{ $label }}@if($cnt)<span class="cnt" aria-label="{{ $cnt }} unresolved">{{ $cnt }}</span>@endif
                </a>
            @endforeach
        </nav>
    </div>
</header>
