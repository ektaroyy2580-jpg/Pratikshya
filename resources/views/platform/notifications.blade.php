@extends('layouts.app')
@section('title', 'Notifications — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/platform.css') }}?v={{ filemtime(public_path('css/modules/platform.css')) }}" rel="stylesheet">
<style>
    .nt-row { display: flex; align-items: flex-start; gap: .9rem; padding: 1rem 1.25rem; position: relative; transition: background .25s, opacity .25s; }
    .nt-row + .nt-row { border-top: 1px solid #f0f1f7; }
    .nt-row.unread { background: #f5f7ff; }
    .nt-row.unread::before { content: ''; position: absolute; left: 0; top: 14px; bottom: 14px; width: 3px; border-radius: 0 3px 3px 0; background: var(--pk-grad); }
    .nt-row .pk-icon { width: 40px; height: 40px; font-size: 1.05rem; border-radius: 12px; }
    .nt-msg { font-weight: 600; line-height: 1.4; font-size: .93rem; }
    .nt-meta { font-size: .76rem; color: var(--pk-muted); font-weight: 600; margin-top: .25rem; display: flex; flex-wrap: wrap; gap: .25rem .6rem; align-items: center; }
    .nt-actions { display: flex; gap: .35rem; flex-shrink: 0; }
    .nt-tabs .btn { border-radius: 999px; padding: .45rem 1.05rem !important; font-size: .85rem; line-height: 1.2; }
    .nt-date { font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--pk-muted); padding: .8rem 1.25rem .4rem; background: var(--pk-bg); border-top: 1px solid var(--pk-line); }
    .nt-date:first-child { border-top: 0; }
    @media (max-width: 575.98px) { .nt-row { padding: .9rem 1rem; flex-wrap: wrap; } .nt-actions { width: 100%; padding-left: calc(40px + .9rem); } }
</style>
@endpush

@section('content')
<header class="pk-page-head pf-head">
    <div class="container d-flex flex-wrap justify-content-between align-items-end gap-3 fade-up">
        <div>
            <a href="{{ route('platform.dashboard') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <h1>Notifications</h1>
            <div class="pk-muted small"><span id="unreadLabel">{{ $unread }}</span> unread · {{ $total }} total</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($unread)
                <form method="POST" action="{{ route('platform.notifications.read-all') }}" id="readAllForm">@csrf<button class="btn btn-ghost btn-sm"><i class="bi bi-check2-all me-1"></i> Mark all read</button></form>
            @endif
            <a href="{{ route('platform.settings') }}#notifications" class="btn btn-ghost btn-sm"><i class="bi bi-sliders me-1"></i> Channels</a>
        </div>
    </div>
</header>

<div class="container py-4" style="max-width: 860px">
    <div class="d-flex gap-2 mb-3 nt-tabs" role="tablist" aria-label="Filter notifications">
        <a href="{{ route('platform.notifications') }}" class="btn btn-sm {{ $filter === 'all' ? 'btn-pk' : 'btn-ghost' }}" role="tab" aria-selected="{{ $filter === 'all' ? 'true' : 'false' }}">All</a>
        <a href="{{ route('platform.notifications', ['filter' => 'unread']) }}" class="btn btn-sm {{ $filter === 'unread' ? 'btn-pk' : 'btn-ghost' }}" role="tab" aria-selected="{{ $filter === 'unread' ? 'true' : 'false' }}">Unread @if($unread)<span class="badge bg-white text-primary ms-1">{{ $unread }}</span>@endif</a>
    </div>

    <section class="pk-card pf-panel reveal" aria-label="Notification list">
        @if($items->isEmpty())
            <div class="pf-empty">
                <span class="pk-icon green mb-2"><i class="bi bi-bell-slash"></i></span>
                <h2 class="h6 mb-1">{{ $filter === 'unread' ? 'You are all caught up' : 'No notifications yet' }}</h2>
                <p class="pk-muted small mb-3">{{ $filter === 'unread' ? 'Nothing new since you last checked.' : 'Join a queue and we will tell you when your turn is near.' }}</p>
                @if(Route::has('find'))<a href="{{ route('find') }}" class="btn btn-pk btn-sm">Find a service</a>@endif
            </div>
        @else
            @php $lastDay = null; @endphp
            @foreach($items as $n)
                @php $day = $n['when']?->isToday() ? 'Today' : ($n['when']?->isYesterday() ? 'Yesterday' : $n['when']?->format('d M Y')); @endphp
                @if($day !== $lastDay)<div class="nt-date">{{ $day }}</div>@php $lastDay = $day; @endphp @endif
                <article class="nt-row {{ $n['read'] ? '' : 'unread' }}" data-id="{{ $n['id'] }}">
                    <span class="pk-icon {{ $n['tone'] }}" aria-hidden="true"><i class="bi {{ $n['icon'] }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="nt-msg">{{ $n['message'] }}</div>
                        <div class="nt-meta">
                            <span>{{ $n['group'] }}</span>
                            @if($n['token'])<span class="chip py-0 px-2">{{ $n['token'] }}</span>@endif
                            <span title="{{ $n['when'] }}">{{ $n['when']?->diffForHumans() }}</span>
                            @unless($n['read'])<span class="visually-hidden">Unread</span>@endunless
                        </div>
                    </div>
                    <div class="nt-actions">
                        @if($n['url'])
                            <form method="POST" action="{{ route('platform.notifications.read', $n['id']) }}" data-no-busy>
                                @csrf<input type="hidden" name="open" value="1">
                                <button class="btn btn-ghost btn-xs">Open <i class="bi bi-arrow-right"></i></button>
                            </form>
                        @endif
                        @unless($n['read'])
                            <form method="POST" action="{{ route('platform.notifications.read', $n['id']) }}" class="js-read" data-no-busy>
                                @csrf<button class="btn btn-ghost btn-xs" aria-label="Mark as read"><i class="bi bi-check2"></i></button>
                            </form>
                        @endunless
                    </div>
                </article>
            @endforeach
            @if($page->hasPages())<div class="pf-pager">{{ $page->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const label = document.getElementById('unreadLabel');
    document.querySelectorAll('form.js-read').forEach(f => f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const row = f.closest('.nt-row');
        const btn = f.querySelector('button'); btn.disabled = true;
        try {
            const res = await fetch(f.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
            if (!res.ok) throw new Error(res.status);
            const json = await res.json();
            row.classList.remove('unread');
            f.remove();
            if (label) label.textContent = json.data.unread;
        } catch (err) { btn.disabled = false; f.submit(); }
    }));
})();
</script>
@endpush
