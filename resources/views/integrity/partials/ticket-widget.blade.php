{{--
    Queue exchange widget for the ticket page. Usage: @include('integrity.partials.ticket-widget', ['ticket' => $ticket])
    Renders only for the ticket owner while the token is waiting. Self-contained (own CSS/JS, safe if data is missing).
--}}
@php
    $igTicket = $ticket ?? null;
    $igUser = auth()->user();
    $igShow = $igTicket && $igUser && $igTicket->user_id === $igUser->id && $igTicket->status === 'waiting' && $igTicket->service;
    if ($igShow) {
        $igIncoming = \App\Models\QueueExchangeRequest::with('service')->where('target_ticket_id', $igTicket->id)
            ->where('recipient_id', $igUser->id)->open()->where('expires_at', '>', now())->latest('id')->get();
        $igOutgoing = \App\Models\QueueExchangeRequest::with('service')->where('requester_ticket_id', $igTicket->id)
            ->open()->where('expires_at', '>', now())->latest('id')->first();
    }
@endphp
@if($igShow)
    @once
        @push('head')
            <link rel="stylesheet" href="{{ asset('css/modules/integrity.css') }}?v={{ @filemtime(public_path('css/modules/integrity.css')) }}">
        @endpush
        @push('scripts')
            <script src="{{ asset('js/modules/integrity.js') }}?v={{ @filemtime(public_path('js/modules/integrity.js')) }}"></script>
        @endpush
    @endonce
    <section class="pk-card ig-widget" aria-labelledby="igWidgetHead{{ $igTicket->id }}">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <h3 id="igWidgetHead{{ $igTicket->id }}" class="d-flex align-items-center gap-2">
                <span class="pk-icon violet" style="width:32px;height:32px;font-size:.9rem"><i class="bi bi-arrow-left-right"></i></span> Swap places
            </h3>
            <a href="{{ route('integrity.exchange.index') }}" class="small fw-semibold text-decoration-none">All exchanges <i class="bi bi-chevron-right"></i></a>
        </div>
        @foreach($igIncoming as $x)
            <div class="mb-3">@include('integrity.partials._incoming', ['x' => $x])</div>
        @endforeach
        @if($igOutgoing)
            @include('integrity.partials._outgoing', ['x' => $igOutgoing])
        @else
            @include('integrity.partials._request-form', ['ticket' => $igTicket])
        @endif
    </section>
@endif
