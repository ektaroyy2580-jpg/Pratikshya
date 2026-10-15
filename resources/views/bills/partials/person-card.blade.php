{{-- $p participant, $row calc person row, $bal balance row|null, $isMe, $mode split mode, $billStatus --}}
@use('App\Services\Bills\BillCalculator')
<div class="pk-card bl-person {{ $isMe ? 'me' : '' }}" data-p="{{ $p->id }}">
    <div class="d-flex align-items-center gap-2 mb-2">
        @include('bills.partials.avatar', ['p' => $p])
        <div class="min-w-0 flex-grow-1">
            <div class="fw-bold text-truncate">{{ $p->display_name }}@if($isMe) <span class="chip py-0 px-2 ms-1">You</span>@endif</div>
            <div class="bl-sub">{{ $p->isOwner() ? 'Paid the restaurant' : 'Member' }}</div>
        </div>
        @if($bal && in_array($billStatus, ['settling', 'settled']))
            <span class="bl-pill {{ $bal['state'] }}">{{ ['paid' => 'Paid', 'claimed' => 'Claimed', 'due' => 'Due', 'covered' => 'Covered'][$bal['state']] }}</span>
        @endif
    </div>
    <div class="amt bl-num"><small>NPR</small><span data-f="total" data-paisa="{{ $row['total'] }}">{{ BillCalculator::money($row['total']) }}</span></div>
    @if($mode === 'custom')
        <ul class="bl-receipt mt-2">
            <li class="muted"><span>Custom amount set by the owner</span><span></span></li>
        </ul>
    @else
        <ul class="bl-receipt mt-2">
            <li><span>{{ $mode === 'itemized' ? 'Items' : 'Equal share' }}</span><span data-f="items" data-paisa="{{ $row['items'] }}">{{ BillCalculator::money($row['items']) }}</span></li>
            <li class="neg" @if(!$row['discount']) hidden @endif data-row="discount"><span>Discount</span><span data-f="discount" data-prefix="−" data-paisa="{{ $row['discount'] }}">−{{ BillCalculator::money($row['discount']) }}</span></li>
            <li><span>Service charge</span><span data-f="service" data-paisa="{{ $row['service'] }}">{{ BillCalculator::money($row['service']) }}</span></li>
            <li><span>VAT</span><span data-f="vat" data-paisa="{{ $row['vat'] }}">{{ BillCalculator::money($row['vat']) }}</span></li>
            <li @if(!$row['tip']) hidden @endif data-row="tip"><span>Tip</span><span data-f="tip" data-paisa="{{ $row['tip'] }}">{{ BillCalculator::money($row['tip']) }}</span></li>
        </ul>
        @if($mode === 'itemized')
            <div class="bl-lines mt-2" data-f="lines">
                @forelse($row['lines'] as $l)
                    <div class="text-truncate">{{ $l['name'] }}@if($l['of'] > $l['weight']) <span>({{ $l['weight'] }}/{{ $l['of'] }})</span>@endif · {{ BillCalculator::money($l['amount']) }}</div>
                @empty
                    <div>No items yet</div>
                @endforelse
            </div>
        @endif
    @endif
    @if($bal && in_array($billStatus, ['settling', 'settled']) && ! $bal['is_owner'])
        <div class="mt-auto pt-3">
            @php $pctPaid = $bal['share'] > 0 ? (int) floor(min($bal['share'], $bal['confirmed']) * 100 / $bal['share']) : 100; @endphp
            <div class="bl-mini-bar {{ $pctPaid >= 100 ? 'done' : '' }}"><span style="width: {{ $pctPaid }}%"></span></div>
            <div class="d-flex justify-content-between bl-sub mt-1 bl-num"><span>Confirmed {{ BillCalculator::money($bal['confirmed']) }}</span><span>Left {{ BillCalculator::money($bal['remaining']) }}</span></div>
        </div>
    @endif
</div>
