<?php

namespace App\Services\Rides\Providers;

use App\Services\Rides\FareEstimator;

class RideProviderRegistry
{
    public function __construct(private FareEstimator $fares) {}

    /** @return array<string, HandoffRideProvider> enabled providers keyed by config key */
    public function all(): array
    {
        $out = [];
        foreach (config('rides.providers', []) as $key => $cfg) {
            if (filter_var($cfg['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $out[$key] = new HandoffRideProvider($key, $this->fares);
            }
        }

        return $out;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): HandoffRideProvider
    {
        $all = $this->all();
        if (! isset($all[$key])) {
            throw new RideProviderException("Unknown or disabled ride provider [$key].");
        }

        return $all[$key];
    }

    public function demo(): DemoRideProvider
    {
        return new DemoRideProvider;
    }
}
