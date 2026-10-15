{{--
    Game participation stats for the admin dashboard. Usage: @include('games.partials.admin-card')
    Self-contained; renders nothing if the games tables are not migrated yet.
--}}
@php
    try {
        $gmStats = app(\App\Services\Games\LeaderboardService::class)->stats();
    } catch (\Throwable $e) {
        $gmStats = null;
    }
@endphp
@if($gmStats)
<div class="pk-card p-3 p-md-4 h-100">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="pk-icon violet"><i class="bi bi-controller"></i></span>
        <div class="flex-grow-1">
            <div class="fw-bold">Game participation</div>
            <div class="small" style="color:var(--pk-muted)">Quick Brain Battle (multiplayer)</div>
        </div>
        @if($gmStats['live'])<span class="chip" style="background:var(--pk-success-tint);color:var(--pk-success)"><span class="live-dot"></span> {{ $gmStats['live'] }} live</span>@endif
    </div>
    <div class="row g-2 text-center">
        <div class="col-4"><div class="h5 mb-0">{{ number_format($gmStats['completed']) }}</div><div class="small" style="color:var(--pk-muted)">Battles</div></div>
        <div class="col-4"><div class="h5 mb-0">{{ number_format($gmStats['players']) }}</div><div class="small" style="color:var(--pk-muted)">Players</div></div>
        <div class="col-4"><div class="h5 mb-0">{{ number_format($gmStats['points_awarded']) }}</div><div class="small" style="color:var(--pk-muted)">WP awarded</div></div>
    </div>
    <div class="d-flex justify-content-between small mt-3 pt-2 border-top" style="color:var(--pk-muted)">
        <span>{{ $gmStats['this_week'] }} this week · {{ number_format($gmStats['answers']) }} answers</span>
        <span>{{ $gmStats['questions'] }} approved Qs @if($gmStats['pending_questions'])· <b style="color:var(--pk-warn)">{{ $gmStats['pending_questions'] }} pending</b>@endif</span>
    </div>
    <a href="{{ route('games.admin.questions') }}" class="btn btn-ghost btn-sm w-100 mt-3"><i class="bi bi-ui-checks me-1"></i>Manage question bank</a>
</div>
@endif
