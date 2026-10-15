{{-- English practice widget for the ticket page. Needs: $ticket (App\Models\Ticket). Safe if data is missing. --}}
@php
    $enMinutes = null;
    try {
        if (isset($ticket) && $ticket && \Illuminate\Support\Facades\Route::has('english.scenarios') && auth()->check() && (int) $ticket->user_id === (int) auth()->id() && $ticket->status === 'waiting') {
            $enLow = (int) ($ticket->stateArray()['wait_low'] ?? 0);
            $enMinutes = $enLow >= 12 ? 10 : ($enLow >= 6 ? 5 : 3);
        }
    } catch (\Throwable $e) {
        $enMinutes = null;
    }
@endphp
@if($enMinutes)
    <a href="{{ route('english.scenarios', ['minutes' => $enMinutes]) }}" class="pk-card lift en-widget text-decoration-none text-reset" style="display:flex;align-items:center;gap:.9rem;padding:1rem 1.1rem">
        <span class="pk-icon violet"><i class="bi bi-mic"></i></span>
        <span class="flex-grow-1" style="min-width:0">
            <span class="d-block fw-bold">Practise English for {{ $enMinutes }} minutes</span>
            <span class="d-block small pk-muted">Interview, café or office conversations — speak or type, get friendly feedback.</span>
        </span>
        <i class="bi bi-arrow-right" style="color:var(--pk-blue)"></i>
    </a>
@endif
