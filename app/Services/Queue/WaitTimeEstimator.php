<?php

namespace App\Services\Queue;

use App\Models\Service;

/**
 * Explainable waiting-time estimate.
 *
 * Base: people ahead × average service minutes ÷ open counters.
 * The average blends the configured minutes with recently observed service
 * durations (weighted by how many samples exist), and the result is shown as
 * a range — it is an estimate, never a guarantee.
 */
class WaitTimeEstimator
{
    public const SAMPLE_SIZE = 20;

    /** @return array{0: float, 1: int} [average minutes, number of observed samples] */
    public function averageMinutes(Service $service): array
    {
        $durations = $service->tickets()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->take(self::SAMPLE_SIZE)
            ->get(['called_at', 'serving_at', 'completed_at'])
            ->map(function ($t) {
                $start = $t->serving_at ?? $t->called_at;

                return $start ? max(0.5, $start->diffInSeconds($t->completed_at) / 60) : null;
            })
            ->filter()
            ->values();

        $configured = max(1, (int) $service->avg_minutes);
        $n = $durations->count();
        if ($n === 0) {
            return [(float) $configured, 0];
        }
        // Trust observations more as samples grow (max 80% weight at 10+ samples).
        $weight = min(0.8, $n / 12.5);

        return [round($configured * (1 - $weight) + $durations->median() * $weight, 1), $n];
    }

    public function forPeopleAhead(Service $service, int $ahead): array
    {
        [$avg, $samples] = $this->averageMinutes($service);
        $counters = max(1, $service->counters()->where('is_open', true)->count());
        $minutes = (int) round($ahead * $avg / $counters);

        // Wider range when we know less.
        $spread = $samples >= 10 ? 0.2 : ($samples >= 3 ? 0.3 : 0.4);
        $low = (int) max(0, floor($minutes * (1 - $spread)));
        $high = (int) ceil($minutes * (1 + $spread)) + ($ahead > 0 ? 1 : 0);
        $confidence = $samples >= 10 ? 'high' : ($samples >= 3 ? 'medium' : 'low');

        $basis = $samples
            ? "based on {$samples} recent services (≈{$avg} min each)"
            : "based on the office's typical {$avg} min per person (no recent history yet)";
        $explanation = "{$ahead} ahead × ≈{$avg} min".($counters > 1 ? " ÷ {$counters} open counters" : '').", {$basis}.";
        if ($service->is_paused) {
            $explanation .= ' The queue is currently paused, so the wait may be longer.';
        }

        return compact('minutes', 'low', 'high', 'confidence', 'explanation') + ['avg' => $avg, 'samples' => $samples, 'counters' => $counters];
    }
}
