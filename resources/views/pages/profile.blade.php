@extends('layouts.app')
@section('title', $user->name.' — प्रतीक्षा')

@push('head')
<style>
    .min-w-0 { min-width: 0; }
    .avatar-xl { width: 72px; height: 72px; border-radius: 22px; display: grid; place-items: center; font-size: 1.9rem; font-weight: 800; color: #fff; background: var(--pk-grad); box-shadow: 0 14px 28px -14px rgba(47, 91, 234, .8); flex-shrink: 0; }
    .prof-head h1 { font-size: clamp(1.6rem, 3.6vw, 2.3rem); line-height: 1.1; }
    @media (max-width: 575.98px) { .head-actions { width: 100%; } .head-actions > * { flex: 1; } .avatar-xl { width: 60px; height: 60px; font-size: 1.6rem; border-radius: 18px; } }

    .stat-tile { background: #fff; border: 1px solid var(--pk-line); border-radius: 16px; padding: 1rem 1.1rem; height: 100%; box-shadow: var(--pk-shadow-sm); display: flex; align-items: center; gap: .9rem; }
    .stat-tile .v { font-size: 1.6rem; font-weight: 800; letter-spacing: -.03em; line-height: 1.05; }
    .stat-tile .l { font-size: .78rem; font-weight: 600; color: var(--pk-muted); }
    @media (max-width: 575.98px) { .stat-tile { flex-direction: column; align-items: flex-start; gap: .6rem; padding: .9rem; } .stat-tile .pk-icon { width: 36px; height: 36px; font-size: 1rem; } .stat-tile .v { font-size: 1.35rem; } }

    .panel-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--pk-line); }
    .panel-head h2 { font-size: 1.05rem; font-weight: 800; letter-spacing: -.015em; margin: 0; }
    .list-row { display: flex; align-items: center; gap: .9rem; padding: .9rem 1.25rem; text-decoration: none; color: inherit; transition: background .2s; position: relative; }
    .list-row + .list-row { border-top: 1px solid #f0f1f7; }
    .list-row:hover { background: #f9faff; color: inherit; }
    .list-row:focus-visible { outline-offset: -3px; border-radius: 0; }
    .list-row:last-child { border-radius: 0 0 var(--pk-radius) var(--pk-radius); }
    .list-row.unread { background: #f5f7ff; }
    .list-row.unread::before { content: ''; position: absolute; left: 0; top: 12px; bottom: 12px; width: 3px; border-radius: 0 3px 3px 0; background: var(--pk-grad); }
    .list-row .t { font-weight: 600; line-height: 1.35; font-size: .92rem; }
    .list-row .s { font-size: .78rem; color: var(--pk-muted); font-weight: 600; margin-top: .15rem; }
    .new-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--pk-blue); flex-shrink: 0; }
    .list-row .pk-icon { width: 38px; height: 38px; font-size: 1rem; border-radius: 11px; }

    .tok-badge { min-width: 64px; height: 44px; padding: 0 .6rem; border-radius: 12px; display: grid; place-items: center; background: var(--pk-bg); border: 1px solid var(--pk-line); font-weight: 800; letter-spacing: -.02em; flex-shrink: 0; }
    .pts { font-weight: 800; color: var(--pk-violet); font-size: .85rem; white-space: nowrap; }
    .pk-status.completed { background: var(--pk-success-tint); color: var(--pk-success); }
    .pk-status.absent, .pk-status.cancelled { background: var(--pk-danger-tint); color: var(--pk-danger); }
    .pk-status.left { background: #eef0f6; color: var(--pk-muted); }
    @media (max-width: 575.98px) { .list-row { padding: .85rem 1rem; gap: .75rem; } .panel-head { padding: 1rem; } }

    .empty { text-align: center; padding: 2.5rem 1.25rem; }
    .empty .pk-icon { width: 52px; height: 52px; font-size: 1.4rem; border-radius: 15px; }
</style>
@endpush

@section('content')
@php
    $unread = $notifications->whereNull('read_at')->count();
    $nIcons = [
        'turn_called' => ['bi-megaphone-fill', 'green'],
        'turn_next' => ['bi-bell-fill', ''],
        'approaching' => ['bi-hourglass-split', 'violet'],
        'counter_changed' => ['bi-arrow-left-right', 'violet'],
        'turn_completed' => ['bi-check2-circle', 'green'],
        'turn_cancelled' => ['bi-x-circle', 'rose'],
        'turn_absent' => ['bi-exclamation-circle', 'amber'],
    ];
    $statusMap = [
        'waiting' => ['waiting', 'Waiting'],
        'called' => ['called', 'Called'],
        'serving' => ['serving', 'Being served'],
        'completed' => ['completed', 'Completed'],
        'absent' => ['absent', 'Absent'],
        'cancelled' => ['cancelled', 'Cancelled'],
        'left' => ['left', 'Left queue'],
    ];
@endphp

<header class="pk-page-head prof-head">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                <div class="avatar-xl fade-up" aria-hidden="true">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                <div class="min-w-0 fade-up" style="animation-delay:.05s">
                    <div class="pk-eyebrow mb-1">My account</div>
                    <h1 class="mb-1 text-truncate">{{ $user->name }}</h1>
                    <div class="small pk-muted fw-semibold text-truncate">{{ $user->email }}<span class="d-none d-sm-inline"> · Member since {{ $user->created_at->format('M Y') }}</span></div>
                </div>
            </div>
            <div class="head-actions d-flex gap-2 fade-up" style="animation-delay:.1s">
                <a href="{{ route('find') }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i>Join a queue</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost w-100" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Log out</button></form>
            </div>
        </div>

        <div class="row g-2 g-md-3">
            <div class="col-4 reveal"><div class="stat-tile"><span class="pk-icon violet"><i class="bi bi-trophy"></i></span><div><div class="v">{{ number_format($points) }}</div><div class="l">WaitPoints</div></div></div></div>
            <div class="col-4 reveal" style="--d:.06s"><div class="stat-tile"><span class="pk-icon green"><i class="bi bi-clock-history"></i></span><div><div class="v">{{ number_format($reclaimed) }}</div><div class="l">minutes reclaimed</div></div></div></div>
            <div class="col-4 reveal" style="--d:.12s"><div class="stat-tile"><span class="pk-icon"><i class="bi bi-ticket-perforated"></i></span><div><div class="v">{{ $tickets->count() }}</div><div class="l">queues joined</div></div></div></div>
        </div>
    </div>
</header>

<div class="container py-4 py-lg-5">
    @if(session('ok'))
        <div class="alert alert-success d-flex flex-wrap align-items-center gap-2 mb-4 fade-up" role="status">
            <i class="bi bi-check-circle-fill"></i><span class="flex-grow-1">{{ session('ok') }}</span>
            <a href="{{ route('find') }}" class="btn btn-sm btn-pk">Join a queue</a>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7 order-2 order-lg-1">
            <section class="pk-card reveal" aria-labelledby="visitsTitle">
                <div class="panel-head">
                    <h2 id="visitsTitle">Visit history</h2>
                    <span class="chip">{{ $tickets->count() }}</span>
                </div>
                @forelse($tickets as $t)
                    @php [$cls, $label] = $statusMap[$t->status] ?? ['left', ucfirst($t->status)]; @endphp
                    <a href="{{ route('ticket', $t) }}" class="list-row">
                        <span class="tok-badge">{{ $t->token() }}</span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="t d-block fw-bold">{{ $t->service->name }}</span>
                            <span class="s d-block">{{ $t->service->location->name }} · {{ $t->created_at->diffForHumans() }}</span>
                            <span class="d-flex d-sm-none align-items-center gap-2 mt-2"><span class="pk-status {{ $cls }}">{{ $label }}</span><span class="pts">+{{ $t->game_points + ($t->status === 'completed' ? 50 : 0) }} pts</span></span>
                        </span>
                        <span class="visit-side d-none d-sm-flex align-items-center gap-3 flex-shrink-0">
                            <span class="pk-status {{ $cls }}">{{ $label }}</span>
                            <span class="pts">+{{ $t->game_points + ($t->status === 'completed' ? 50 : 0) }} pts</span>
                        </span>
                    </a>
                @empty
                    <div class="empty">
                        <span class="pk-icon mb-3"><i class="bi bi-ticket-perforated"></i></span>
                        <div class="fw-bold mb-1">No visits yet</div>
                        <p class="small pk-muted mb-3">Your tokens and past visits will show up here.</p>
                        <a href="{{ route('find') }}" class="btn btn-pk btn-sm">Join your first queue</a>
                    </div>
                @endforelse
            </section>
        </div>

        <div class="col-lg-5 order-1 order-lg-2">
            <section class="pk-card reveal" style="--d:.06s" aria-labelledby="notifTitle">
                <div class="panel-head">
                    <h2 id="notifTitle">Notifications</h2>
                    @if($unread)
                        <span class="chip">{{ $unread }} new</span>
                    @else
                        <span class="chip" style="background:#eef0f6;color:var(--pk-muted)">{{ $notifications->count() }}</span>
                    @endif
                </div>
                @forelse($notifications->take(12) as $n)
                    @php $d = $n->data; [$ico, $tone] = $nIcons[$d['kind'] ?? ''] ?? ['bi-bell', '']; $isNew = $n->read_at === null; @endphp
                    <a href="{{ $d['url'] ?? (isset($d['ticket_id']) ? route('ticket', $d['ticket_id']) : route('profile')) }}" class="list-row {{ $isNew ? 'unread' : '' }}">
                        <span class="pk-icon {{ $tone }}"><i class="bi {{ $ico }}"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="t d-block {{ $isNew || in_array($d['kind'] ?? '', ['turn_called', 'turn_next']) ? 'fw-bold' : '' }}">{{ $d['message'] ?? '' }}</span>
                            <span class="s d-block">{{ $d['token'] ?? '' }} · {{ $n->created_at->diffForHumans() }}</span>
                        </span>
                        @if($isNew)<span class="new-dot" aria-label="Unread"></span>@endif
                    </a>
                @empty
                    <div class="empty">
                        <span class="pk-icon violet mb-3"><i class="bi bi-bell-slash"></i></span>
                        <div class="fw-bold mb-1">No notifications yet</div>
                        <p class="small pk-muted mb-0">Join a queue to get turn alerts here.</p>
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection
