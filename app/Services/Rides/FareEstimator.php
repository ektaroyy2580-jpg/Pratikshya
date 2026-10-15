<?php

namespace App\Services\Rides;

/**
 * Our own NPR fare MODEL — never a provider price. Arithmetic is done in integer paisa;
 * the displayed range is rounded outward to NPR 5 and never below the model minimum.
 */
class FareEstimator
{
    /** @return array{low: int, high: int, point: int} NPR */
    public function estimate(array $model, float $roadKm, int $minutes): array
    {
        $paisa = (int) round((float) $model['base'] * 100)
            + (int) round((float) $model['per_km'] * 100 * $roadKm)
            + (int) round((float) $model['per_min'] * 100 * $minutes);
        $paisa = max($paisa, (int) round((float) $model['minimum'] * 100));

        $lowF = (float) config('rides.range.low', 0.9);
        $highF = (float) config('rides.range.high', 1.2);
        $minNpr = (int) round((float) $model['minimum']);

        $low = intdiv((int) round($paisa * $lowF), 500) * 5;
        $high = intdiv((int) round($paisa * $highF) + 499, 500) * 5;
        $low = max($low, $minNpr);
        $high = max($high, $low);

        return ['low' => $low, 'high' => $high, 'point' => (int) round($paisa / 100)];
    }
}
