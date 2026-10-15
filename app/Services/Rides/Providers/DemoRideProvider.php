<?php

namespace App\Services\Rides\Providers;

use App\Models\RideRequest;
use Illuminate\Support\Str;

/**
 * DEMONSTRATION booking. No driver, vehicle or provider is ever contacted. The status advances
 * server-side from demo_started_at using config('rides.demo.timeline'); it never becomes "confirmed".
 */
class DemoRideProvider implements RideProvider
{
    public function key(): string
    {
        return 'demo';
    }

    public function supportsLiveBooking(): bool
    {
        return false;
    }

    public function quote(array $trip): array
    {
        return []; // The demo reuses the estimate the user already selected.
    }

    public function book(RideRequest $ride): RideRequest
    {
        $ride->forceFill([
            'is_demo' => true,
            'status' => 'demo_requested',
            'demo_started_at' => now(),
            'external_reference' => 'DEMO-'.strtoupper(Str::random(6)),
        ])->save();

        return $ride;
    }

    /** Stage for a given elapsed time (seconds) since the demo started. */
    public function stageAt(int $elapsed): string
    {
        $stage = 'demo_requested';
        foreach (config('rides.demo.timeline') as $name => $offset) {
            if ($elapsed >= $offset) {
                $stage = $name;
            }
        }

        return $stage;
    }

    public function status(RideRequest $ride): array
    {
        $timeline = config('rides.demo.timeline');
        $base = ['live' => false, 'demo' => true, 'badge' => config('rides.demo.badge'), 'vehicle' => null, 'progress' => 0, 'eta_seconds' => null, 'driver' => null];

        if (! $ride->is_demo || ! $ride->demo_started_at || ! in_array($ride->status, RideRequest::DEMO_ACTIVE, true)) {
            return ['status' => $ride->status, 'progress' => $ride->status === 'demo_completed' ? 100 : 0] + $base;
        }

        $elapsed = max(0, (int) $ride->demo_started_at->diffInSeconds(now()));
        $stage = $this->stageAt($elapsed);

        if ($stage !== $ride->status) {
            $ride->forceFill(['status' => $stage])->save();
        }

        // Simulated vehicle position — purely illustrative, drawn on a straight line.
        $vehicle = null;
        $progress = 0;
        $eta = null;
        $approach = [$ride->pickup_lat + 0.006, $ride->pickup_lng - 0.006];
        if ($stage === 'demo_driver_assigned' || $stage === 'demo_arriving') {
            $from = $timeline['demo_driver_assigned'];
            $to = $timeline['demo_on_trip'];
            $f = min(1, max(0, ($elapsed - $from) / max(1, $to - $from)));
            $vehicle = [$approach[0] + ($ride->pickup_lat - $approach[0]) * $f, $approach[1] + ($ride->pickup_lng - $approach[1]) * $f];
            $eta = max(0, $to - $elapsed);
        } elseif ($stage === 'demo_on_trip') {
            $from = $timeline['demo_on_trip'];
            $to = $timeline['demo_completed'];
            $f = min(1, max(0, ($elapsed - $from) / max(1, $to - $from)));
            $progress = (int) round($f * 100);
            $vehicle = [$ride->pickup_lat + ($ride->destination_lat - $ride->pickup_lat) * $f, $ride->pickup_lng + ($ride->destination_lng - $ride->pickup_lng) * $f];
            $eta = max(0, $to - $elapsed);
        } elseif ($stage === 'demo_completed') {
            $progress = 100;
            $vehicle = [$ride->destination_lat, $ride->destination_lng];
        }

        return array_merge($base, [
            'status' => $stage,
            'elapsed' => $elapsed,
            'eta_seconds' => $eta,
            'progress' => $progress,
            'vehicle' => $vehicle ? ['lat' => round($vehicle[0], 6), 'lng' => round($vehicle[1], 6)] : null,
            'driver' => $stage === 'demo_requested' ? null : [
                'name' => 'Demo driver (simulated)',
                'vehicle' => 'Simulated '.strtolower($ride->vehicleLabel()),
                'plate' => 'DEMO 00',
            ],
        ]);
    }
}
