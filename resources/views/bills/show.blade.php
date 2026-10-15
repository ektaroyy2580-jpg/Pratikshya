@extends('layouts.app')
@section('title', $bill->title.' — Bills — प्रतीक्षा')
@use('App\Services\Bills\BillCalculator')
@include('bills.partials.assets')
@php
    $t = $calc['totals'];
    $people = $bill->joinedParticipants;
    $mode = $bill->split_mode;
    $open = $bill->status === 'open';
    $myRow = $calc['people'][$me->id] ?? null;
    $myBal = $balances['rows'][$me->id] ?? null;
    $canSelect = $open && $mode === 'itemized' && ($isOwner || $bill->members_can_select);
    $showCustom = $mode === 'custom' || $bill->tip_allocation === 'custom';
    $unassigned = collect($calc['items'])->contains(fn ($i) => ! $i['assigned'] && $i['total'] > 0);
@endphp

@section('content')
<header class="pk-page-head bl-head">
    <div class="container">
        <div class="bl-crumbs mb-2">
            <a class="pk-breadcrumb" href="{{ route('bills.index') }}"><i class="bi bi-receipt me-1"></i>Bills</a>
            <i class="bi bi-chevron-right sep"></i><span class="pk-breadcrumb text-truncate" style="max-width:220px">{{ $bill->title }}</span>
        </div>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div class="min-w-0 fade-up">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="bl-pill {{ $bill->status }}" id="statusPill">{{ ucfirst($bill->status) }}</span>
                    <span class="chip"><i class="bi bi-diagram-3"></i>{{ ['equal' => 'Equal split', 'itemized' => 'By item', 'custom' => 'Custom amounts'][$mode] }}</span>
                </div>
                <h1 class="mb-1">{{ $bill->title }}</h1>
                <div class="pk-muted fw-semibold small d-flex flex-wrap gap-3">
                    @if($bill->nearby_place_id && \Illuminate\Support\Facades\Route::has('cafes.show'))
                        <span><i class="bi bi-shop me-1"></i><a href="{{ route('cafes.show', $bill->nearby_place_id) }}#bill">{{ $bill->restaurant_name ?: 'Café' }}</a></span>
                    @else
                        <span><i class="bi bi-shop me-1"></i>{{ $bill->restaurant_name ?: 'Restaurant not set' }}</span>
                    @endif
                    <span><i class="bi bi-person me-1"></i>Hosted by {{ $bill->owner->name }}</span>
                    <span><i class="bi bi-calendar3 me-1"></i>{{ $bill->created_at->format('M j, Y') }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 fade-up">
                @if($isOwner && $open)
                    <a href="{{ route('bills.edit', $bill) }}" class="btn btn-ghost"><i class="bi bi-pencil me-1"></i>Edit items</a>
                @endif
                @if(in_array($bill->status, ['settling', 'settled']))
                    <a href="{{ route('bills.settle', $bill) }}" class="btn btn-pk"><i class="bi bi-wallet2 me-1"></i>{{ $isOwner ? 'Settlement tracker' : (($myBal['remaining'] ?? 0) > 0 ? 'Pay my share' : 'Settlement') }}</a>
                @elseif($isOwner && $open)
                    <form method="POST" action="{{ route('bills.status', $bill) }}">@csrf<input type="hidden" name="to" value="settling">
                        <button class="btn btn-pk" @disabled(! $calc['verification']['ok'] || $unassigned || $people->count() < 2) title="{{ $people->count() < 2 ? 'Invite at least one person first' : ($unassigned ? 'Assign every item first' : '') }}"><i class="bi bi-lock me-1"></i>Lock &amp; start settling</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-6 col-lg-3 reveal"><div class="bl-stat accent"><div class="l"><i class="bi bi-receipt"></i>Bill total</div><div class="v bl-num"><small>NPR</small><span data-g="grand" data-paisa="{{ $t['grand_total'] }}">{{ BillCalculator::money($t['grand_total']) }}</span></div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.06s"><div class="bl-stat"><div class="l"><i class="bi bi-person-check"></i>Your share</div><div class="v bl-num"><small>NPR</small><span data-g="mine" data-paisa="{{ $myRow['total'] ?? 0 }}">{{ BillCalculator::money($myRow['total'] ?? 0) }}</span></div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.12s"><div class="bl-stat"><div class="l"><i class="bi bi-graph-up-arrow"></i>Collected</div><div class="v">{{ $balances['progress'] }}<small>%</small></div><div class="bl-mini-bar mt-2 {{ $balances['progress'] >= 100 ? 'done' : '' }}"><span style="width: {{ $balances['progress'] }}%"></span></div></div></div>
            <div class="col-6 col-lg-3 reveal" style="--d:.18s"><div class="bl-stat"><div class="l"><i class="bi bi-people"></i>People</div><div class="d-flex align-items-center justify-content-between mt-2"><span class="v mt-0">{{ $people->count() }}</span><div class="bl-stack">@foreach($people->take(5) as $p)@include('bills.partials.avatar', ['p' => $p, 'size' => 'sm'])@endforeach</div></div></div></div>
        </div>
    </div>
</header>

<div class="container py-4">
    @include('bills.partials.errors')

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Per-person cards --}}
            <section aria-labelledby="whoH" class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 id="whoH" class="bl-section-title">Who pays what</h2>
                    <span class="bl-sub">Exact to the paisa</span>
                </div>
                @if($people->count() < 2 && $isOwner)
                    <div class="bl-demo-note mb-3" style="background:var(--pk-tint);color:var(--pk-ink-2)"><i class="bi bi-person-plus mt-1"></i><span>It's just you so far — share the invite link or QR on the right so friends can join.</span></div>
                @endif
                <div class="row g-3" id="peopleGrid">
                    @foreach($people as $i => $p)
                        <div class="col-sm-6 col-xl-4 reveal" style="--d:{{ min($i, 6) * 0.05 }}s">
                            @include('bills.partials.person-card', ['p' => $p, 'row' => $calc['people'][$p->id], 'bal' => $balances['rows'][$p->id] ?? null, 'isMe' => $p->id === $me->id, 'mode' => $mode, 'billStatus' => $bill->status])
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Items & assignment --}}
            <section class="pk-card p-3 p-md-4 mb-4 reveal" id="assign" aria-labelledby="assignH">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h2 id="assignH" class="bl-section-title">{{ $mode === 'itemized' ? 'Who had what' : 'Items' }}</h2>
                        <div class="bl-sub">
                            @if($mode === 'itemized')
                                @if($isOwner && $open) Tick who shared each item. Weights split a shared item unevenly (e.g. 2 = two portions).
                                @elseif($canSelect) Tick the items you had — your share updates instantly.
                                @elseif($open) The owner assigns items for this bill.
                                @else Locked for settlement. @endif
                            @else
                                {{ $mode === 'equal' ? 'Everything is split equally between everyone.' : 'The owner sets each person\'s amount below.' }}
                            @endif
                        </div>
                    </div>
                    <span class="chip"><i class="bi bi-basket"></i>{{ $bill->items->count() }} items · NPR {{ BillCalculator::money($t['subtotal']) }}</span>
                </div>

                @if($mode === 'itemized')
                    <form method="POST" action="{{ route('bills.allocations', $bill) }}" id="matrixForm">
                        @csrf
                        <div class="bl-matrix-wrap">
                            <table class="bl-matrix">
                                <thead>
                                    <tr>
                                        <th class="item" scope="col">Item</th>
                                        @foreach($people as $p)
                                            <th scope="col" class="{{ $p->id === $me->id ? 'col-me' : '' }}"><div class="d-flex flex-column align-items-center gap-1">@include('bills.partials.avatar', ['p' => $p, 'size' => 'sm'])<span class="text-truncate" style="max-width:70px">{{ $p->id === $me->id ? 'You' : \Illuminate\Support\Str::of($p->display_name)->before(' ') }}</span></div></th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bill->items as $item)
                                        @php $allocs = $item->allocations->keyBy('bill_participant_id'); $assigned = $calc['items'][$item->id]['assigned'] ?? false; @endphp
                                        <tr class="{{ $assigned ? '' : 'unassigned' }}" data-item="{{ $item->id }}" data-shared="{{ $item->is_shared ? 1 : 0 }}">
                                            <th class="item" scope="row">
                                                <div class="text-truncate" style="max-width:200px">{{ $item->name }} @if($item->is_shared)<i class="bi bi-people-fill ms-1" style="color:var(--pk-violet)" title="Shared item"></i>@endif</div>
                                                <div class="price bl-num">{{ $item->quantity }} × {{ BillCalculator::money(BillCalculator::toPaisa($item->unit_price)) }} = {{ BillCalculator::money($calc['items'][$item->id]['total'] ?? 0) }}</div>
                                            </th>
                                            @foreach($people as $p)
                                                @php
                                                    $on = $allocs->has($p->id);
                                                    $editable = $open && ($isOwner || ($canSelect && $p->id === $me->id));
                                                @endphp
                                                <td class="{{ $p->id === $me->id ? 'col-me' : '' }}">
                                                    <label class="bl-check" aria-label="{{ $item->name }} — {{ $p->display_name }}">
                                                        <input type="checkbox" name="alloc[{{ $item->id }}][{{ $p->id }}]" value="1" data-cell data-item="{{ $item->id }}" data-p="{{ $p->id }}" @checked($on) @disabled(! $editable)>
                                                        <span class="box"><svg viewBox="0 0 16 16"><path d="M3 8.5l3.2 3L13 4.5"/></svg></span>
                                                    </label>
                                                    @if($item->is_shared && $isOwner && $open)
                                                        <div><input class="bl-weight bl-num" type="number" min="1" max="20" name="weight[{{ $item->id }}][{{ $p->id }}]" value="{{ $allocs[$p->id]->weight ?? 1 }}" data-weight data-item="{{ $item->id }}" data-p="{{ $p->id }}" aria-label="Share weight for {{ $p->display_name }}" @disabled(! $on)></div>
                                                    @elseif($item->is_shared && $on && ($allocs[$p->id]->weight ?? 1) > 1)
                                                        <div class="bl-sub">×{{ $allocs[$p->id]->weight }}</div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($isOwner && $open)
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                <span class="bl-sub" id="matrixHint"><i class="bi bi-lightning-charge me-1"></i>Changes preview instantly; save to make them official.</span>
                                <button class="btn btn-pk" id="matrixSave" type="submit"><i class="bi bi-check2-all me-1"></i>Save assignments</button>
                            </div>
                        @endif
                    </form>
                @else
                    <ul class="bl-receipt">
                        @forelse($bill->items as $item)
                            <li><span>{{ $item->quantity }} × {{ $item->name }} @if($item->is_shared)<i class="bi bi-people-fill ms-1" style="color:var(--pk-violet)"></i>@endif</span><span>{{ BillCalculator::money($calc['items'][$item->id]['total'] ?? 0) }}</span></li>
                        @empty
                            <li class="muted"><span>No items yet</span><span></span></li>
                        @endforelse
                    </ul>
                @endif
            </section>

            {{-- Custom amounts / custom tips (owner) --}}
            @if($showCustom)
                <section class="pk-card p-3 p-md-4 mb-4 reveal" id="custom" aria-labelledby="customH">
                    <h2 id="customH" class="bl-section-title mb-1">{{ $mode === 'custom' ? 'Custom amounts' : 'Custom tip shares' }}</h2>
                    <p class="bl-sub mb-3">{{ $mode === 'custom' ? 'Amounts must add up to exactly NPR '.BillCalculator::money($t['grand_total']).'.' : 'Tip shares must add up to exactly NPR '.BillCalculator::money($t['tip']).'.' }}</p>
                    <form method="POST" action="{{ route('bills.custom', $bill) }}" id="customForm">
                        @csrf
                        <div class="row g-2">
                            @foreach($people as $p)
                                <div class="col-sm-6">
                                    <label class="form-label small mb-1 d-flex align-items-center gap-2" for="c{{ $p->id }}">@include('bills.partials.avatar', ['p' => $p, 'size' => 'sm']) {{ $p->display_name }}</label>
                                    <div class="bl-input-group pre">
                                        <span class="suffix">NPR</span>
                                        @if($mode === 'custom')
                                            <input class="form-control bl-num" id="c{{ $p->id }}" name="amounts[{{ $p->id }}]" inputmode="decimal" data-custom value="{{ $p->custom_amount }}" placeholder="0.00" @disabled(! ($isOwner && $open))>
                                        @else
                                            <input class="form-control bl-num" id="c{{ $p->id }}" name="tips[{{ $p->id }}]" inputmode="decimal" data-custom value="{{ $p->custom_tip }}" placeholder="0.00" @disabled(! ($isOwner && $open))>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                            <div class="fw-bold bl-num" id="customSum" data-target="{{ $mode === 'custom' ? $t['grand_total'] : $t['tip'] }}"></div>
                            @if($isOwner && $open)<button class="btn btn-pk" type="submit"><i class="bi bi-check2 me-1"></i>Save amounts</button>@endif
                        </div>
                    </form>
                </section>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="d-flex flex-column gap-4">
                @include('bills.partials.verification', ['v' => $calc['verification']])

                <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="rcptH">
                    <h2 id="rcptH" class="bl-section-title mb-2"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Receipt</h2>
                    <ul class="bl-receipt">
                        <li><span>Subtotal</span><span>{{ BillCalculator::money($t['subtotal']) }}</span></li>
                        @if($t['discount'])<li class="neg"><span>Discount {{ $bill->discount_type === 'percent' ? '('.rtrim(rtrim($bill->discount_value, '0'), '.').'%)' : '' }}</span><span>−{{ BillCalculator::money($t['discount']) }}</span></li>@endif
                        <li><span>Service charge ({{ rtrim(rtrim($bill->service_charge_percent, '0'), '.') }}%)</span><span>{{ BillCalculator::money($t['service']) }}</span></li>
                        <li><span>VAT ({{ rtrim(rtrim($bill->vat_percent, '0'), '.') }}%)</span><span>{{ BillCalculator::money($t['vat']) }}</span></li>
                        @if($t['tip'])<li><span>Tip · {{ ['equal' => 'split equally', 'proportional' => 'by share', 'custom' => 'custom'][$bill->tip_allocation] }}</span><span>{{ BillCalculator::money($t['tip']) }}</span></li>@endif
                        <li class="grand"><span>Total</span><span>NPR {{ BillCalculator::money($t['grand_total']) }}</span></li>
                    </ul>
                    <p class="bl-sub mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>{{ $calc['order'] }}. Rounded half-up per step; leftover paisa assigned by largest remainder.</p>
                    @if($bill->payment_note)
                        <div class="mt-3 p-2 px-3 rounded-3 small" style="background:var(--pk-bg)"><i class="bi bi-wallet2 me-1 text-primary"></i><strong>How to pay {{ \Illuminate\Support\Str::of($bill->owner->name)->before(' ') }}:</strong> {{ $bill->payment_note }}</div>
                    @endif
                </section>

                @if($isOwner)
                    <section class="pk-card p-3 p-md-4 reveal" id="invite" aria-labelledby="inviteH">
                        <h2 id="inviteH" class="bl-section-title mb-1"><i class="bi bi-person-plus me-2 text-primary"></i>Invite friends</h2>
                        @if(! $open)
                            <p class="bl-sub mb-0">Invites are closed while the bill is {{ $bill->status }}.</p>
                        @else
                            @if($inviteUrl)
                                <p class="bl-sub mb-3">Anyone with this link must log in and tap “Join this bill”. Expires {{ $bill->invite_expires_at->diffForHumans() }}.</p>
                                <div class="d-flex justify-content-center mb-3"><div class="bl-qr" id="qrBox" data-url="{{ $inviteUrl }}" role="img" aria-label="QR code of the invite link"><span class="skeleton" style="width:100%;height:100%"></span></div></div>
                                <div class="bl-link">
                                    <input class="form-control form-control-sm" id="inviteUrl" value="{{ $inviteUrl }}" readonly aria-label="Invite link">
                                    <button type="button" class="btn btn-ghost btn-sm bl-copy" id="copyLink" aria-label="Copy invite link"><i class="bi bi-clipboard"></i></button>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <form method="POST" action="{{ route('bills.invite.rotate', $bill) }}" class="flex-fill">@csrf<button class="btn btn-ghost btn-sm w-100"><i class="bi bi-arrow-repeat me-1"></i>New link</button></form>
                                    <form method="POST" action="{{ route('bills.invite.revoke', $bill) }}" class="flex-fill" onsubmit="return confirm('Revoke this link? It will stop working immediately.')">@csrf<button class="btn btn-ghost btn-sm w-100 text-danger"><i class="bi bi-slash-circle me-1"></i>Revoke</button></form>
                                </div>
                            @else
                                <p class="bl-sub mb-2">The invite link is {{ $bill->invite_revoked_at ? 'revoked' : 'expired' }}.</p>
                                <form method="POST" action="{{ route('bills.invite.rotate', $bill) }}">@csrf<button class="btn btn-ghost btn-sm w-100"><i class="bi bi-link-45deg me-1"></i>Create a new link</button></form>
                            @endif
                            <hr class="my-3">
                            <form method="POST" action="{{ route('bills.invite.email', $bill) }}">
                                @csrf
                                <label class="form-label small" for="inviteEmail">Invite a Pratikshya user by email</label>
                                <div class="d-flex gap-2">
                                    <input class="form-control form-control-sm" type="email" id="inviteEmail" name="email" required maxlength="190" placeholder="friend@example.com" autocomplete="off">
                                    <button class="btn btn-pk btn-sm"><i class="bi bi-send"></i><span class="visually-hidden">Send invitation</span></button>
                                </div>
                            </form>
                            @if($pendingInvites->isNotEmpty())
                                <div class="mt-3">
                                    <div class="bl-sub fw-bold mb-1">Waiting to accept</div>
                                    @foreach($pendingInvites as $pi)
                                        <div class="d-flex align-items-center gap-2 py-1">@include('bills.partials.avatar', ['p' => $pi, 'size' => 'sm'])<span class="small fw-semibold">{{ $pi->display_name }}</span><span class="bl-pill claimed ms-auto">Invited</span></div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </section>
                @endif

                <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="pplH">
                    <h2 id="pplH" class="bl-section-title mb-2"><i class="bi bi-people me-2 text-primary"></i>People</h2>
                    @foreach($people as $p)
                        <div class="d-flex align-items-center gap-2 py-2 {{ $loop->first ? '' : 'border-top' }}">
                            @include('bills.partials.avatar', ['p' => $p, 'size' => 'sm'])
                            <div class="min-w-0 flex-grow-1"><div class="small fw-bold text-truncate">{{ $p->display_name }}</div><div class="bl-sub" style="font-size:.74rem">{{ $p->isOwner() ? 'Owner' : 'Joined '.$p->joined_at?->diffForHumans() }}</div></div>
                            @if($isOwner && $open && ! $p->isOwner())
                                <form method="POST" action="{{ route('bills.participants.remove', [$bill, $p]) }}" onsubmit="return confirm('Remove {{ e(addslashes($p->display_name)) }} from this bill?')">@csrf<button class="bl-icon-btn" aria-label="Remove {{ $p->display_name }}"><i class="bi bi-person-dash"></i></button></form>
                            @endif
                        </div>
                    @endforeach
                </section>

                @if($isOwner)
                    <section class="pk-card p-3 p-md-4 reveal" aria-labelledby="manageH">
                        <h2 id="manageH" class="bl-section-title mb-2"><i class="bi bi-gear me-2 text-primary"></i>Manage</h2>
                        <div class="d-grid gap-2">
                            @if($bill->status === 'settling')
                                <form method="POST" action="{{ route('bills.status', $bill) }}">@csrf<input type="hidden" name="to" value="open"><button class="btn btn-ghost btn-sm w-100"><i class="bi bi-unlock me-1"></i>Reopen for edits</button></form>
                            @endif
                            @if($bill->status !== 'archived')
                                <form method="POST" action="{{ route('bills.status', $bill) }}" onsubmit="return confirm('Archive this bill? It becomes read-only.')">@csrf<input type="hidden" name="to" value="archived"><button class="btn btn-ghost btn-sm w-100"><i class="bi bi-archive me-1"></i>Archive bill</button></form>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php $billData = ['input' => $engineInput, 'me' => $me->id, 'owner' => $isOwner, 'open' => $open, 'mineUrl' => route('bills.items.mine', [$bill, 0])]; @endphp
<script id="billData" type="application/json">@json($billData)</script>
<script src="{{ asset('vendor/qrcode/qrcode.js') }}"></script>
<script src="{{ asset('js/modules/bills-calc.js') }}?v={{ filemtime(public_path('js/modules/bills-calc.js')) }}"></script>
<script src="{{ asset('js/modules/bills-show.js') }}?v={{ filemtime(public_path('js/modules/bills-show.js')) }}"></script>
@endpush
