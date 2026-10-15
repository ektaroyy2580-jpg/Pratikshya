<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'प्रतीक्षा — Don\'t Waste Your Wait')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>document.documentElement.classList.add('reveal-ready');try{if(localStorage.getItem('pk_lang')==='ne'){document.documentElement.classList.add('i18n-pending');document.documentElement.lang='ne';setTimeout(function(){document.documentElement.classList.remove('i18n-pending')},1500);}}catch(e){}</script>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@php
    $me = auth()->user();
    $navTicket = $me?->tickets()->whereIn('status', ['waiting', 'called', 'serving'])->latest()->first();
    $unread = $me?->unreadNotifications()->count() ?? 0;
@endphp
<nav class="navbar navbar-expand-md pk-nav sticky-top py-2" aria-label="Main">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            @include('partials.logo', ['size' => 38])
            <span class="brand-word"><span class="np">प्रतीक्षा</span><span class="en" translate="no">Pratikshya</span></span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation"><i class="bi bi-list fs-3"></i></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-1 py-2 py-md-0">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('find', 'service') ? 'active' : '' }}" href="{{ route('find') }}"><i class="bi bi-search me-1"></i>Find a service</a></li>
                @auth
                    @php
                        // Feature modules: only show links whose routes exist.
                        $features = collect([
                            ['dashboard', 'bi-grid-1x2', 'Dashboard'],
                            ['games.hub', 'bi-controller', 'Games'],
                            ['library.home', 'bi-book', 'Library'],
                            ['english.home', 'bi-mic', 'English practice'],
                            ['explore.index', 'bi-compass', 'Explore nearby'],
                            ['rides.plan', 'bi-car-front', 'Rides'],
                            ['integrity.exchange.index', 'bi-arrow-left-right', 'Queue exchange'],
                            ['platform.rewards', 'bi-stars', 'Rewards'],
                            ['platform.notifications', 'bi-bell', 'Notifications'],
                            ['platform.settings', 'bi-gear', 'Settings'],
                        ])->filter(fn ($f) => Route::has($f[0]));
                    @endphp
                    @if($features->isNotEmpty())
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-grid-3x3-gap me-1"></i>More</a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                @foreach($features as [$r, $ic, $label])
                                    <li><a class="dropdown-item rounded-3 py-2" href="{{ route($r) }}"><i class="bi {{ $ic }} me-2 text-primary"></i>{{ $label }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                    @if($navTicket)
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('ticket') ? 'active' : '' }}" href="{{ route('ticket', $navTicket) }}"><i class="bi bi-ticket-perforated me-1"></i>My token <span class="chip ms-1 py-0 px-2">{{ $navTicket->token() }}</span></a></li>
                    @endif
                    @if(in_array($me->role, ['staff', 'admin']))
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('staff') ? 'active' : '' }}" href="{{ route('staff') }}"><i class="bi bi-person-badge me-1"></i>Staff</a></li>
                    @endif
                    @php
                        $isOrgManager = class_exists(\App\Services\Platform\OrgAccess::class) && app(\App\Services\Platform\OrgAccess::class)->isManager($me);
                        $manage = collect([
                            ['admin', 'bi-speedometer2', 'Admin overview', $me->role === 'admin'],
                            ['platform.manage.index', 'bi-sliders', 'Management console', $me->role === 'admin' || $isOrgManager],
                            ['platform.analytics', 'bi-graph-up', 'Analytics', $me->role === 'admin' || $isOrgManager],
                            ['integrity.admin.index', 'bi-shield-exclamation', 'Dalal AI alerts', $me->role === 'admin'],
                            ['integrity.staff.exchanges', 'bi-arrow-left-right', 'Exchange approvals', in_array($me->role, ['staff', 'admin'])],
                            ['ops.incidents.index', 'bi-tools', 'Incidents & notices', in_array($me->role, ['staff', 'admin'])],
                            ['ops.qr.index', 'bi-qr-code', 'QR posters', in_array($me->role, ['staff', 'admin'])],
                            ['ads.admin', 'bi-megaphone', 'Ads review', $me->role === 'admin'],
                            ['games.admin.questions', 'bi-patch-question', 'Game questions', $me->role === 'admin'],
                            ['library.admin.index', 'bi-journal-text', 'Library content', $me->role === 'admin'],
                            ['ads.portal', 'bi-shop', 'Advertiser portal', class_exists(\App\Models\AdvertiserUser::class) && \App\Models\AdvertiserUser::where('user_id', $me->id)->exists()],
                            ['preorder.merchant', 'bi-cup-hot', 'Café dashboard', class_exists(\App\Models\Restaurant::class) && method_exists(\App\Models\Restaurant::class, 'managedBy') && \App\Models\Restaurant::managedBy($me)->exists()],
                        ])->filter(fn ($m) => $m[3] && Route::has($m[0]));
                    @endphp
                    @if($manage->isNotEmpty())
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('admin*', 'platform.manage*', 'ops.*', 'integrity.admin*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-briefcase me-1"></i>Manage</a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                @foreach($manage as [$r, $ic, $label])
                                    <li><a class="dropdown-item rounded-3 py-2" href="{{ route($r) }}"><i class="bi {{ $ic }} me-2 text-primary"></i>{{ $label }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('profile') ? 'active' : '' }}" href="{{ route('profile') }}"><i class="bi bi-person-circle me-1"></i>{{ \Illuminate\Support\Str::of($me->name)->before(' ') }}@if($unread)<span class="badge bg-danger nav-badge ms-1" aria-label="{{ $unread }} unread notifications">{{ $unread }}</span>@endif</a></li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Log in</a></li>
                @endauth
                @auth<li class="nav-item ms-md-1 my-2 my-md-0"><button type="button" class="pk-sound-btn" data-pk-sound aria-label="Toggle turn alert sound"><i class="bi bi-bell"></i></button></li>@endauth
                <li class="nav-item ms-md-2 my-2 my-md-0"><div class="lang-sw" role="group" aria-label="Language"><button type="button" data-lang="en" onclick="setLang('en')">EN</button><button type="button" data-lang="ne" onclick="setLang('ne')">नेपाली</button></div></li>
                <li class="nav-item ms-md-2"><a class="btn btn-pk btn-sm" href="{{ $me ? route('find') : route('register.show') }}">{{ $me ? 'Join a queue' : 'Get started' }}</a></li>
            </ul>
        </div>
    </div>
</nav>

@unless(request()->routeIs('profile'))
    @if(session('ok') || $errors->has('queue'))
        <div class="toast-ww" role="status" aria-live="polite" id="flashToast">
            <div class="alert {{ $errors->has('queue') ? 'alert-danger' : 'alert-success' }} shadow d-flex align-items-center gap-2 mb-0">
                <i class="bi {{ $errors->has('queue') ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill' }}"></i>
                <span class="flex-grow-1 fw-semibold">{{ $errors->first('queue') ?: session('ok') }}</span>
                <button type="button" class="btn-close" aria-label="Close" onclick="this.closest('#flashToast').remove()"></button>
            </div>
        </div>
        <script>setTimeout(() => document.getElementById('flashToast')?.remove(), 6000);</script>
    @endif
@endunless

<main>
@yield('content')
</main>

<footer class="footer-ww mt-5 py-4">
    <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 small">
        <div class="d-flex align-items-center gap-2">@include('partials.logo', ['size' => 26, 'static' => true]) प्रतीक्षा · Don't waste your wait</div>
        <div class="d-flex gap-3"><a href="{{ route('find') }}">Services</a><a href="{{ route('staff.login') }}">Staff portal</a>@if(Route::has('explore.credits'))<a href="{{ route('explore.credits') }}">Photo credits</a>@endif @if(Route::has('library.credits'))<a href="{{ route('library.credits') }}">Library credits</a>@endif<span>Local demo build</span></div>
    </div>
</footer>

{{-- Mobile bottom navigation --}}
@php
    $bn = [
        ['Home', 'bi-house-door', route('home'), request()->routeIs('home')],
        ['Queues', 'bi-ticket-perforated', $navTicket ? route('ticket', $navTicket) : route('find'), request()->routeIs('find', 'service', 'ticket')],
        ['Games', 'bi-controller', Route::has('games.hub') ? route('games.hub') : route('find'), request()->routeIs('games.*', 'arcade.*')],
        ['Explore', 'bi-compass', Route::has('explore.index') ? route('explore.index') : route('find'), request()->routeIs('explore.*', 'rides.*', 'preorder.*')],
        ['Profile', 'bi-person', $me ? route('profile') : route('login'), request()->routeIs('profile', 'login')],
    ];
@endphp
<nav class="bottom-nav d-md-none" aria-label="Mobile">
    @foreach($bn as [$label, $icon, $href, $active])
        <a href="{{ $href }}" class="{{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif><i class="bi {{ $icon }}"></i><span>{{ $label }}</span></a>
    @endforeach
</nav>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/i18n.js') }}?v={{ filemtime(public_path('js/i18n.js')) }}"></script>
@if($navTicket)
<script>window.PK_ALERT = { ticketId: {{ $navTicket->id }}, stateUrl: @json(route('ticket.state', $navTicket)), ticketUrl: @json(route('ticket', $navTicket)), onTicketPage: {{ request()->routeIs('ticket') ? 'true' : 'false' }} };</script>
@endif
<script src="{{ asset('js/turn-alerts.js') }}?v={{ filemtime(public_path('js/turn-alerts.js')) }}"></script>
<script src="{{ asset('js/motion.js') }}?v={{ filemtime(public_path('js/motion.js')) }}"></script>
<script>
// One-shot entrance animation for elements marked .reveal (stagger with style="--d:.1s").
(() => {
    const els = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('in')); return; }
    const io = new IntersectionObserver(entries => entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
    }), { rootMargin: '0px 0px -8% 0px' });
    els.forEach(e => io.observe(e));
    // Safety net: never leave content hidden.
    setTimeout(() => els.forEach(e => e.classList.add('in')), 2500);
})();
</script>
@stack('scripts')
</body>
</html>
