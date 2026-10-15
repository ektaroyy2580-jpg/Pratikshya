@php $isStaffPortal = trim($__env->yieldContent('variant')) === 'staff'; @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f1535">
    <title>@yield('title', 'Sign in') · प्रतीक्षा</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>try{if(localStorage.getItem('pk_lang')==='ne'){document.documentElement.classList.add('i18n-pending');document.documentElement.lang='ne';setTimeout(function(){document.documentElement.classList.remove('i18n-pending')},1500);}}catch(e){}</script>
    @stack('head')
    <style>
        body { min-height: 100vh; background: var(--pk-bg); }

        /* ---------- Split shell ---------- */
        .auth-shell { min-height: 100vh; display: grid; grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
        @media (min-width: 992px) { .auth-shell { grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); grid-template-rows: 1fr; } }

        /* ---------- Brand panel (calm navy, static glow) ---------- */
        .auth-brand { position: relative; overflow: hidden; color: #fff; background:
                radial-gradient(640px 420px at 100% 0%, rgba(112, 70, 232, .38), transparent 70%),
                radial-gradient(560px 420px at 0% 100%, rgba(47, 91, 234, .34), transparent 70%),
                #0f1535; }
        .auth-brand::after { content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .5;
            background-image: linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(520px 420px at 70% 40%, #000, transparent 80%); -webkit-mask-image: radial-gradient(520px 420px at 70% 40%, #000, transparent 80%); }
        .auth-brand-inner { position: relative; z-index: 1; display: flex; flex-direction: column; padding: 1rem 1.15rem; }
        @media (min-width: 992px) { .auth-brand-inner { padding: 2.5rem 3.25rem; position: sticky; top: 0; min-height: 100vh; } }

        .brand-link { color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: .65rem; font-weight: 800; font-size: 1.2rem; letter-spacing: -.02em; }
        .brand-link:hover { color: #fff; }
        .brand-link .brand-mark { width: 38px; height: 38px; font-size: 1.1rem; box-shadow: 0 8px 20px -8px rgba(112, 70, 232, .8); }
        .brand-link small { display: block; font-size: .68rem; font-weight: 600; letter-spacing: .02em; color: rgba(255,255,255,.6); line-height: 1; margin-top: 3px; }
        .brand-link:focus-visible { outline-color: rgba(185, 200, 255, .8); }

        .brand-body { max-width: 30rem; }
        .brand-chip { display: inline-flex; align-items: center; gap: .45rem; padding: .35rem .8rem; border-radius: 999px; font-size: .78rem; font-weight: 700;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); color: #dfe5ff; }
        .brand-tagline { font-size: clamp(2.2rem, 3.4vw, 3.15rem); font-weight: 800; line-height: 1.04; letter-spacing: -.035em; }
        .brand-tagline .soft { background: linear-gradient(135deg, #9db4ff 0%, #c4b2ff 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .brand-sub { color: rgba(226, 231, 255, .78); font-size: 1.02rem; line-height: 1.6; }

        .feat-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 1.1rem; }
        .feat-list li { display: flex; gap: .9rem; align-items: flex-start; }
        .feat-ico { flex: 0 0 auto; width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 1.05rem;
            background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); color: #b9c8ff; }
        .feat-list strong { display: block; font-size: .95rem; font-weight: 700; }
        .feat-list span { color: rgba(226, 231, 255, .68); font-size: .86rem; }

        /* Static token card illustration */
        .token-card { max-width: 330px; border-radius: 18px; padding: 1rem 1.1rem; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.13);
            box-shadow: 0 30px 60px -30px rgba(0,0,0,.6); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
        .token-card .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; color: rgba(226,231,255,.6); }
        .token-card .tok { font-size: 2rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
        .token-card .track { height: 6px; border-radius: 999px; background: rgba(255,255,255,.12); overflow: hidden; }
        .token-card .track span { display: block; height: 100%; width: 68%; border-radius: inherit; background: linear-gradient(90deg, #6f8cff, #a88bff); }
        .token-card .pill { font-size: .72rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; background: rgba(34,197,94,.16); color: #86efac; display: inline-flex; align-items: center; gap: .35rem; }
        .token-card .pill .live-dot { width: 6px; height: 6px; background: #4ade80; }

        .brand-foot { color: rgba(226,231,255,.6); font-size: .82rem; }
        .brand-foot a { color: rgba(226,231,255,.85); text-decoration: none; font-weight: 600; }
        .brand-foot a:hover { color: #fff; }

        /* Mobile: compact header */
        .brand-mobile-tag { color: rgba(226,231,255,.75); font-size: .8rem; font-weight: 600; }
        @media (max-width: 991.98px) {
            .auth-brand .brand-body, .auth-brand .brand-foot { display: none !important; }
            .auth-brand-inner { flex-direction: row; align-items: center; justify-content: space-between; gap: 1rem; }
        }

        /* ---------- Form side ---------- */
        .auth-main { display: flex; align-items: flex-start; justify-content: center; padding: 1.5rem 1rem 2.5rem; }
        @media (min-width: 992px) { .auth-main { align-items: center; padding: 3rem 2.5rem; } }
        .auth-col { width: 100%; max-width: 448px; }
        .auth-card { padding: 1.6rem 1.35rem; }
        @media (min-width: 576px) { .auth-card { padding: 2.4rem 2.4rem 2.2rem; } }
        .auth-title { font-size: 1.7rem; font-weight: 800; letter-spacing: -.03em; margin-bottom: .35rem; }
        .auth-lead { color: var(--pk-muted); margin-bottom: 1.75rem; }
        .auth-card .form-label { color: var(--pk-ink); margin-bottom: .4rem; }
        .auth-card .form-control { padding: .78rem 1rem; background-color: #fff; }
        .auth-card .form-control::placeholder { color: #a3a9c2; }
        .auth-card .form-text { color: var(--pk-muted); font-size: .8rem; }
        .auth-card .btn-pk { padding: .85rem 1.3rem; font-size: 1rem; }
        .auth-card .alert { font-size: .9rem; padding: .8rem 1rem; }
        .label-row { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; }
        .label-row a { font-size: .82rem; font-weight: 700; text-decoration: none; }

        /* Password field with inline toggle */
        .pw-wrap { position: relative; }
        .pw-wrap .form-control { padding-right: 3rem; }
        .pw-wrap .form-control.is-invalid { background-position: right 3rem center; padding-right: 5rem; }
        .btn-pw { position: absolute; top: 5px; right: 5px; bottom: 5px; width: 40px; border: 0; border-radius: 9px; background: transparent;
            color: var(--pk-muted); display: grid; place-items: center; transition: background .2s, color .2s; }
        .btn-pw:hover { background: var(--pk-tint); color: var(--pk-blue); }

        /* Strength meter */
        .pw-meter { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
        .pw-meter span { height: 5px; border-radius: 6px; background: #e6e8f2; transition: background .25s; }
        .pw-meter[data-score="1"] span:nth-child(-n+1) { background: var(--pk-danger); }
        .pw-meter[data-score="2"] span:nth-child(-n+2) { background: var(--pk-warn); }
        .pw-meter[data-score="3"] span:nth-child(-n+3) { background: var(--pk-blue); }
        .pw-meter[data-score="4"] span:nth-child(-n+4) { background: var(--pk-success); }
        .pw-label { font-size: .76rem; font-weight: 700; color: var(--pk-muted); white-space: nowrap; }
        .pw-label [data-score="1"] { color: var(--pk-danger); } .pw-label [data-score="2"] { color: var(--pk-warn); }
        .pw-label [data-score="3"] { color: var(--pk-blue); } .pw-label [data-score="4"] { color: var(--pk-success); }
        .pw-checks { list-style: none; padding: 0; margin: .55rem 0 0; display: grid; grid-template-columns: 1fr 1fr; gap: .2rem .75rem; font-size: .78rem; color: var(--pk-muted); }
        .pw-checks li { display: flex; align-items: center; gap: .4rem; transition: color .2s; }
        .pw-checks li.ok { color: var(--pk-success); }
        .match-hint { font-size: .8rem; font-weight: 600; margin-top: .4rem; min-height: 1.2em; }
        .match-hint:empty { min-height: 0; margin: 0; }
        .match-hint.ok { color: var(--pk-success); } .match-hint.bad { color: var(--pk-danger); }
        @media (max-width: 380px) { .pw-checks { grid-template-columns: 1fr; } }

        .divider { display: flex; align-items: center; gap: .75rem; color: var(--pk-muted); font-size: .78rem; font-weight: 600; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--pk-line); }
        .alt-link { display: flex; align-items: center; justify-content: center; gap: .5rem; padding: .7rem 1rem; border: 1px solid var(--pk-line); border-radius: var(--pk-radius-sm);
            color: var(--pk-ink-2); font-weight: 700; font-size: .9rem; text-decoration: none; background: #fff; transition: border-color .2s, color .2s, background .2s; }
        .alt-link:hover { border-color: #cdd3ea; color: var(--pk-blue); background: #fbfcff; }

        .demo-box { background: #fff; border: 1px solid var(--pk-line); border-radius: var(--pk-radius-sm); }
        .demo-box code { color: var(--pk-ink); background: var(--pk-bg); border: 1px solid var(--pk-line); border-radius: 6px; padding: .1rem .4rem; font-size: .78rem; }
        .demo-toggle { color: var(--pk-ink-2); font-weight: 700; font-size: .85rem; background: none; border: 0; padding: .85rem 1rem; }
        .demo-toggle:hover { color: var(--pk-blue); }
        .demo-toggle .bi-chevron-down { transition: transform .25s var(--pk-ease); display: inline-block; }
        .demo-toggle[aria-expanded="true"] .bi-chevron-down { transform: rotate(180deg); }
        .demo-role { display: inline-block; min-width: 3.4rem; font-weight: 700; color: var(--pk-ink); }

        .auth-foot-m { color: var(--pk-muted); }
        .auth-foot-m a { color: var(--pk-ink-2); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="auth-shell">
    <aside class="auth-brand" aria-label="About प्रतीक्षा">
        <div class="auth-brand-inner">
            <a href="{{ route('home') }}" class="brand-link">
                @include('partials.logo', ['size' => 40])
                <span>प्रतीक्षा<small>Pratikshya</small></span>
            </a>
            <span class="brand-mobile-tag d-lg-none">{{ $isStaffPortal ? 'Staff & Admin portal' : "Don't waste your wait" }}</span>

            <div class="brand-body my-auto py-5">
                @if($isStaffPortal)
                    <span class="brand-chip mb-4"><i class="bi bi-shield-check" aria-hidden="true"></i> Staff &amp; Admin portal</span>
                    <h2 class="brand-tagline mb-3">Run the counter,<br><span class="soft">not the crowd.</span></h2>
                    <p class="brand-sub mb-4 pb-1">One console to call tokens, check documents and keep every visitor informed, scoped to the locations you're assigned.</p>
                    <ul class="feat-list mb-5">
                        <li><span class="feat-ico" aria-hidden="true"><i class="bi bi-megaphone"></i></span>
                            <div><strong>Call next in one tap</strong><span>Route tokens to any open counter.</span></div></li>
                        <li><span class="feat-ico" aria-hidden="true"><i class="bi bi-file-earmark-check"></i></span>
                            <div><strong>Document checks</strong><span>Record what was seen before service starts.</span></div></li>
                        <li><span class="feat-ico" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
                            <div><strong>Full audit trail</strong><span>Every call, move and role change is logged.</span></div></li>
                    </ul>
                @else
                    <span class="brand-chip mb-4"><i class="bi bi-geo-alt" aria-hidden="true"></i> Smart queues for Nepal</span>
                    <h2 class="brand-tagline mb-3">Don't waste<br>your <span class="soft">wait.</span></h2>
                    <p class="brand-sub mb-4 pb-1">Take a token from your phone, see exactly where you stand, and arrive with the right documents.</p>
                    <ul class="feat-list mb-5">
                        <li><span class="feat-ico" aria-hidden="true"><i class="bi bi-broadcast"></i></span>
                            <div><strong>Live tokens</strong><span>Real-time position and counter calls.</span></div></li>
                        <li><span class="feat-ico" aria-hidden="true"><i class="bi bi-bell"></i></span>
                            <div><strong>Turn alerts</strong><span>We tell you when you're three away.</span></div></li>
                        <li><span class="feat-ico" aria-hidden="true"><i class="bi bi-patch-check"></i></span>
                            <div><strong>Document checklist</strong><span>Know you have the right papers first.</span></div></li>
                    </ul>
                @endif

                <div class="token-card" aria-hidden="true">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="lbl">{{ $isStaffPortal ? 'Now serving · Counter 2' : 'Your token' }}</span>
                        <span class="pill"><span class="live-dot"></span> Live</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <span class="tok">{{ $isStaffPortal ? 'A-24' : 'B-07' }}</span>
                        <span class="small" style="color: rgba(226,231,255,.7)">{{ $isStaffPortal ? '12 waiting' : '3 ahead · ~6 min' }}</span>
                    </div>
                    <div class="track"><span></span></div>
                </div>
            </div>

            <div class="brand-foot d-flex justify-content-between align-items-center gap-3">
                <a href="{{ route('home') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to home</a>
                <span><i class="bi bi-hdd me-1" aria-hidden="true"></i> Local demo</span>
            </div>
        </div>
    </aside>

    <main class="auth-main" id="main">
        <div class="auth-col">
            @yield('content')
            <p class="text-center small auth-foot-m mt-4 mb-0 d-lg-none">
                <a href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to home</a>
            </p>
        </div>
    </main>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/auth.js') }}?v=2"></script>
@stack('scripts')
<script src="{{ asset('js/i18n.js') }}?v={{ filemtime(public_path('js/i18n.js')) }}"></script>
</body>
</html>
