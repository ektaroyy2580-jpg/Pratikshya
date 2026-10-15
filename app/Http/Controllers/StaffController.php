<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyDocumentsRequest;
use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\DocumentVerification;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\Queue\QueueException;
use App\Services\Queue\QueueManager;
use Illuminate\Http\Request;

/** Staff portal. Every action is scoped to the staff member's approved locations. */
class StaffController extends Controller
{
    public function __construct(private QueueManager $queues)
    {
    }

    public function dashboard(Request $request)
    {
        $locationIds = $request->user()->staffLocationIds();
        abort_if(empty($locationIds), 403, 'You have no assigned location.');

        $services = Service::with('location')->whereIn('location_id', $locationIds)->orderBy('location_id')->get();
        $service = $services->firstWhere('id', $request->integer('service')) ?? $services->first();
        abort_unless($service, 404);

        $service->load('counters');
        $active = $service->tickets()->with('user', 'counter', 'verifications')->whereIn('status', Ticket::ACTIVE)->orderBy('number')->get();
        $done = $service->tickets()->with('counter')->whereIn('status', ['completed', 'absent', 'cancelled', 'left'])->latest('updated_at')->take(8)->get();
        $counters = Counter::where('location_id', $service->location_id)->orderBy('number')->get();
        $estimate = $service->estimate();
        $completedToday = $service->tickets()->where('status', 'completed')->whereDate('completed_at', today())->count();

        return view('pages.staff', compact('services', 'service', 'active', 'done', 'counters', 'estimate', 'completedToday'));
    }

    public function callNext(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        $counter = $request->filled('counter_id')
            ? Counter::where('location_id', $service->location_id)->findOrFail($request->integer('counter_id'))
            : null;

        return $this->run(function () use ($request, $service, $counter) {
            $t = $this->queues->callNext($service, $request->user(), $counter);

            return $t ? "Called {$t->token()} to Counter {$t->counterNumber()}."
                : 'Advanced to '.$service->fresh()->token($service->fresh()->current_number).' (walk-in token).';
        }, $request);
    }

    public function pause(Request $request, Service $service)
    {
        $this->authorizeService($request, $service);
        $service->forceFill(['is_paused' => ! $service->is_paused])->save();
        AuditLog::record($service->is_paused ? 'queue_paused' : 'queue_resumed', $service);

        return $this->done($request, $service->is_paused ? 'Queue paused.' : 'Queue resumed.');
    }

    public function start(Request $request, Ticket $ticket)
    {
        $this->authorize('manage', $ticket);

        return $this->run(fn () => 'Serving '.$this->queues->startService($ticket, $request->user())->token().'.', $request);
    }

    public function complete(Request $request, Ticket $ticket)
    {
        $this->authorize('manage', $ticket);

        return $this->run(fn () => $this->queues->complete($ticket, $request->user())->token().' completed.', $request);
    }

    public function absent(Request $request, Ticket $ticket)
    {
        $this->authorize('manage', $ticket);

        return $this->run(fn () => $this->queues->markAbsent($ticket, $request->user())->token().' marked absent.', $request);
    }

    public function cancel(Request $request, Ticket $ticket)
    {
        $this->authorize('manage', $ticket);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        return $this->run(fn () => $this->queues->cancel($ticket, $request->user(), $reason)->token().' cancelled.', $request);
    }

    public function move(Request $request, Ticket $ticket)
    {
        $this->authorize('manage', $ticket);
        $counter = Counter::findOrFail($request->validate(['counter_id' => ['required', 'integer']])['counter_id']);

        return $this->run(fn () => $this->queues->moveToCounter($ticket, $counter, $request->user())->token()." moved to Counter {$counter->number}.", $request);
    }

    /** Physical document check. Records what was seen; it does not certify authenticity. */
    public function verify(VerifyDocumentsRequest $request, Ticket $ticket)
    {
        $data = $request->validated();
        DocumentVerification::create([
            'ticket_id' => $ticket->id, 'staff_id' => $request->user()->id, 'result' => $data['result'],
            'missing' => $data['missing'] ?? null, 'reason' => $data['reason'] ?? null, 'verified_at' => now(),
        ]);
        $ticket->forceFill(['docs_verified' => $data['result'] === 'verified'])->save();
        AuditLog::record('documents_'.$data['result'], $ticket, ['missing' => $data['missing'] ?? []]);

        return $this->done($request, "Documents {$data['result']} for {$ticket->token()}.");
    }

    private function authorizeService(Request $request, Service $service): void
    {
        abort_unless($request->user()->canOperateLocation($service->location_id), 403, 'This location is not assigned to you.');
    }

    private function run(callable $action, Request $request)
    {
        try {
            return $this->done($request, $action());
        } catch (QueueException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['queue' => $e->getMessage()]);
        }
    }

    private function done(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('ok', $message);
    }
}
