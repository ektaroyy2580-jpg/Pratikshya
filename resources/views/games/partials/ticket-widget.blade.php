{{--
    Games widget for the ticket page. Usage: @include('games.partials.ticket-widget', ['ticket' => $ticket])
    Shows open Quick Brain Battles in this ticket's queue + a Play link. Safe if data is missing.
--}}
@php
    $gmTicket = $ticket ?? null;
    $gmOpen = collect();
    $gmSeat = null;
    try {
        if ($gmTicket && auth()->check() && $gmTicket->user_id === auth()->id() && \Illuminate\Support\Facades\Route::has('games.hub')) {
            $gmSvc = app(\App\Services\Games\CompetitionService::class);
            $gmSeat = $gmSvc->activeSeat(auth()->user());
            if ($gmTicket->status === 'waiting') {
                $gmOpen = $gmSvc->openIn($gmTicket->service_id, 3)->reject(fn ($c) => $gmSeat && $c->id === $gmSeat->competition_id);
            }
        }
    } catch (\Throwable $e) {
        $gmOpen = collect();
    }
@endphp
@if($gmTicket && \Illuminate\Support\Facades\Route::has('games.hub'))
<div class="pk-card p-3" style="border-radius:16px">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="pk-icon violet" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-lightning-charge"></i></span>
        <div class="flex-grow-1">
            <div class="fw-bold" style="line-height:1.2">Quick Brain Battle</div>
            <div class="small" style="color:var(--pk-muted)">Play people waiting in this queue</div>
        </div>
    </div>
    @if($gmSeat)
        <a href="{{ route('games.competitions.show', $gmSeat->competition_id) }}" class="btn btn-pk btn-sm w-100">Return to your battle</a>
    @else
        @forelse($gmOpen as $c)
            <div class="d-flex align-items-center justify-content-between small py-1 border-bottom" style="border-color:var(--pk-line)!important">
                <span><b>{{ $c->categoryLabel() }}</b> · {{ $c->players->count() }}/{{ $c->max_players }} players</span>
                <form method="POST" action="{{ route('games.competitions.join', $c) }}" class="m-0">@csrf<button class="btn btn-link btn-sm p-0 fw-bold text-decoration-none">Join</button></form>
            </div>
        @empty
            <div class="small mb-1" style="color:var(--pk-muted)">{{ $gmTicket->status === 'waiting' ? 'No open battles yet — start one.' : 'Battles are available while you are waiting.' }}</div>
        @endforelse
        <a href="{{ route('games.hub') }}" class="btn btn-pk btn-sm w-100 mt-2"><i class="bi bi-controller me-1"></i>Play</a>
    @endif
</div>
@endif
