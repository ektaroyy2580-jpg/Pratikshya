<?php

namespace App\Services\Preorder;

use App\Models\Restaurant;
use App\Models\Ticket;
use App\Models\User;

/**
 * "Can I get this food and be back before my turn?"
 *
 *   time away = max(walk there, kitchen prep) + collect + walk back
 *
 * compared with the CONSERVATIVE end of the user's queue estimate (wait_low,
 * i.e. the earliest their turn is likely to come). Read-only: this class never
 * modifies, pauses or cancels a queue ticket.
 *
 * States: ok (comfortable margin) · tight (fits but within the safety margin)
 *         · conflict (likely not back in time — explicit confirmation required)
 *         · no_ticket (not in a queue; just shows the ready time).
 */
class TimingCheck
{
    /** The user's most time-critical active ticket (smallest conservative wait), or null. */
    public function contextTicket(User $user): ?Ticket
    {
        return $user->tickets()
            ->whereIn('status', Ticket::ACTIVE)
            ->with('service.location', 'counter')
            ->get()
            ->sortBy(fn (Ticket $t) => $this->conservativeWait($t))
            ->first();
    }

    /** Earliest likely turn in minutes. Called/serving means "now". */
    public function conservativeWait(Ticket $ticket): int
    {
        if ($ticket->status !== 'waiting') {
            return 0;
        }

        return (int) ($ticket->stateArray()['wait_low'] ?? 0);
    }

    /** Kitchen time: slowest item, scaled by order size (+15% per extra unit, max ×2), plus the café's buffer. */
    public function prepMinutes(Restaurant $restaurant, array $lines): int
    {
        $max = 0;
        $qty = 0;
        foreach ($lines as $l) {
            $max = max($max, (int) $l['prep_minutes']);
            $qty += max(0, (int) $l['qty']);
        }
        if ($qty === 0) {
            return 0;
        }
        $factorPct = min(200, 100 + 15 * ($qty - 1));

        return (int) ceil($max * $factorPct / 100) + (int) $restaurant->prep_buffer_minutes;
    }

    /** One-way walking minutes from where the user is queueing to the café. */
    public function walkMinutes(Restaurant $restaurant, ?Ticket $ticket): int
    {
        $from = $ticket?->service?->location ?? $restaurant->location;
        $place = $restaurant->nearbyPlace;

        if ($place && $from && (int) $place->location_id === (int) $from->id && $place->walk_minutes) {
            return max(1, (int) $place->walk_minutes);
        }
        if ($from && $restaurant->lat !== null && $restaurant->lng !== null && $from->lat !== null) {
            $metres = $this->haversine((float) $from->lat, (float) $from->lng, (float) $restaurant->lat, (float) $restaurant->lng);

            return max(1, (int) ceil($metres / max(1, (int) config('preorder.walk_metres_per_minute', 75))));
        }

        return max(1, (int) ($restaurant->walk_minutes ?? $place?->walk_minutes ?? 5));
    }

    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * @param  array<int, array{prep_minutes:int, qty:int}>  $lines
     */
    public function evaluate(Restaurant $restaurant, array $lines, ?Ticket $ticket): array
    {
        $prep = $this->prepMinutes($restaurant, $lines);
        $walk = $this->walkMinutes($restaurant, $ticket);
        $collect = (int) config('preorder.collect_minutes', 3);
        $margin = (int) config('preorder.safety_margin_minutes', 5);
        $away = max($walk, $prep) + $collect + $walk;

        $base = [
            'prep_minutes' => $prep,
            'walk_minutes' => $walk,
            'collect_minutes' => $collect,
            'away_minutes' => $away,
            'ready_in_minutes' => $prep,
            'margin_minutes' => $margin,
            'ticket_id' => $ticket?->id,
            'token' => $ticket?->token(),
            'ticket_status' => $ticket?->status,
            'wait_low' => null,
            'spare_minutes' => null,
        ];
        $breakdown = "{$walk} min walk there, ~{$prep} min in the kitchen (overlaps your walk), {$collect} min to collect and {$walk} min back — about {$away} min away from the queue.";

        if (! $ticket) {
            return $base + [
                'state' => 'no_ticket',
                'needs_confirmation' => false,
                'headline' => "Ready in about {$prep} min",
                'explanation' => "You're not in an active queue, so there's nothing to clash with. {$breakdown}",
            ];
        }

        $waitLow = $this->conservativeWait($ticket);
        $spare = $waitLow - $away;
        $base['wait_low'] = $waitLow;
        $base['spare_minutes'] = $spare;
        $token = $ticket->token();

        if ($ticket->status !== 'waiting') {
            $state = 'conflict';
            $headline = "Your token {$token} is being called now";
            $explanation = "Please go to your counter first. If you still order, collect it after your service — your place in the queue will not change because of this order.";
        } elseif ($spare >= $margin) {
            $state = 'ok';
            $headline = "Fits your wait — about {$spare} min to spare";
            $explanation = "Your turn ({$token}) is unlikely before ~{$waitLow} min (we use the early end of the estimate). {$breakdown}";
        } elseif ($spare >= 0) {
            $state = 'tight';
            $headline = 'Tight — only '.$spare.' min to spare';
            $explanation = "Your turn ({$token}) could come in ~{$waitLow} min. {$breakdown} Keep an eye on the live queue while you're away.";
        } else {
            $state = 'conflict';
            $headline = 'Likely not back before your turn ('.abs($spare).' min short)';
            $explanation = "Your turn ({$token}) could come in ~{$waitLow} min, but this order needs about {$away} min away. {$breakdown} If you're called while away, the queue will not wait — and this order won't change your place.";
        }

        return $base + [
            'state' => $state,
            'needs_confirmation' => $state === 'conflict',
            'headline' => $headline,
            'explanation' => $explanation,
        ];
    }
}
