<?php

namespace App\Http\Controllers\Integrity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrity\ExchangeRequestRequest;
use App\Models\QueueExchangeRequest;
use App\Models\Ticket;
use App\Services\Integrity\ExchangeException;
use App\Services\Integrity\ExchangeService;
use Illuminate\Http\Request;

/** Voluntary queue exchange for users. Every lookup is scoped to the signed-in user. */
class ExchangeController extends Controller
{
    public function __construct(private ExchangeService $exchanges)
    {
    }

    public function index(Request $request)
    {
        $this->exchanges->expireStale();
        $me = $request->user();

        $tickets = $me->tickets()->with('service.location', 'counter')->where('status', 'waiting')->orderBy('created_at')->get();
        $incoming = QueueExchangeRequest::with('service', 'requesterTicket', 'targetTicket')
            ->where('recipient_id', $me->id)->open()->latest('id')->get();
        $outgoing = QueueExchangeRequest::with('service', 'requesterTicket', 'targetTicket')
            ->where('requester_id', $me->id)->open()->latest('id')->get();
        $history = QueueExchangeRequest::with('service')
            ->where(fn ($q) => $q->where('requester_id', $me->id)->orWhere('recipient_id', $me->id))
            ->whereNotIn('status', QueueExchangeRequest::OPEN)->latest('updated_at')->take(8)->get();

        $signature = $this->signature($me->id);

        return view('integrity.exchange', compact('tickets', 'incoming', 'outgoing', 'history', 'me', 'signature'));
    }

    /** Polling: lets the page refresh itself when a request arrives or changes. */
    public function poll(Request $request)
    {
        $this->exchanges->expireStale();
        $me = $request->user();

        return response()->json(['message' => 'Exchange status retrieved', 'data' => [
            'incoming' => QueueExchangeRequest::where('recipient_id', $me->id)->where('status', 'pending')->count(),
            'signature' => $this->signature($me->id),
        ]]);
    }

    private function signature(int $userId): string
    {
        $rows = QueueExchangeRequest::where(fn ($q) => $q->where('requester_id', $userId)->orWhere('recipient_id', $userId))
            ->latest('id')->take(20)->get(['id', 'status']);

        return md5($rows->map(fn ($r) => $r->id.':'.$r->status)->implode('|'));
    }

    public function store(ExchangeRequestRequest $request, Ticket $ticket)
    {
        return $this->run(function () use ($request, $ticket) {
            $x = $this->exchanges->request($ticket, $request->user(), $request->validated('target_token'));

            return 'Request sent to '.$x->service->token($x->target_number_before).'. Nothing changes unless they accept'
                .($x->service->requires_exchange_approval ? ' and staff approve.' : '.');
        });
    }

    public function accept(Request $request, QueueExchangeRequest $exchange)
    {
        return $this->run(function () use ($request, $exchange) {
            $x = $this->exchanges->accept($exchange, $request->user());

            return $x->status === 'awaiting_staff'
                ? 'Accepted. Staff at the counter will approve it before positions change.'
                : 'Exchange complete. Your token is now '.$x->targetTicket->fresh('service')->token().'.';
        });
    }

    public function decline(Request $request, QueueExchangeRequest $exchange)
    {
        return $this->run(function () use ($request, $exchange) {
            $this->exchanges->decline($exchange, $request->user());

            return 'Request declined. Your token is unchanged.';
        });
    }

    public function cancel(Request $request, QueueExchangeRequest $exchange)
    {
        return $this->run(function () use ($request, $exchange) {
            $this->exchanges->cancel($exchange, $request->user());

            return 'Request withdrawn. Your token is unchanged.';
        });
    }

    private function run(callable $action)
    {
        // Back to where the action started (exchange page or the ticket widget).
        $fallback = route('integrity.exchange.index');
        try {
            return back(302, [], $fallback)->with('ok', $action());
        } catch (ExchangeException $e) {
            return back(302, [], $fallback)->withInput()->withErrors(['queue' => $e->getMessage()]);
        }
    }
}
