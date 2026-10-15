<?php

namespace App\Http\Controllers\Rides;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\CarpoolConsentRequest;
use App\Http\Requests\Rides\RideQuoteRequest;
use App\Http\Requests\Rides\RideStoreRequest;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Models\RideRequest;
use App\Services\Rides\Providers\RideProviderException;
use App\Services\Rides\RidePlanner;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(private RidePlanner $planner) {}

    public function plan(Request $request)
    {
        $user = $request->user();
        $destination = $this->planner->resolveDestination($user, $request->only('service', 'location', 'place'));
        $ticket = $this->planner->activeTicket($user);

        $locations = Location::orderBy('name')->get(['id', 'name', 'area', 'lat', 'lng'])
            ->filter(fn ($l) => $l->lat && $l->lng)
            ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'sub' => $l->area, 'lat' => (float) $l->lat, 'lng' => (float) $l->lng, 'kind' => 'location'])->values();
        $places = NearbyPlace::orderBy('name')->get(['id', 'location_id', 'name', 'category', 'lat', 'lng'])
            ->filter(fn ($p) => $p->lat && $p->lng)
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sub' => $p->category, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng, 'kind' => 'place'])->values();

        $recent = RideRequest::ownedBy($user)->latest('id')->limit(4)->get();
        $mode = in_array($request->query('mode'), array_keys(config('rides.modes')), true) ? $request->query('mode') : null;

        return view('rides.plan', [
            'destination' => $destination,
            'ticket' => $ticket,
            'ticketState' => $ticket?->stateArray(),
            'locations' => $locations,
            'places' => $places,
            'landmarks' => config('rides.landmarks'),
            'recent' => $recent,
            'initialMode' => $mode,
            'routingConfigured' => (bool) config('rides.routing_url'),
        ]);
    }

    public function quote(RideQuoteRequest $request)
    {
        $d = $request->validated();
        $plan = $this->planner->plan((float) $d['pickup_lat'], (float) $d['pickup_lng'], (float) $d['destination_lat'], (float) $d['destination_lng']);

        // Queue-aware timing: only for the user's own active ticket at this destination office.
        $leaveBy = null;
        $ticket = $this->planner->activeTicket($request->user());
        if ($ticket && ! empty($d['location_id']) && (int) $ticket->service->location_id === (int) $d['location_id']) {
            foreach ($plan['modes'] as $m) {
                $leaveBy[$m['key']] = $this->planner->leaveBy($ticket, $m['minutes']);
            }
        }
        $plan['leave_by'] = $leaveBy;

        return response()->json(['message' => 'Estimated fares and approximate travel times — not live provider prices.', 'data' => $plan]);
    }

    public function store(RideStoreRequest $request)
    {
        try {
            $ride = $this->planner->createQuote($request->user(), $request->validated());
        } catch (RideProviderException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['vehicle_type' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => 'Trip saved. Booking happens in the provider app — or try the demo.',
            'data' => [
                'id' => $ride->id,
                'provider' => $ride->providerName(),
                'vehicle' => $ride->vehicleLabel(),
                'fare_low' => (float) $ride->fare_low,
                'fare_high' => (float) $ride->fare_high,
                'fare_source' => 'estimate',
                'est_minutes' => $ride->est_minutes,
                'distance_km' => (float) $ride->distance_km,
                'status' => $ride->status,
                'consent_share' => $ride->consent_share,
                'handoff_url' => route('rides.handoff', $ride),
                'demo_url' => route('rides.demo', $ride),
                'show_url' => route('rides.show', $ride),
            ],
        ], 201);
    }

    public function show(Request $request, RideRequest $ride)
    {
        $this->authorize('view', $ride);
        $state = $this->planner->status($ride);
        $ride->refresh()->load('ticket.service');
        $ticket = $ride->ticket;

        return view('rides.show', [
            'ride' => $ride,
            'state' => $state,
            'carpool' => $this->planner->carpoolMatches($ride),
            'leaveBy' => $ticket && $ticket->user_id === $request->user()->id && $ticket->isActive()
                ? $this->planner->leaveBy($ticket, $ride->est_minutes) : null,
            'handoffUrl' => $this->planner->registry()->has($ride->provider) ? $this->planner->registry()->get($ride->provider)->handoffUrl() : null,
        ]);
    }

    public function state(RideRequest $ride)
    {
        $this->authorize('view', $ride);
        $data = $this->planner->status($ride);
        $data['carpool'] = $this->planner->carpoolMatches($ride->refresh());

        return response()->json([
            'message' => $ride->is_demo ? config('rides.demo.badge') : 'Trip status — we do not receive updates from the provider.',
            'data' => $data,
        ]);
    }

    /** Opens the provider's OFFICIAL site/app link (form target=_blank). Records intent only — never a booking. */
    public function handoff(Request $request, RideRequest $ride)
    {
        $this->authorize('update', $ride);
        try {
            $url = $this->planner->handOff($ride);
        } catch (RideProviderException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : redirect()->route('rides.show', $ride)->withErrors(['queue' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Fare and booking are confirmed in the {$ride->providerName()} app.",
                'data' => ['url' => $url, 'status' => $ride->status, 'show_url' => route('rides.show', $ride)],
            ]);
        }

        return redirect()->away($url);
    }

    public function demo(Request $request, RideRequest $ride)
    {
        $this->authorize('update', $ride);
        try {
            $this->planner->startDemo($ride);
        } catch (RideProviderException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : redirect()->route('rides.show', $ride)->withErrors(['queue' => $e->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['message' => config('rides.demo.badge'), 'data' => ['status' => $ride->status, 'is_demo' => true, 'show_url' => route('rides.show', $ride)]])
            : redirect()->route('rides.show', $ride)->with('ok', 'Demo ride started — a simulation, no driver is coming.');
    }

    public function cancel(Request $request, RideRequest $ride)
    {
        $this->authorize('update', $ride);
        try {
            $this->planner->cancel($ride);
        } catch (RideProviderException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : redirect()->route('rides.show', $ride)->withErrors(['queue' => $e->getMessage()]);
        }

        $msg = $ride->is_demo ? 'Demo ride cancelled.' : 'Trip removed from your plans. If you booked in the provider app, cancel it there too.';

        return $request->expectsJson()
            ? response()->json(['message' => $msg, 'data' => $this->planner->status($ride)])
            : redirect()->route('rides.show', $ride)->with('ok', $msg);
    }

    public function carpool(CarpoolConsentRequest $request, RideRequest $ride)
    {
        $this->authorize('update', $ride);
        $on = $request->boolean('consent_share');
        $this->planner->setConsent($ride, $on);
        $matches = $this->planner->carpoolMatches($ride);
        $msg = $on ? 'Carpool matching is on for this trip.' : 'Carpool matching is off. Nothing about this trip is shared.';

        return $request->expectsJson()
            ? response()->json(['message' => $msg, 'data' => ['consent_share' => $on, 'carpool' => $matches]])
            : redirect()->route('rides.show', $ride)->with('ok', $msg);
    }
}
