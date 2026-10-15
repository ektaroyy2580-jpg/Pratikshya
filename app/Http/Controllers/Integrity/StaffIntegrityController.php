<?php

namespace App\Http\Controllers\Integrity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrity\StaffReportRequest;
use App\Models\AuditLog;
use App\Models\QueueExchangeRequest;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\Integrity\DalalEngine;
use App\Services\Integrity\ExchangeException;
use App\Services\Integrity\ExchangeService;
use Illuminate\Http\Request;

/** Staff side of the integrity module, scoped to the staff member's approved locations. */
class StaffIntegrityController extends Controller
{
    public function __construct(private ExchangeService $exchanges)
    {
    }

    public function exchanges(Request $request)
    {
        $this->exchanges->expireStale();
        $locationIds = $request->user()->staffLocationIds();
        abort_if(empty($locationIds), 403, 'You have no assigned location.');

        $pending = QueueExchangeRequest::with('service.location', 'requesterTicket', 'targetTicket')
            ->where('status', 'awaiting_staff')
            ->whereHas('service', fn ($q) => $q->whereIn('location_id', $locationIds))
            ->orderBy('expires_at')->get();
        $recent = QueueExchangeRequest::with('service', 'decider')
            ->whereIn('status', ['completed', 'rejected', 'expired', 'failed'])
            ->whereHas('service', fn ($q) => $q->whereIn('location_id', $locationIds))
            ->latest('updated_at')->take(10)->get();
        $services = Service::with('location')->whereIn('location_id', $locationIds)->orderBy('location_id')->get();
        $service = $services->firstWhere('id', $request->integer('service')) ?? $services->first();

        return view('integrity.staff-exchanges', compact('pending', 'recent', 'services', 'service'));
    }

    public function approve(Request $request, QueueExchangeRequest $exchange)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        return $this->run(function () use ($request, $exchange, $note) {
            $this->exchanges->approve($exchange, $request->user(), $note);
            $s = $exchange->service;

            return "Approved. Tokens {$s->token($exchange->requester_number_before)} and {$s->token($exchange->target_number_before)} have swapped places.";
        });
    }

    public function reject(Request $request, QueueExchangeRequest $exchange)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        return $this->run(function () use ($request, $exchange, $note) {
            $this->exchanges->reject($exchange, $request->user(), $note);

            return 'Exchange rejected. Both tokens are unchanged.';
        });
    }

    /** "Report duplicate-token suspicion" → a staff_report alert for admin review. Takes no action on the token. */
    public function report(StaffReportRequest $request, Ticket $ticket, DalalEngine $engine)
    {
        $data = $request->validated();
        $alert = $engine->staffReport($ticket, $request->user(), $data['reason'], $data['note']);
        AuditLog::record('integrity_staff_report', $alert, ['ticket_id' => $ticket->id, 'reason' => $data['reason']]);

        return back()->with('ok', "Report for {$ticket->token()} sent to admin review. The token itself has not been changed.");
    }

    private function run(callable $action)
    {
        try {
            return back()->with('ok', $action());
        } catch (ExchangeException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }
    }
}
