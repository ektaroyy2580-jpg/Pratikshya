<?php

namespace App\Services\Rides;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Models\RideRequest;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Rides\Providers\RideProviderException;
use App\Services\Rides\Providers\RideProviderRegistry;

/**
 * Business logic for Smart Explore & Ride Booking. Nothing here changes queue order:
 * tickets are only READ (for "leave by" timing and to link a trip to the user's own ticket).
 */
class RidePlanner
{
    public function __construct(
        private TravelEstimator $travel,
        private RideProviderRegistry $providers,
    ) {}

    public function registry(): RideProviderRegistry
    {
        return $this->providers;
    }

    public function travel(): TravelEstimator
    {
        return $this->travel;
    }

    /** Distance, per-mode times, recommendation and estimated fare cards for a trip. */
    public function plan(float $pLat, float $pLng, float $dLat, float $dLng): array
    {
        $route = $this->travel->route($pLat, $pLng, $dLat, $dLng);
        $modes = $this->travel->modes($route['road_km']);
        $minutes = array_map(fn ($m) => $m['minutes'], $modes);

        $quotes = [];
        foreach ($this->providers->all() as $provider) {
            array_push($quotes, ...$provider->quote(['road_km' => $route['road_km'], 'minutes' => $minutes]));
        }

        return [
            'straight_km' => $route['straight_km'],
            'road_km' => $route['road_km'],
            'routing' => $route['source'],
            'geometry' => $route['geometry'],
            'modes' => array_values($modes),
            'recommend' => $this->travel->recommend($route['straight_km'], $modes),
            'quotes' => $quotes,
        ];
    }

    public function activeTicket(User $user): ?Ticket
    {
        return $user->tickets()->whereIn('status', Ticket::ACTIVE)->with('service.location')->latest('id')->first();
    }

    /**
     * Resolve the destination from ?service / ?location / ?place, else the user's active ticket.
     *
     * @return array{lat: float, lng: float, label: string, location_id: ?int, nearby_place_id: ?int, service_id: ?int, kind: string}|null
     */
    public function resolveDestination(?User $user, array $q): ?array
    {
        if (! empty($q['place']) && ($p = NearbyPlace::find((int) $q['place'])) && $p->lat && $p->lng) {
            return ['lat' => (float) $p->lat, 'lng' => (float) $p->lng, 'label' => $p->name, 'location_id' => null, 'nearby_place_id' => $p->id, 'service_id' => null, 'kind' => 'place'];
        }
        if (! empty($q['service']) && ($s = Service::with('location')->where('is_active', true)->find((int) $q['service'])) && $s->location) {
            return $this->fromLocation($s->location, $s->id, $s->name);
        }
        if (! empty($q['location']) && ($l = Location::find((int) $q['location']))) {
            return $this->fromLocation($l);
        }
        if ($user && ($t = $this->activeTicket($user)) && $t->service?->location) {
            return $this->fromLocation($t->service->location, $t->service_id, $t->service->name) + ['from_ticket' => true];
        }

        return null;
    }

    private function fromLocation(Location $l, ?int $serviceId = null, ?string $serviceName = null): array
    {
        return [
            'lat' => (float) $l->lat, 'lng' => (float) $l->lng,
            'label' => $l->name, 'sub' => $serviceName ? $serviceName.' · '.$l->area : $l->area,
            'location_id' => $l->id, 'nearby_place_id' => null, 'service_id' => $serviceId, 'kind' => 'location',
        ];
    }

    /** Record a planned trip (status "quoted"). Fare & time are recomputed server-side — client values are ignored. */
    public function createQuote(User $user, array $data): RideRequest
    {
        $provider = $this->providers->get($data['provider']);
        if (! $provider->hasVehicle($data['vehicle_type'])) {
            throw new RideProviderException('That vehicle type is not offered by '.$provider->name().'.');
        }

        $plan = $this->plan((float) $data['pickup_lat'], (float) $data['pickup_lng'], (float) $data['destination_lat'], (float) $data['destination_lng']);
        $quote = collect($plan['quotes'])->first(fn ($q) => $q['provider'] === $data['provider'] && $q['vehicle_type'] === $data['vehicle_type']);

        // Link to the user's OWN active ticket only when the destination is that ticket's office.
        $locationId = $data['location_id'] ?? null;
        $ticketId = null;
        if ($locationId) {
            $ticketId = $user->tickets()->whereIn('status', Ticket::ACTIVE)
                ->whereHas('service', fn ($s) => $s->where('location_id', $locationId))
                ->latest('id')->value('id');
        }

        $ride = new RideRequest([
            'pickup_label' => $data['pickup_label'] ?? 'Pinned pickup',
            'destination_label' => $data['destination_label'] ?? 'Destination',
        ]);
        $ride->forceFill([
            'user_id' => $user->id,
            'ticket_id' => $ticketId,
            'provider' => $provider->key(),
            'vehicle_type' => $data['vehicle_type'],
            'mode' => $quote['mode'],
            'pickup_lat' => round((float) $data['pickup_lat'], 7),
            'pickup_lng' => round((float) $data['pickup_lng'], 7),
            'destination_lat' => round((float) $data['destination_lat'], 7),
            'destination_lng' => round((float) $data['destination_lng'], 7),
            'location_id' => $locationId,
            'nearby_place_id' => $data['nearby_place_id'] ?? null,
            'distance_km' => $plan['road_km'],
            'est_minutes' => $quote['minutes'],
            'fare_low' => $quote['fare_low'],
            'fare_high' => $quote['fare_high'],
            'fare_source' => 'estimate',
            'status' => 'quoted',
            'is_demo' => false,
            'consent_share' => (bool) ($data['consent_share'] ?? false),
        ])->save();

        AuditLog::record('ride.quoted', $ride, ['provider' => $ride->provider, 'vehicle' => $ride->vehicle_type]);

        return $ride;
    }

    /** Hand the user to the provider's official site/app. Records intent only — never a booking. */
    public function handOff(RideRequest $ride): string
    {
        $provider = $this->providers->get($ride->provider);
        $url = $provider->handoffUrl();
        if (! $url) {
            throw new RideProviderException('No official link is configured for '.$provider->name().'.');
        }
        if (! in_array($ride->status, ['quoted', 'handed_off'], true)) {
            throw new RideProviderException('This trip can no longer be handed off.');
        }
        $ride->forceFill(['status' => 'handed_off', 'handed_off_at' => now()])->save();
        AuditLog::record('ride.handed_off', $ride, ['provider' => $ride->provider]);

        return $url;
    }

    public function startDemo(RideRequest $ride): RideRequest
    {
        if (! in_array($ride->status, ['quoted', 'handed_off'], true)) {
            throw new RideProviderException('A demo has already been run for this trip. Plan a new trip to try again.');
        }
        $this->providers->demo()->book($ride);
        AuditLog::record('ride.demo_started', $ride, ['demo' => true]);

        return $ride;
    }

    public function cancel(RideRequest $ride): RideRequest
    {
        // Sync the simulated status first so a finished demo can't be "cancelled".
        $this->status($ride);
        if (! $ride->isCancellable()) {
            throw new RideProviderException('This trip has already finished.');
        }
        $ride->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        AuditLog::record('ride.cancelled', $ride, ['demo' => $ride->is_demo]);

        return $ride;
    }

    /** Status payload for polling (JSON data). */
    public function status(RideRequest $ride): array
    {
        $payload = $ride->is_demo
            ? $this->providers->demo()->status($ride)
            : ($this->providers->has($ride->provider)
                ? $this->providers->get($ride->provider)->status($ride)
                : ['status' => $ride->status, 'live' => false, 'demo' => false]);

        return $payload + [
            'id' => $ride->id,
            'reference' => $ride->external_reference,
            'provider' => $ride->providerName(),
            'vehicle_label' => $ride->vehicleLabel(),
            'fare_low' => (float) $ride->fare_low,
            'fare_high' => (float) $ride->fare_high,
            'fare_source' => 'estimate',
            'cancellable' => $ride->isCancellable(),
            'consent_share' => $ride->consent_share,
        ];
    }

    /**
     * When to leave for a queue ticket. Uses the LOW end of the wait estimate so the user is early, not late.
     */
    public function leaveBy(Ticket $ticket, int $travelMinutes): array
    {
        $state = $ticket->stateArray();
        $turnLow = $ticket->status === 'waiting' ? (int) $state['wait_low'] : 0;
        $turnHigh = $ticket->status === 'waiting' ? (int) $state['wait_high'] : 0;
        $buffer = (int) config('rides.leave_buffer_minutes', 10);
        $leaveIn = $turnLow - $travelMinutes - $buffer;

        return [
            'ticket_status' => $ticket->status,
            'turn_low' => $turnLow,
            'turn_high' => $turnHigh,
            'travel_minutes' => $travelMinutes,
            'buffer_minutes' => $buffer,
            'leave_in' => max(0, $leaveIn),
            'leave_now' => $leaveIn <= 0,
            'late_by' => $leaveIn < 0 ? -$leaveIn : 0,
            'leave_at' => now()->addMinutes(max(0, $leaveIn))->setTimezone(config('rides.display_timezone', 'Asia/Kathmandu'))->format('g:i A'),
            'token' => $ticket->token(),
        ];
    }

    public function setConsent(RideRequest $ride, bool $on): RideRequest
    {
        $ride->forceFill(['consent_share' => $on])->save();
        AuditLog::record($on ? 'ride.carpool_opt_in' : 'ride.carpool_opt_out', $ride);

        return $ride;
    }

    /**
     * Anonymous counts of OTHER people who also opted in and are heading to the same place.
     * Returns null unless this ride has explicit consent. Never returns identities or exact points.
     *
     * @return array{same_destination: int, nearby_pickup: int}|null
     */
    public function carpoolMatches(RideRequest $ride): ?array
    {
        if (! $ride->consent_share || $ride->status === 'cancelled') {
            return null;
        }
        $cfg = config('rides.carpool');
        $myLat = Geo::coarse($ride->pickup_lat);
        $myLng = Geo::coarse($ride->pickup_lng);

        $others = RideRequest::query()
            ->where('consent_share', true)
            ->where('user_id', '!=', $ride->user_id)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subHours((int) $cfg['window_hours']))
            ->latest('id')->limit(500)
            ->get(['user_id', 'pickup_lat', 'pickup_lng', 'destination_lat', 'destination_lng', 'location_id']);

        $same = $others->filter(fn ($o) => ($ride->location_id && $o->location_id === $ride->location_id)
            || Geo::haversine($o->destination_lat, $o->destination_lng, $ride->destination_lat, $ride->destination_lng) <= (float) $cfg['destination_radius_km']);
        $near = $same->filter(fn ($o) => Geo::haversine(Geo::coarse($o->pickup_lat), Geo::coarse($o->pickup_lng), $myLat, $myLng) <= (float) $cfg['pickup_radius_km']);

        return [
            'same_destination' => $same->unique('user_id')->count(),
            'nearby_pickup' => $near->unique('user_id')->count(),
        ];
    }
}
