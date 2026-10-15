@extends('layouts.app')
@section('title', 'प्रतीक्षा — Smart queues for Nepal')

@push('head')
<style>
    .hero { position: relative; padding: 4.5rem 0 4rem; overflow: hidden;
        background: radial-gradient(900px 420px at 88% 0%, rgba(112,70,232,.13), transparent 65%),
                    radial-gradient(800px 380px at 5% 10%, rgba(47,91,234,.11), transparent 65%), var(--pk-surface); }
    .hero::after { content: ''; position: absolute; inset: auto 0 0 0; height: 1px; background: var(--pk-line); }
    .hero h1 { font-size: clamp(2.4rem, 5.2vw, 4rem); line-height: 1.04; }
    .hero .lead { font-size: 1.15rem; color: var(--pk-ink-2); max-width: 34rem; }
    .grid-bg { position: absolute; inset: 0; pointer-events: none; opacity: .5;
        background-image: linear-gradient(var(--pk-line) 1px, transparent 1px), linear-gradient(90deg, var(--pk-line) 1px, transparent 1px);
        background-size: 44px 44px; mask-image: radial-gradient(600px 300px at 70% 30%, #000, transparent 75%); -webkit-mask-image: radial-gradient(600px 300px at 70% 30%, #000, transparent 75%); }

    /* Live token preview */
    .preview { position: relative; max-width: 380px; margin-inline: auto; }
    .preview-card { background: #fff; border: 1px solid var(--pk-line); border-radius: 24px; box-shadow: 0 40px 80px -40px rgba(31,41,110,.45), var(--pk-shadow); overflow: hidden; }
    .preview-top { background: var(--pk-grad); color: #fff; padding: 1.25rem 1.4rem 1.6rem; }
    .preview-token { font-size: 3.4rem; font-weight: 800; letter-spacing: -.04em; line-height: 1; }
    .preview-body { padding: 1.25rem 1.4rem 1.4rem; margin-top: -14px; background: #fff; border-radius: 18px 18px 0 0; position: relative; }
    .mini { background: var(--pk-bg); border-radius: 12px; padding: .7rem .8rem; }
    .mini .v { font-weight: 800; font-size: 1.15rem; }
    .mini .l { font-size: .7rem; color: var(--pk-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .q-track { height: 8px; border-radius: 999px; background: #eceef6; overflow: hidden; }
    .q-track > span { display: block; height: 100%; width: 35%; background: var(--pk-grad); border-radius: inherit; transition: width 1.2s var(--pk-ease); }
    .float-note { position: absolute; z-index: 3; background: #fff; border: 1px solid var(--pk-line); border-radius: 14px; box-shadow: var(--pk-shadow-lg); padding: .6rem .8rem; font-size: .8rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; animation: pk-rise .7s var(--pk-ease) both; }
    .float-note.n1 { left: -70px; top: 46%; animation-delay: .5s; }
    .float-note.n2 { right: -54px; bottom: 30%; animation-delay: .8s; }
    @media (max-width: 991.98px) { .float-note { display: none; } }

    .stat-strip .num { font-size: 2rem; font-weight: 800; letter-spacing: -.03em; }
    .step-num { width: 30px; height: 30px; border-radius: 50%; display: inline-grid; place-items: center; font-size: .8rem; font-weight: 800; background: var(--pk-ink); color: #fff; }
    .feature h3 { font-size: 1.1rem; }
    .queue-row { display: flex; align-items: center; gap: .9rem; padding: .85rem 1rem; border-radius: 14px; text-decoration: none; color: inherit; transition: background .2s; }
    .queue-row:hover { background: var(--pk-bg); }
    .cta-band { background: var(--pk-ink); color: #fff; border-radius: 24px; position: relative; overflow: hidden; }
    .cta-band::before { content: ''; position: absolute; inset: 0; background: radial-gradient(500px 220px at 90% 0%, rgba(112,70,232,.55), transparent 70%), radial-gradient(500px 220px at 0% 100%, rgba(47,91,234,.45), transparent 70%); }
    .cta-band > * { position: relative; }
</style>
@endpush

@section('content')
{{-- ================= HERO ================= --}}
<section class="hero">
    <div class="grid-bg"></div>
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="chip mb-3 fade-up"><span class="live-dot"></span> {{ number_format($stats['waiting']) }} people in live queues right now</span>
                <h1 class="mb-3 fade-up" style="animation-delay:.05s">Don't waste<br>your <span class="text-gradient">wait.</span></h1>
                <p class="lead mb-4 fade-up" style="animation-delay:.1s">Join government, bank and hospital queues from your phone. Check your documents before you go, and use the waiting time for something better. We'll tell you when it's your turn.</p>
                <div class="d-flex flex-wrap gap-2 mb-4 fade-up" style="animation-delay:.15s">
                    <a href="{{ route('find') }}" class="btn btn-pk btn-lg px-4">Find a service <i class="bi bi-arrow-right ms-1"></i></a>
                    @guest<a href="{{ route('register.show') }}" class="btn btn-ghost btn-lg px-4">Create free account</a>@endguest
                    @auth<a href="{{ route('profile') }}" class="btn btn-ghost btn-lg px-4">My visits</a>@endauth
                </div>
                <div class="d-flex flex-wrap gap-4 small pk-muted fade-up" style="animation-delay:.2s">
                    <span><i class="bi bi-shield-check text-success me-1"></i>One token per person: fair order</span>
                    <span><i class="bi bi-bell me-1 text-primary"></i>Alert when you're 3 away</span>
                </div>
            </div>

            <div class="col-lg-6">
                @if($featured)
                <div class="preview fade-up" style="animation-delay:.15s">
                    <div class="float-note n1"><span class="pk-icon green" style="width:30px;height:30px;font-size:.9rem"><i class="bi bi-check2"></i></span> Documents ready</div>
                    <div class="float-note n2"><span class="pk-icon" style="width:30px;height:30px;font-size:.9rem"><i class="bi bi-bell-fill"></i></span> 3 people away</div>
                    <div class="preview-card">
                        <div class="preview-top">
                            <div class="d-flex justify-content-between align-items-center small opacity-75 fw-semibold mb-2">
                                <span><i class="bi bi-geo-alt me-1"></i>{{ \Illuminate\Support\Str::limit($featured->location->name, 30) }}</span>
                                <span><span class="live-dot me-1" style="background:#fff"></span>Live</span>
                            </div>
                            <div class="small opacity-75">{{ $featured->name }}</div>
                            <div class="preview-token">{{ $featured->token($featured->last_number + 1) }}</div>
                            <div class="small opacity-75 mt-1">Next token if you join now · Counter {{ $featured->counter }}</div>
                        </div>
                        <div class="preview-body">
                            <div class="row g-2 mb-3 text-center">
                                <div class="col-4"><div class="mini"><div class="v">{{ $featured->token($featured->current_number) }}</div><div class="l">Serving</div></div></div>
                                <div class="col-4"><div class="mini"><div class="v">{{ $featured->waitingCount() }}</div><div class="l">Ahead</div></div></div>
                                <div class="col-4"><div class="mini"><div class="v">{{ $featuredEstimate['low'] }}–{{ $featuredEstimate['high'] }}</div><div class="l">Minutes</div></div></div>
                            </div>
                            <div class="q-track mb-2"><span id="heroTrack"></span></div>
                            <div class="d-flex justify-content-between small pk-muted mb-3"><span>Joined</span><span>Your turn</span></div>
                            <div class="small fw-bold mb-2">While you wait</div>
                            <div class="d-grid gap-2">
                                <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background:var(--pk-bg)"><span class="pk-icon violet" style="width:32px;height:32px;font-size:.95rem"><i class="bi bi-controller"></i></span><span class="small fw-semibold">Play a quick quiz or memory game</span></div>
                                <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background:var(--pk-bg)"><span class="pk-icon green" style="width:32px;height:32px;font-size:.95rem"><i class="bi bi-cup-hot"></i></span><span class="small fw-semibold">Café 4 min away: fits your wait</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- ================= LIVE STATS ================= --}}
<section class="py-4 border-bottom bg-white">
    <div class="container">
        <div class="row text-center stat-strip g-3">
            <div class="col-4 reveal"><div class="num" data-count="{{ $stats['waiting'] }}">{{ $stats['waiting'] }}</div><div class="small pk-muted">people waiting now</div></div>
            <div class="col-4 reveal" style="--d:.08s"><div class="num" data-count="{{ $stats['services'] }}">{{ $stats['services'] }}</div><div class="small pk-muted">services with live queues</div></div>
            <div class="col-4 reveal" style="--d:.16s"><div class="num" data-count="{{ $stats['locations'] }}">{{ $stats['locations'] }}</div><div class="small pk-muted">offices &amp; hospitals</div></div>
        </div>
        <div class="text-center small pk-muted mt-2"><i class="bi bi-info-circle me-1"></i>Counts come from this local demo database.</div>
    </div>
</section>

{{-- ================= HOW IT WORKS ================= --}}
<section class="pk-section" id="how">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <div class="pk-eyebrow mb-2">How it works</div>
            <h2 class="display-6 mb-2">From standing in line to living your day</h2>
            <p class="pk-muted mx-auto mb-0" style="max-width:36rem">Licence renewals, passport offices, banks and hospital OPDs. Know the wait before you leave, and never reach the counter missing a paper.</p>
        </div>
        <div class="row g-4">
            @foreach([
                ['bi-search', '', 'Find your service', 'See who is being served now, how many people are ahead, and an honest wait range.'],
                ['bi-file-earmark-check', 'green', 'Check documents', 'A checklist for each service. Know what is missing before the trip, not at the counter.'],
                ['bi-ticket-perforated', 'violet', 'Get a fair token', 'One token per person, issued in order. Games and points never change your place.'],
                ['bi-bell', 'amber', 'Go live your wait', 'Play, read or step out nearby. We alert you when you are 3 away and when you are called.'],
            ] as $i => [$icon, $tone, $title, $text])
                <div class="col-sm-6 col-lg-3 reveal" style="--d: {{ $i * .08 }}s">
                    <div class="pk-card lift p-4 h-100 feature">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="pk-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                            <span class="step-num">{{ $i + 1 }}</span>
                        </div>
                        <h3 class="mb-2">{{ $title }}</h3>
                        <p class="small pk-muted mb-0">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ================= LIVE QUEUES ================= --}}
<section class="pb-5">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5 reveal">
                <div class="pk-eyebrow mb-2">Right now</div>
                <h2 class="mb-3">Busiest queues today</h2>
                <p class="pk-muted mb-4">Live numbers from each office. Tap one to see documents and join from wherever you are.</p>
                <a href="{{ route('find') }}" class="btn btn-ghost">See all services <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
            <div class="col-lg-7 reveal" style="--d:.1s">
                <div class="pk-card p-2">
                    @foreach($busiest as $s)
                        <a class="queue-row" href="{{ route('service', $s) }}">
                            <span class="pk-icon {{ ['', 'violet', 'green', 'amber'][$loop->index % 4] }}"><i class="bi {{ $s->location->icon }}"></i></span>
                            <span class="flex-grow-1 min-w-0">
                                <span class="fw-bold d-block text-truncate">{{ $s->name }}</span>
                                <span class="small pk-muted d-block text-truncate">{{ $s->location->name }}</span>
                            </span>
                            <span class="text-end">
                                <span class="fw-bold d-block">{{ $s->waitingCount() }} waiting</span>
                                @php $e = $s->estimate(); @endphp
                                <span class="small pk-muted">~{{ $e['low'] }}–{{ $e['high'] }} min</span>
                            </span>
                            <i class="bi bi-chevron-right pk-muted"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ================= FOR OFFICES ================= --}}
<section class="pb-4">
    <div class="container">
        <div class="cta-band p-4 p-md-5 reveal">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="small fw-bold text-uppercase opacity-75 mb-2" style="letter-spacing:.06em">For offices, banks &amp; hospitals</div>
                    <h2 class="text-white mb-2">Calmer halls. Faster counters.</h2>
                    <p class="mb-0 opacity-75">Staff call the next token, verify documents and close each service from one screen. Every action is recorded in an audit trail.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('staff.login') }}" class="btn btn-light btn-lg fw-bold px-4">Staff portal <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
// Fill the preview progress bar once, gently.
requestAnimationFrame(() => setTimeout(() => { const t = document.getElementById('heroTrack'); if (t) t.style.width = '62%'; }, 400));
</script>
@endpush
