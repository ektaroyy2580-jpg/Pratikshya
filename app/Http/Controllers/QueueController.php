<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinQueueRequest;
use App\Models\Location;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\Queue\QueueException;
use App\Services\Queue\QueueManager;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(private QueueManager $queues)
    {
    }

    public function home()
    {
        $services = Service::with('location')->where('is_active', true)->get();
        $stats = [
            'locations' => Location::count(),
            'services' => $services->count(),
            'waiting' => $services->sum(fn ($s) => $s->waitingCount()),
        ];
        // Live preview card on the landing page uses a real queue.
        $featured = $services->firstWhere('prefix', 'D') ?? $services->first();
        $featuredEstimate = $featured?->estimate();
        $busiest = $services->sortByDesc(fn ($s) => $s->waitingCount())->take(4)->values();

        return view('pages.home', compact('stats', 'featured', 'featuredEstimate', 'busiest'));
    }

    public function find(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $locations = Location::with(['services' => fn ($s) => $s->where('is_active', true)])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('area', 'like', "%{$q}%")->orWhere('type', 'like', "%{$q}%")
                    ->orWhereHas('services', fn ($s) => $s->where('name', 'like', "%{$q}%"));
            }))
            ->get();

        return view('pages.find', compact('locations', 'q'));
    }

    public function service(Request $request, Service $service)
    {
        $service->load('location', 'counters');
        $estimate = $service->estimate();
        $active = $this->queues->activeTicketFor($service, $request->user());

        return view('pages.service', compact('service', 'estimate', 'active'));
    }

    public function join(JoinQueueRequest $request, Service $service)
    {
        $docs = $request->validated('docs', []);
        $missing = collect($service->documents ?? [])->reject(fn ($d) => ($docs[$d] ?? null) === 'available');
        if ($missing->isNotEmpty() && ! $request->boolean('confirm_missing') && ! $service->requires_all_documents) {
            return back()->withInput()->with('warn_missing', $missing->values()->all());
        }

        try {
            $ticket = $this->queues->join($service, $request->user(), $docs, $request->validated('idempotency_key'));
        } catch (QueueException $e) {
            return back()->withInput()->withErrors(['queue' => $e->getMessage()]);
        }
        session()->push('tickets', $ticket->id);

        return redirect()->route('ticket', $ticket)
            ->with('ok', $ticket->wasRecentlyCreated ? "You're in! Token {$ticket->token()}." : "You already hold token {$ticket->token()} for this service.");
    }

    public function ticket(Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $ticket->load('service.location.places', 'counter', 'verifications');

        return view('pages.ticket', compact('ticket'));
    }

    /** Polling endpoint — read only, never creates entries. */
    public function state(Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $ticket->load('service', 'counter');

        return response()->json(['message' => 'Queue status retrieved successfully', 'data' => $ticket->stateArray()]);
    }

    public function leave(Request $request, Ticket $ticket)
    {
        $this->authorize('leave', $ticket);
        try {
            $this->queues->leave($ticket, $request->user());
        } catch (QueueException $e) {
            return back()->withErrors(['queue' => $e->getMessage()]);
        }

        return redirect()->route('profile')->with('ok', "You left the queue. Token {$ticket->token()} has been released.");
    }

    /**
     * Interim game points (Phase 2 replaces this with server-validated matches).
     * Owner only, throttled, capped per call and per ticket, never affects queue order.
     */
    public function points(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $won = max(0, min(100, (int) $request->input('points')));
        $ticket->forceFill(['game_points' => min(500, $ticket->game_points + $won)])->save();

        return response()->json(['message' => 'Points recorded', 'data' => $ticket->fresh(['service', 'counter'])->stateArray()]);
    }
}
