@extends('layouts.app')
@section('title', 'Split bills — प्रतीक्षा')
@use('App\Services\Bills\BillCalculator')
@include('bills.partials.assets')

@section('content')
<header class="pk-page-head bl-head">
    <div class="container">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div class="fade-up">
                <span class="pk-eyebrow"><i class="bi bi-receipt"></i> Group bills</span>
                <h1 class="mt-2 mb-1">Split the bill, <span class="text-gradient">fairly</span>.</h1>
                <p class="pk-muted mb-0">Exact to the paisa — itemized, equal or custom, with Nepal's 10% service charge and 13% VAT.</p>
            </div>
            <a href="{{ route('bills.create') }}" class="btn btn-pk fade-up"><i class="bi bi-plus-lg me-1"></i>New bill</a>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-6 col-md-3 reveal">
                <div class="bl-stat"><div class="l"><i class="bi bi-arrow-up-right-circle"></i>You owe</div><div class="v bl-num"><small>NPR</small>{{ BillCalculator::money($youOwe) }}</div></div>
            </div>
            <div class="col-6 col-md-3 reveal" style="--d:.06s">
                <div class="bl-stat"><div class="l"><i class="bi bi-arrow-down-left-circle"></i>Owed to you</div><div class="v bl-num"><small>NPR</small>{{ BillCalculator::money($owedToYou) }}</div></div>
            </div>
            <div class="col-6 col-md-3 reveal" style="--d:.12s">
                <div class="bl-stat"><div class="l"><i class="bi bi-collection"></i>Active bills</div><div class="v">{{ $cards->whereIn('bill.status', ['open', 'settling'])->count() }}</div></div>
            </div>
            <div class="col-6 col-md-3 reveal" style="--d:.18s">
                <div class="bl-stat"><div class="l"><i class="bi bi-envelope-paper"></i>Invitations</div><div class="v">{{ $invites->count() }}</div></div>
            </div>
        </div>
    </div>
</header>

<div class="container py-4">
    @include('bills.partials.errors')

    @if($invites->isNotEmpty())
        <h2 class="bl-section-title mb-2">Invitations</h2>
        <div class="row g-3 mb-4">
            @foreach($invites as $inv)
                <div class="col-md-6 reveal">
                    <div class="pk-card p-3 d-flex flex-wrap align-items-center gap-3" style="border-color:#ddd2ff">
                        <span class="pk-icon violet"><i class="bi bi-envelope-heart"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-truncate">{{ $inv->bill->title }}</div>
                            <div class="bl-sub">{{ $inv->bill->owner->name }} invited you · {{ $inv->bill->joinedParticipants->count() }} people</div>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('bills.invitations.decline', $inv) }}">@csrf<button class="btn btn-ghost btn-sm">Decline</button></form>
                            <form method="POST" action="{{ route('bills.invitations.accept', $inv) }}">@csrf<button class="btn btn-pk btn-sm"><i class="bi bi-check2 me-1"></i>Join</button></form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="bl-section-title">My bills</h2>
        <span class="bl-sub">{{ $cards->count() }} {{ \Illuminate\Support\Str::plural('bill', $cards->count()) }}</span>
    </div>

    @if($cards->isEmpty())
        <div class="pk-card bl-empty reveal">
            <span class="pk-icon violet mb-3"><i class="bi bi-receipt-cutoff"></i></span>
            <h3 class="h5">No bills yet</h3>
            <p class="pk-muted mb-3 mx-auto" style="max-width:420px">Had momo with friends? Add what you ordered, share a link or QR, and everyone sees exactly what they owe.</p>
            <a href="{{ route('bills.create') }}" class="btn btn-pk"><i class="bi bi-plus-lg me-1"></i>Create your first bill</a>
        </div>
    @else
        <div class="row g-3">
            @foreach($cards as $i => $c)
                @php $b = $c['bill']; $bal = $c['balance']; @endphp
                <div class="col-md-6 col-xl-4 reveal" style="--d:{{ min($i, 6) * 0.05 }}s">
                    <a href="{{ route('bills.show', $b) }}" class="pk-card lift bl-card h-100">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="t text-truncate">{{ $b->title }}</div>
                                <div class="bl-sub text-truncate"><i class="bi bi-shop me-1"></i>{{ $b->restaurant_name ?: 'Restaurant' }} · {{ $b->created_at->format('M j') }}</div>
                            </div>
                            <span class="bl-pill {{ $b->status }}">{{ ucfirst($b->status) }}</span>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3 gap-2">
                            <div>
                                <div class="bl-sub">{{ $c['me']->isOwner() ? 'Bill total' : 'Your share' }}</div>
                                <div class="fw-bold fs-4 bl-num" style="letter-spacing:-.03em">NPR {{ BillCalculator::money($c['me']->isOwner() ? BillCalculator::toPaisa($b->total) : $c['share']) }}</div>
                            </div>
                            <div class="bl-stack">
                                @foreach($b->joinedParticipants->take(4) as $p)
                                    @include('bills.partials.avatar', ['p' => $p, 'size' => 'sm'])
                                @endforeach
                                @if($b->joinedParticipants->count() > 4)<span class="bl-av sm" style="--h:230">+{{ $b->joinedParticipants->count() - 4 }}</span>@endif
                            </div>
                        </div>
                        @if(in_array($b->status, ['settling', 'settled']))
                            <div class="bl-mini-bar mt-3 {{ $c['progress'] >= 100 ? 'done' : '' }}" role="progressbar" aria-valuenow="{{ $c['progress'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Collected"><span style="width: {{ $c['progress'] }}%"></span></div>
                            <div class="d-flex justify-content-between bl-sub mt-1"><span>{{ $c['progress'] }}% collected</span>
                                @if($c['me']->isOwner())<span>NPR {{ BillCalculator::money($c['remaining']) }} to collect</span>
                                @elseif($bal)<span class="bl-pill {{ $bal['state'] }} py-0">{{ ['paid' => 'Paid', 'claimed' => 'Awaiting confirmation', 'due' => 'Due', 'covered' => 'Covered'][$bal['state']] }}</span>@endif
                            </div>
                        @else
                            <div class="bl-sub mt-3">
                                @if($c['ok'])<i class="bi bi-check-circle-fill" style="color:var(--pk-success)"></i> Allocations add up
                                @else<i class="bi bi-exclamation-triangle-fill" style="color:var(--pk-warn)"></i> Needs attention
                                @endif
                                · {{ $b->joinedParticipants->count() }} people · {{ ucfirst($b->split_mode) }} split
                            </div>
                        @endif
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <p class="bl-sub mt-4 mb-0"><i class="bi bi-shield-check me-1"></i>Bills are private to the people on them and never affect your place in any queue.</p>
</div>
@endsection
