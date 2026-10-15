<?php

namespace App\Services\Rides;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Approximate travel times per mode. Default: straight-line (haversine) × road factor and
 * configured average speeds. If RIDES_ROUTING_URL points to an OSRM-compatible server the
 * road distance and geometry come from it; any failure silently falls back to the estimate.
 */
class TravelEstimator
{
    /** @return array{straight_km: float, road_km: float, source: string, geometry: ?array} */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $straight = Geo::haversine($fromLat, $fromLng, $toLat, $toLng);
        $estimate = [
            'straight_km' => round($straight, 2),
            'road_km' => round($straight * (float) config('rides.road_factor', 1.3), 2),
            'source' => 'estimate',
            'geometry' => null,
        ];

        $url = config('rides.routing_url');
        if (! $url) {
            return $estimate;
        }

        try {
            $res = Http::timeout((int) config('rides.routing_timeout', 3))
                ->get(rtrim($url, '/')."/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}", ['overview' => 'simplified', 'geometries' => 'geojson']);
            $route = $res->successful() ? ($res->json('routes.0') ?? null) : null;
            $meters = is_array($route) ? ($route['distance'] ?? null) : null;
            if (! is_numeric($meters) || $meters <= 0) {
                return $estimate;
            }
            $coords = collect($route['geometry']['coordinates'] ?? [])
                ->filter(fn ($c) => is_array($c) && count($c) >= 2 && is_numeric($c[0]) && is_numeric($c[1]))
                ->map(fn ($c) => [(float) $c[1], (float) $c[0]])->take(2000)->values()->all();

            return ['straight_km' => $estimate['straight_km'], 'road_km' => round($meters / 1000, 2), 'source' => 'routing', 'geometry' => $coords ?: null];
        } catch (Throwable) {
            return $estimate;
        }
    }

    public function minutesFor(string $mode, float $roadKm): int
    {
        $m = config("rides.modes.$mode");
        if (! $m) {
            throw new \InvalidArgumentException("Unknown travel mode [$mode].");
        }

        // round() first so float noise (e.g. 12.0000001) doesn't add a phantom minute.
        return max(1, (int) ceil(round($roadKm / (float) $m['speed_kmh'] * 60, 4))) + (int) ($m['wait_minutes'] ?? 0);
    }

    /** All modes with approximate minutes. */
    public function modes(float $roadKm): array
    {
        $out = [];
        foreach (config('rides.modes') as $key => $m) {
            $out[$key] = [
                'key' => $key,
                'label' => $m['label'],
                'icon' => $m['icon'],
                'minutes' => $this->minutesFor($key, $roadKm),
                'speed_kmh' => $m['speed_kmh'],
                'wait_minutes' => (int) ($m['wait_minutes'] ?? 0),
                'approximate' => true,
            ];
        }

        return $out;
    }

    /** Suggest a mode for this distance (walking for short hops, rides/bus when far). */
    public function recommend(float $straightKm, array $modes): array
    {
        $far = (float) config('rides.far_threshold_km', 2.0);
        if ($straightKm <= 1.2) {
            return ['mode' => 'walking', 'reason' => 'It is a short walk — about '.$modes['walking']['minutes'].' min.'];
        }
        if ($straightKm <= $far) {
            return ['mode' => 'bike', 'reason' => 'Close enough for a quick bike ride (~'.$modes['bike']['minutes'].' min) or a '.$modes['walking']['minutes'].' min walk.'];
        }

        return ['mode' => 'car', 'reason' => 'You are '.number_format($straightKm, 1).' km away — a ride (~'.$modes['car']['minutes'].' min) or bike ride-hail is fastest; public transport is cheapest (~'.$modes['transit']['minutes'].' min).'];
    }
}
