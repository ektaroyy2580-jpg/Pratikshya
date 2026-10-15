@extends('layouts.app')

@push('head')
<link href="{{ asset('css/modules/platform.css') }}?v={{ filemtime(public_path('css/modules/platform.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $pfUser = auth()->user();
    $pfAccess = app(\App\Services\Platform\OrgAccess::class);
    $pfIsAdmin = $pfAccess->isPlatformAdmin($pfUser);
    $pfOrgIds = $pfAccess->membershipOrgIds($pfUser);
    $pfOrgNames = $pfIsAdmin ? collect() : \App\Models\Organization::whereIn('id', $pfOrgIds)->pluck('name');
    $pfLocIds = $pfAccess->scopeLocationIds($pfUser);
    $pfPending = \App\Models\StaffAssignment::where('is_approved', false)->when($pfLocIds !== null, fn ($q) => $q->whereIn('location_id', $pfLocIds ?: [0]))->count();
    $pfNav = [
        'Console' => [
            ['Overview', 'bi-grid-1x2', 'platform.manage.index', true],
            ['Analytics', 'bi-graph-up', 'platform.analytics', $pfIsAdmin],
        ],
        'Operations' => [
            ['Organizations', 'bi-buildings', 'platform.manage.organizations', true],
            ['Locations & services', 'bi-geo-alt', 'platform.manage.locations', true],
            ['Staff assignments', 'bi-person-badge', 'platform.manage.staff', true],
            ['Nearby & listings', 'bi-shop', 'platform.manage.places', true],
        ],
        'Platform' => [
            ['Users & roles', 'bi-people', 'platform.manage.users', $pfIsAdmin],
            ['Audit log', 'bi-journal-text', 'platform.manage.audit', $pfIsAdmin],
            ['Integrations & AI', 'bi-plug', 'platform.manage.integrations', $pfIsAdmin],
        ],
    ];
    $pfActive = fn ($route) => request()->routeIs($route) || request()->routeIs($route.'.*');
@endphp
<div class="container pb-4">
    <div class="pf-shell">
        <aside class="pf-side" aria-label="Console navigation">
            <button class="btn btn-ghost pf-menu-btn" type="button" data-bs-toggle="collapse" data-bs-target="#pfSide" aria-expanded="false" aria-controls="pfSide">
                <span class="d-flex align-items-center gap-2"><i class="bi bi-layout-sidebar"></i> Console menu</span><i class="bi bi-chevron-down"></i>
            </button>
            <div class="collapse pf-side-collapse" id="pfSide">
                <div class="pk-card">
                    <nav>
                        @foreach($pfNav as $group => $links)
                            @php $visible = collect($links)->filter(fn ($l) => $l[3]); @endphp
                            @if($visible->isNotEmpty())
                                <div class="grp">{{ $group }}</div>
                                @foreach($visible as [$label, $icon, $route])
                                    <a href="{{ route($route) }}" class="pf-nav-link {{ $pfActive($route) ? 'active' : '' }}" @if($pfActive($route)) aria-current="page" @endif>
                                        <i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}
                                        @if($route === 'platform.manage.staff' && $pfPending)<span class="count" aria-label="{{ $pfPending }} pending">{{ $pfPending }}</span>@endif
                                    </a>
                                @endforeach
                            @endif
                        @endforeach
                        @if($pfIsAdmin && Route::has('admin'))
                            <div class="grp">Shortcuts</div>
                            <a href="{{ route('admin') }}" class="pf-nav-link"><i class="bi bi-speedometer2" aria-hidden="true"></i>Classic admin</a>
                        @endif
                        @if(Route::has('staff') && in_array($pfUser->role, ['staff', 'admin'], true))
                            <a href="{{ route('staff') }}" class="pf-nav-link"><i class="bi bi-person-workspace" aria-hidden="true"></i>Staff portal</a>
                        @endif
                    </nav>
                    <div class="pf-scope">
                        @if($pfIsAdmin)
                            <strong><i class="bi bi-shield-check me-1"></i>Platform admin</strong>Full access to every organization.
                        @else
                            <strong><i class="bi bi-building me-1"></i>Organization admin</strong>{{ $pfOrgNames->join(', ') ?: 'No organization' }}
                        @endif
                    </div>
                </div>
            </div>
        </aside>
        <div class="min-w-0">
            @if($errors->any() && ! $errors->has('queue'))
                <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert">
                    <i class="bi bi-exclamation-octagon-fill mt-1"></i>
                    <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                </div>
            @endif
            @yield('console')
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/platform-console.js') }}?v={{ filemtime(public_path('js/modules/platform-console.js')) }}"></script>
@endpush
