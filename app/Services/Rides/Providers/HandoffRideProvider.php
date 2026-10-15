<?php

namespace App\Services\Rides\Providers;

use App\Models\RideRequest;
use App\Services\Rides\FareEstimator;

/**
 * Config-backed adapter for inDrive / Yango / Pathao. None of them offer a public booking API
 * we are authorised to use, so: quotes are OUR estimates, book() is unsupported, and the user
 * is handed off to the provider's official website/app where the real fare and booking happen.
 */
class HandoffRideProvider implements RideProvider
{
    public function __construct(private string $key, private FareEstimator $fares) {}

    public function key(): string
    {
        return $this->key;
    }

    public function config(): array
    {
        return config("rides.providers.{$this->key}", []);
    }

    public function name(): string
    {
        return $this->config()['name'] ?? ucfirst($this->key);
    }

    public function supportsLiveBooking(): bool
    {
        // No authorised partner integration exists yet. Even if api keys are configured,
        // nothing here talks to the provider, so we never claim live booking.
        return false;
    }

    public function hasVehicle(string $type): bool
    {
        return isset($this->config()['vehicles'][$type]);
    }

    public function quote(array $trip): array
    {
        $cfg = $this->config();
        $out = [];
        foreach ($cfg['vehicles'] ?? [] as $type => $v) {
            $minutes = (int) ($trip['minutes'][$v['mode']] ?? 0);
            $fare = $this->fares->estimate($v, (float) $trip['road_km'], $minutes);
            $out[] = [
                'provider' => $this->key,
                'provider_name' => $this->name(),
                'initial' => $cfg['initial'] ?? strtoupper($this->key[0]),
                'color' => $cfg['color'] ?? '#2f5bea',
                'tagline' => $cfg['tagline'] ?? null,
                'vehicle_type' => $type,
                'vehicle_label' => $v['label'],
                'icon' => $v['icon'] ?? 'bi-car-front',
                'mode' => $v['mode'],
                'seats' => $v['seats'] ?? null,
                'minutes' => $minutes,
                'pickup_eta' => $v['pickup_eta'] ?? null,
                'fare_low' => $fare['low'],
                'fare_high' => $fare['high'],
                'source' => 'estimate',
                'surge_note' => $v['surge_note'] ?? null,
                'supports_live_booking' => false,
            ];
        }

        return $out;
    }

    public function book(RideRequest $ride): RideRequest
    {
        throw new RideProviderException($this->name().' does not offer a booking API this app can use. Open the official app to book.');
    }

    public function status(RideRequest $ride): array
    {
        $name = $this->name();

        return [
            'status' => $ride->status,
            'live' => false,
            'demo' => false,
            'headline' => $ride->status === 'handed_off' ? "Continue in {$name}" : 'Trip planned',
            'detail' => "Fare and booking are confirmed in the {$name} app. We cannot see whether a ride was booked.",
        ];
    }

    /** Official homepage only. A deep link is used only if configured from official partner docs. */
    public function handoffUrl(): ?string
    {
        $cfg = $this->config();

        return ($cfg['deep_link'] ?? null) ?: ($cfg['website'] ?? null);
    }
}
