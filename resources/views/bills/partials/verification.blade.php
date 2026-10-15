{{-- $v: verification array from BillCalculator. Re-rendered client-side by bills-show.js (same markup). --}}
@use('App\Services\Bills\BillCalculator')
<div class="pk-card bl-verify {{ $v['ok'] ? 'ok' : 'bad' }}" id="verifyPanel" aria-live="polite">
    <div class="head">
        <span class="seal"><i class="bi {{ $v['ok'] ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill' }}"></i></span>
        <div class="min-w-0">
            <div class="fw-bold" data-v="title">{{ $v['ok'] ? 'All allocations add up ✓' : 'Allocations need attention' }}</div>
            <div class="bl-sub bl-num" data-v="sub">Shares total NPR {{ BillCalculator::money($v['sum']) }} of NPR {{ BillCalculator::money($v['total']) }}{{ $v['difference'] !== 0 ? ' · off by NPR '.BillCalculator::money(abs($v['difference'])) : ' · exact to the paisa' }}</div>
        </div>
    </div>
    <ul class="bl-checks" data-v="checks">
        @foreach($v['checks'] as $c)
            <li><i class="bi {{ $c['ok'] ? 'bi-check-circle-fill ok' : 'bi-x-circle-fill no' }}"></i><span>{{ $c['label'] }}</span><span class="d">{{ $c['detail'] }}</span></li>
        @endforeach
    </ul>
    <div data-v="notes">
        @foreach($v['errors'] as $e)
            <div class="small fw-semibold mt-2" style="color:#a3153a"><i class="bi bi-x-octagon me-1"></i>{{ $e }}</div>
        @endforeach
        @foreach($v['warnings'] as $w)
            <div class="small fw-semibold mt-2" style="color:#8a4b04"><i class="bi bi-info-circle me-1"></i>{{ $w }}</div>
        @endforeach
    </div>
</div>
