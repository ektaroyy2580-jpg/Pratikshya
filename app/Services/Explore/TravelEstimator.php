<?php

namespace App\Services\Explore;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Walking estimates.
 *
 * Default (routing_provider = none): haversine straight-line distance × road factor 1.3,
 * walked at 4.5 km/h. Always labelled "approximate (straight-line based)" and never
 * accompanied by a drawn street route — the map shows a dashed "direct line, not a route".
 *
 * routing_provider = osrm: asks a configured (self-hosted) OSRM server for a foot route;
 * falls back to the straight-line estimate if it is unreachable.
 */
class TravelEstimator
{
    public const EARTH_RADIUS_M = 6371000;

    public const STRAIGHT_LABEL = 'approximate (straight-line based)';

    public const LINE_LABEL = 'direct line, not a route';

    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Minutes to walk a street distance (metres) at the configured speed; at least 1 minute. */
    public function walkMinutes(float $meters): int
    {
        $kmh = max(0.5, (float) config('explore.walk_kmh', 4.5));

        return max(1, (int) ceil(($meters / 1000) / $kmh * 60));
    }

    /**
     * Straight-line based estimate (no network calls).
     *
     * @return array{straight_m:int, distance_m:int, walk_minutes:int, method:string, label:string, geometry:null}
     */
    public function straight(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $straight = $this->distanceMeters($fromLat, $fromLng, $toLat, $toLng);
        $street = $straight * (float) config('explore.road_factor', 1.3);

        return [
            'straight_m' => (int) round($straight),
            'distance_m' => (int) round($street),
            'walk_minutes' => $this->walkMinutes($street),
            'method' => 'straight_line',
            'label' => self::STRAIGHT_LABEL,
            'geometry' => null,
        ];
    }

    /**
     * Best available estimate: OSRM route when configured and reachable, otherwise straight-line.
     * Geometry ([[lat,lng],...]) is only returned for a real routed path.
     */
    public function estimate(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        if ($this->provider() === 'osrm' && ($routed = $this->osrm($fromLat, $fromLng, $toLat, $toLng))) {
            return $routed;
        }

        return $this->straight($fromLat, $fromLng, $toLat, $toLng);
    }

    public function provider(): string
    {
        $p = config('explore.routing_provider', 'none');

        return $p === 'osrm' && config('explore.osrm_url') ? 'osrm' : 'none';
    }

    private function osrm(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $base = rtrim((string) config('explore.osrm_url'), '/');
        $url = sprintf('%s/route/v1/foot/%F,%F;%F,%F', $base, $fromLng, $fromLat, $toLng, $toLat);
        try {
            $res = Http::timeout((float) config('explore.osrm_timeout', 3))
                ->get($url, ['overview' => 'full', 'geometries' => 'geojson']);
            $route = $res->successful() ? ($res->json('routes.0') ?? null) : null;
            if (! is_array($route) || ! is_numeric($route['distance'] ?? null)) {
                return null;
            }
            $coords = collect($route['geometry']['coordinates'] ?? [])
                ->filter(fn ($c) => is_array($c) && count($c) >= 2 && is_numeric($c[0]) && is_numeric($c[1]))
                ->map(fn ($c) => [round((float) $c[1], 6), round((float) $c[0], 6)])
                ->values()->all();
            $meters = (float) $route['distance'];

            return [
                'straight_m' => (int) round($this->distanceMeters($fromLat, $fromLng, $toLat, $toLng)),
                'distance_m' => (int) round($meters),
                // Use our walking speed, not the server's profile speed, so estimates stay consistent.
                'walk_minutes' => $this->walkMinutes($meters),
                'method' => 'osrm',
                'label' => 'approximate (walking route from routing service)',
                'geometry' => $coords ?: null,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
