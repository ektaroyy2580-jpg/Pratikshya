@extends('layouts.app')
@section('title', 'Rewards wallet — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/platform.css') }}?v={{ filemtime(public_path('css/modules/platform.css')) }}" rel="stylesheet">
<style>
    .wallet { position: relative; overflow: hidden; color: #fff; border: 0; padding: 1.6rem; background:
        radial-gradient(420px 220px at 100% 0%, rgba(112, 70, 232, .55), transparent 70%),
        radial-gradient(380px 220px at 0% 100%, rgba(47, 91, 234, .5), transparent 70%), #0f1535; }
    .wallet .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: rgba(226, 231, 255, .72); }
    .wallet .bal { font-size: clamp(2.6rem, 6vw, 3.4rem); font-weight: 800; letter-spacing: -.04em; line-height: 1; font-variant-numeric: tabular-nums; }
    .wallet .sub { color: rgba(226, 231, 255, .78); font-size: .85rem; }
    .wallet .mini { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: 12px; padding: .6rem .8rem; }
    .wallet .mini b { display: block; font-size: 1.15rem; }
    .rw-item { padding: 1.15rem; height: 100%; display: flex; flex-direction: column; gap: .75rem; }
    .rw-item .pk-icon { width: 42px; height: 42px; }
    .rw-cost { font-weight: 800; color: var(--pk-violet); white-space: nowrap; }
    .rw-item .progress { height: 6px; }
    .code-card { border: 2px dashed #c9d4ff; background: var(--pk-tint); border-radius: var(--pk-radius); padding: 1.25rem; text-align: center; }
    .code-card .code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: clamp(1.4rem, 4vw, 1.9rem); font-weight: 800; letter-spacing: .08em; color: var(--pk-ink); }
    .ld-row { display: flex; align-items: center; gap: .85rem; padding: .8rem 1.25rem; }
    .ld-row + .ld-row { border-top: 1px solid #f0f1f7; }
    .ld-pts { font-weight: 800; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ld-pts.plus { color: var(--pk-success); } .ld-pts.minus { color: var(--pk-danger); }
    @media (max-width: 575.98px) { .ld-row { padding: .75rem 1rem; } }
</style>
@endpush

@section('content')
<header class="pk-page-head pf-head">
    <div class="container fade-up">
        <a href="{{ route('platform.dashboard') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <h1>Rewards wallet</h1>
        <div class="pk-muted small">Earn WaitPoints by playing, learning and completing visits. Redeem them with demo partners.</div>
    </div>
</header>

<div class="container py-4">
    @error('redeem')
        <div class="alert alert-warning d-flex gap-2 align-items-center fade-up" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ $message }}</span></div>
    @enderror

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <section class="pk-card wallet h-100 reveal" aria-label="Balance">
                <div class="lbl"><i class="bi bi-stars me-1"></i> Available balance</div>
                <div class="bal mt-2" data-count="{{ $balance }}">{{ number_format($balance) }}</div>
                <div class="sub mt-1">WaitPoints</div>
                <div class="d-flex gap-2 mt-4">
                    <div class="mini flex-fill"><span class="lbl">Earned</span><b>{{ number_format($earned) }}</b></div>
                    <div class="mini flex-fill"><span class="lbl">Redeemed</span><b>{{ number_format($spent) }}</b></div>
                </div>
                <p class="sub small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Points are a thank-you only — they never change your place in any queue.</p>
            </section>
        </div>
        <div class="col-lg-7">
            @if($fresh)
                <section class="code-card h-100 d-flex flex-column justify-content-center fade-up" aria-live="polite">
                    <div class="pk-eyebrow justify-content-center mb-1"><i class="bi bi-check-circle-fill"></i> Redeemed</div>
                    <div class="fw-bold mb-2">{{ $fresh->item?->title }}</div>
                    <div class="code" id="freshCode">{{ $fresh->code }}</div>
                    <div class="small pk-muted mt-2">One-time code · valid until {{ $fresh->expires_at?->format('d M Y') }} · show it at {{ $fresh->item?->partner }}</div>
                    <div class="mt-3"><button type="button" class="btn btn-ghost btn-sm" id="copyCode"><i class="bi bi-clipboard me-1"></i> Copy code</button></div>
                    <div class="small pk-muted mt-3"><i class="bi bi-info-circle me-1"></i>Demo partner — no real merchant is connected, so this code is for demonstration only.</div>
                </section>
            @else
                <section class="pk-card pf-panel h-100 reveal" style="--d:.05s" aria-labelledby="howH">
                    <div class="pf-panel-head"><h2 id="howH"><i class="bi bi-lightbulb text-primary"></i> How points work</h2></div>
                    <div class="pf-panel-body small d-grid gap-3">
                        <div class="d-flex gap-3"><span class="pk-icon violet" style="width:36px;height:36px;font-size:.95rem"><i class="bi bi-controller"></i></span><div><strong>Earn while waiting</strong><div class="pk-muted">Games, reading, English practice and completed visits award points — verified on the server, once per activity.</div></div></div>
                        <div class="d-flex gap-3"><span class="pk-icon green" style="width:36px;height:36px;font-size:.95rem"><i class="bi bi-gift"></i></span><div><strong>Redeem with partners</strong><div class="pk-muted">Each redemption creates a unique one-time code. Double clicks never charge twice.</div></div></div>
                        <div class="d-flex gap-3"><span class="pk-icon amber" style="width:36px;height:36px;font-size:.95rem"><i class="bi bi-slash-circle"></i></span><div><strong>No queue jumping</strong><div class="pk-muted">Points can't buy priority. Your token order is fixed by when you joined.</div></div></div>
                    </div>
                </section>
            @endif
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-end mb-2">
        <h2 class="h5 mb-0">Redeem</h2>
        <span class="pf-demo">Demo partners</span>
    </div>
    @if($catalogue->isEmpty())
        <div class="pk-card pf-empty mb-4"><span class="pk-icon mb-2"><i class="bi bi-gift"></i></span><p class="pk-muted small mb-0">No rewards available right now.</p></div>
    @else
        <div class="row g-3 mb-4">
            @foreach($catalogue as $i => $item)
                @php $affordable = $balance >= $item->points_cost; $pct = min(100, (int) round($balance / max(1, $item->points_cost) * 100)); @endphp
                <div class="col-sm-6 col-xl-4 reveal" style="--d: {{ $i * .05 }}s">
                    <article class="pk-card lift rw-item">
                        <div class="d-flex align-items-start gap-3">
                            <span class="pk-icon {{ $affordable ? 'violet' : '' }}"><i class="bi {{ $item->icon }}"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <h3 class="h6 mb-1">{{ $item->title }}</h3>
                                <div class="small pk-muted">{{ $item->partner }}</div>
                            </div>
                            <span class="rw-cost">{{ number_format($item->points_cost) }} pts</span>
                        </div>
                        <p class="small pk-muted mb-0 flex-grow-1">{{ $item->description }}</p>
                        @if($item->stock !== null)<div class="small fw-semibold {{ $item->stock ? 'text-body' : 'text-danger' }}"><i class="bi bi-box-seam me-1"></i>{{ $item->stock ? $item->stock.' left' : 'Out of stock' }}</div>@endif
                        @if($affordable && $item->inStock())
                            <form method="POST" action="{{ route('platform.rewards.redeem', $item->id) }}" data-confirm="Redeem {{ $item->title }} for {{ $item->points_cost }} points?">
                                @csrf<input type="hidden" name="nonce" value="{{ $nonce }}">
                                <button class="btn btn-pk btn-sm w-100" data-busy="Redeeming…"><i class="bi bi-gift me-1"></i> Redeem</button>
                            </form>
                        @else
                            <div>
                                <div class="progress mb-1" role="progressbar" aria-label="Progress towards {{ $item->title }}" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $pct }}%"></div></div>
                                <div class="small pk-muted">{{ $item->inStock() ? number_format($item->points_cost - $balance).' more points needed' : 'Currently unavailable' }}</div>
                            </div>
                        @endif
                    </article>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="pk-card pf-panel reveal" aria-labelledby="codesH">
                <div class="pf-panel-head"><h2 id="codesH"><i class="bi bi-ticket-detailed text-primary"></i> Your codes</h2></div>
                @forelse($redemptions as $r)
                    <div class="ld-row">
                        <span class="pk-icon" style="width:36px;height:36px;font-size:.95rem"><i class="bi {{ $r->item?->icon ?? 'bi-gift' }}"></i></span>
                        <div class="min-w-0 flex-grow-1"><div class="fw-semibold small text-truncate">{{ $r->item?->title }}</div><div class="pf-mono pk-muted">{{ $r->code }}</div></div>
                        @if($r->isExpired())<span class="pf-badge muted">Expired</span>@elseif($r->status === 'issued')<span class="pf-badge ok">Valid</span>@else<span class="pf-badge muted">{{ ucfirst($r->status) }}</span>@endif
                    </div>
                @empty
                    <div class="pf-empty py-4"><p class="pk-muted small mb-0">Codes you redeem will appear here.</p></div>
                @endforelse
            </section>
        </div>
        <div class="col-lg-7">
            <section class="pk-card pf-panel reveal" style="--d:.05s" aria-labelledby="ledH">
                <div class="pf-panel-head"><h2 id="ledH"><i class="bi bi-clock-history text-primary"></i> History</h2><span class="small pk-muted">{{ $ledger->total() }} transactions</span></div>
                @forelse($ledger as $tx)
                    <div class="ld-row">
                        <span class="pk-icon {{ $tx->points > 0 ? 'green' : 'rose' }}" style="width:34px;height:34px;font-size:.9rem"><i class="bi {{ $tx->points > 0 ? 'bi-plus-lg' : 'bi-dash-lg' }}"></i></span>
                        <div class="min-w-0 flex-grow-1"><div class="fw-semibold small text-truncate">{{ $tx->reason }}</div><div class="small pk-muted">{{ str_replace('_', ' ', $tx->source) }} · {{ $tx->created_at?->diffForHumans() }}</div></div>
                        <span class="ld-pts {{ $tx->points > 0 ? 'plus' : 'minus' }}">{{ $tx->points > 0 ? '+' : '−' }}{{ number_format(abs($tx->points)) }}</span>
                    </div>
                @empty
                    <div class="pf-empty py-4"><span class="pk-icon mb-2"><i class="bi bi-stars"></i></span><p class="pk-muted small mb-0">No points yet. Play a game or finish a visit to earn your first WaitPoints.</p></div>
                @endforelse
                @if($ledger->hasPages())<div class="pf-pager">{{ $ledger->links('pagination::bootstrap-5') }}</div>@endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/platform-console.js') }}?v={{ filemtime(public_path('js/modules/platform-console.js')) }}"></script>
<script>
document.getElementById('copyCode')?.addEventListener('click', async (e) => {
    const code = document.getElementById('freshCode').textContent.trim();
    try { await navigator.clipboard.writeText(code); e.currentTarget.innerHTML = '<i class="bi bi-check2 me-1"></i> Copied'; } catch (err) { e.currentTarget.textContent = code; }
});
</script>
@endpush
