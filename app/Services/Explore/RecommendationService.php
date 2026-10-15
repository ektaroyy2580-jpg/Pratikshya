<?php

namespace App\Services\Explore;

use App\Models\ExploreDismissal;
use App\Models\ExplorePreference;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Cafes\CafeImages;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Neutral, waiting-time-aware recommendations.
 *
 * A place fits when: walk there + visit + walk back to the queue location + safety buffer
 * ≤ remaining wait. The remaining wait is the LOW end of the queue estimate (conservative).
 * Sponsored listings are never part of these results, and partner status never affects ranking.
 */
class RecommendationService
{
    public const TZ = 'Asia/Kathmandu';

    public function __construct(private TravelEstimator $travel) {}

    /** The user's most relevant active ticket (or a specific one they own). */
    public function activeTicket(User $user, ?int $ticketId = null): ?Ticket
    {
        $q = $user->tickets()->whereIn('status', Ticket::ACTIVE)->with('service.location');
        if ($ticketId) {
            $q->whereKey($ticketId);
        }

        return $q->latest('id')->first();
    }

    /**
     * Conservative remaining minutes for a ticket.
     *
     * @return array{minutes:?int, status:string, source:string}
     */
    public function remainingFor(?Ticket $ticket, ?int $manualMinutes = null): array
    {
        if ($ticket && $ticket->isActive()) {
            if ($ticket->status !== 'waiting') {
                return ['minutes' => 0, 'status' => $ticket->status, 'source' => 'queue'];
            }
            $state = $ticket->stateArray();

            return ['minutes' => (int) $state['wait_low'], 'status' => 'waiting', 'source' => 'queue'];
        }
        if ($manualMinutes !== null) {
            return ['minutes' => max(0, min(240, $manualMinutes)), 'status' => 'manual', 'source' => 'manual'];
        }

        return ['minutes' => null, 'status' => 'none', 'source' => 'none'];
    }

    /**
     * @param  array{0:float,1:float}|null  $origin  where the user is now (defaults to the queue location)
     */
    public function recommend(User $user, Location $location, ?int $remaining, ?array $origin = null, ?int $limit = null): array
    {
        $prefs = ExplorePreference::for($user);
        $buffer = max(0, (int) config('explore.safety_buffer_minutes', 8));
        $base = ['enabled' => $prefs->suggestions_enabled, 'remaining' => $remaining, 'buffer' => $buffer, 'items' => []];

        if (! $prefs->suggestions_enabled) {
            return $base + ['message' => 'Location-based suggestions are turned off. You can turn them back on in Explore preferences.'];
        }
        if ($remaining === null) {
            return $base + ['message' => 'Join a queue (or tell us how many minutes you have) to see places that fit your wait.'];
        }
        if ($remaining <= $buffer) {
            return $base + ['message' => $remaining === 0
                ? 'It is your turn or very close to it — please stay near the counter.'
                : "About {$remaining} min left — that is inside our {$buffer}-minute safety buffer, so please stay close to the counter."];
        }

        $categories = $prefs->preferredCategories();
        $dismissed = ExploreDismissal::where('user_id', $user->id)->active()->pluck('nearby_place_id')->all();
        $maxWalk = (int) config('explore.max_walk_minutes', 15);

        $items = $this->candidates($location)
            ->reject(fn (NearbyPlace $p) => (bool) $p->is_sponsored)
            ->reject(fn (NearbyPlace $p) => in_array($p->id, $dismissed, true))
            ->filter(fn (NearbyPlace $p) => in_array(PlaceCategories::keyOf($p), $categories, true))
            ->map(fn (NearbyPlace $p) => $this->present($p, $location, $origin, $remaining))
            ->filter(fn (array $i) => $i['walk_there'] <= $maxWalk && $i['walk_back'] <= $maxWalk)
            ->filter(fn (array $i) => $i['fits'])
            ->filter(fn (array $i) => $i['open_status'] !== 'closed')
            // Neutral ranking: closer + better rated + comfortable spare time. Partner/sponsor flags are ignored.
            ->map(fn (array $i) => $i + ['score' => round($i['rating'] * 10 - $i['walk_there'] * 1.5 - ($i['spare'] - $buffer < 3 ? 6 : 0), 2)])
            ->sort(fn (array $a, array $b) => ($b['score'] <=> $a['score']) ?: ($a['id'] <=> $b['id']))
            ->take($limit ?? (int) config('explore.max_recommendations', 6))
            ->values()
            ->all();

        return array_merge($base, [
            'items' => $items,
            'message' => $items
                ? "You have approximately {$remaining} minutes. These fit with a {$buffer}-minute safety buffer."
                : "Nothing nearby fits safely in about {$remaining} minutes (we keep a {$buffer}-minute buffer). Stay close — you can still browse the map.",
        ]);
    }

    /** Places attached to the queue location (other locations' places are too far by design). */
    public function candidates(Location $location): Collection
    {
        return NearbyPlace::where('location_id', $location->id)->orderBy('id')->get();
    }

    /**
     * Place as an array for the map, list, drawer and recommendations.
     * Trip = origin → place → back to the queue location.
     */
    public function present(NearbyPlace $p, Location $location, ?array $origin = null, ?int $remaining = null): array
    {
        [$oLat, $oLng] = $origin ?? [(float) $location->lat, (float) $location->lng];
        $there = $this->travel->straight($oLat, $oLng, (float) $p->lat, (float) $p->lng);
        $back = $this->travel->straight((float) $p->lat, (float) $p->lng, (float) $location->lat, (float) $location->lng);
        $visit = max(1, (int) $p->visit_minutes);
        $total = $there['walk_minutes'] + $visit + $back['walk_minutes'];
        $buffer = max(0, (int) config('explore.safety_buffer_minutes', 8));
        $spare = $remaining !== null ? $remaining - $total : null;
        $key = PlaceCategories::keyOf($p);
        $hours = $this->hours($p, $total);

        $item = [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $key,
            'category_label' => PlaceCategories::label($key),
            'subcategory' => $p->subcategory,
            'icon' => PlaceCategories::icon($key),
            'color' => PlaceCategories::color($key),
            'tip' => $p->tip,
            'address' => $p->address,
            'phone' => $p->phone,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'rating' => (float) $p->rating,
            'distance_m' => $there['distance_m'],
            'straight_m' => $there['straight_m'],
            'walk_there' => $there['walk_minutes'],
            'walk_back' => $back['walk_minutes'],
            'visit' => $visit,
            'total' => $total,
            'spare' => $spare,
            'fits' => $spare !== null && $spare >= $buffer,
            'estimate_label' => TravelEstimator::STRAIGHT_LABEL,
            'hours_text' => $hours['text'],
            'hours_verified' => $hours['verified'],
            'open_status' => $hours['status'],
            'accessibility' => $p->accessibility_notes,
            'is_partner' => (bool) $p->is_partner,
            'sponsored' => (bool) $p->is_sponsored,
            // Cafés & restaurants open the café hub; everything else the place page.
            'is_cafe' => $isCafe = in_array($key, ['cafe', 'restaurant'], true),
            'url' => $isCafe && Route::has('cafes.show') ? route('cafes.show', $p) : route('explore.place', $p),
            'image' => $isCafe ? CafeImages::heroUrl($p) : null,
        ];
        $item['explanation'] = $this->explain($item, $remaining, $buffer);

        return $item;
    }

    public function explain(array $i, ?int $remaining, int $buffer): string
    {
        $walk = $i['walk_there'] === $i['walk_back']
            ? "{$i['walk_there']} min walk each way"
            : "{$i['walk_there']} min walk there + {$i['walk_back']} min back";
        $s = "{$i['total']} min total: {$walk} + {$i['visit']} min visit";
        if ($remaining === null) {
            return $s.'. Walking times are approximate (straight-line based).';
        }
        if ($i['spare'] >= $buffer) {
            $s .= ", leaves {$i['spare']} min spare before your estimated turn (we keep {$buffer} min as a safety buffer).";
        } elseif ($i['spare'] >= 0) {
            $s .= ", leaving only {$i['spare']} min spare — less than our {$buffer}-minute safety buffer, so not recommended now.";
        } else {
            $s .= ', which is '.abs($i['spare']).' min longer than your estimated wait — not recommended now.';
        }
        if (! $i['hours_verified']) {
            $s .= ' Opening hours not verified — check before you go.';
        }

        return $s;
    }

    /**
     * Opening hours are only treated as fact when verified and in a simple "HH:MM-HH:MM" format.
     *
     * @return array{text:string, verified:bool, status:string} status: open|closed|unknown
     */
    public function hours(NearbyPlace $p, int $tripMinutes = 0, ?CarbonInterface $now = null): array
    {
        if (! $p->hours_verified || ! $p->opening_hours) {
            return ['text' => 'Hours not verified', 'verified' => false, 'status' => 'unknown'];
        }
        if (! preg_match('/^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$/', $p->opening_hours, $m)) {
            return ['text' => $p->opening_hours, 'verified' => true, 'status' => 'unknown'];
        }
        $now ??= now(self::TZ);
        $mins = $now->hour * 60 + $now->minute;
        $open = (int) $m[1] * 60 + (int) $m[2];
        $close = (int) $m[3] * 60 + (int) $m[4];
        // Must be open now and still open when the visit would end.
        $isOpen = $close > $open
            ? ($mins >= $open && $mins + $tripMinutes <= $close)
            : ($mins >= $open || $mins + $tripMinutes <= $close); // overnight

        return ['text' => trim($p->opening_hours).' (verified)', 'verified' => true, 'status' => $isOpen ? 'open' : 'closed'];
    }
}
