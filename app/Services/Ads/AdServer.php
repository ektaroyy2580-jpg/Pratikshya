<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Picks a sponsored campaign for a placement using CONTEXT only (placement, page location, page category).
 * There is no behavioural profiling: we never read a user's history, tickets or reading/game activity
 * to choose an ad. The only per-viewer signal is an HMAC'd viewer hash used for frequency capping and
 * impression de-duplication.
 *
 * Ads never affect queue order, document checks, medical priority or staff decisions.
 */
class AdServer
{
    public function __construct(private AdSpend $spend) {}

    public function enabled(): bool
    {
        return (bool) config('ads.enabled', true);
    }

    /** Stable, non-reversible viewer key: per account when logged in, otherwise per session. */
    public function viewerHash(?User $user = null): string
    {
        $user ??= auth()->user();
        $basis = $user ? 'u:'.$user->id : 's:'.(session()->getId() ?: 'none');

        return hash_hmac('sha256', $basis, (string) config('app.key'));
    }

    /**
     * Whether the viewer has switched sponsored content off. Read defensively: the column is owned by
     * another module and may not exist. Default: ads are shown but contextual-only.
     */
    public function viewerOptedOut(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        static $cols = null;
        $cols ??= [
            'users' => Schema::hasTable('users') ? array_values(array_intersect(['ads_opt_out', 'hide_sponsored'], Schema::getColumnListing('users'))) : [],
            'prefs' => Schema::hasTable('user_preferences') ? array_values(array_intersect(['ads_opt_out', 'hide_sponsored'], Schema::getColumnListing('user_preferences'))) : [],
        ];
        foreach ($cols['users'] as $c) {
            if ($user->getAttribute($c)) {
                return true;
            }
        }
        if ($cols['prefs']) {
            $row = DB::table('user_preferences')->where('user_id', $user->id)->first($cols['prefs']);
            foreach ($cols['prefs'] as $c) {
                if ($row && $row->{$c}) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Context that must never show an ad (critical queue moments). */
    public function suppressed(array $context): bool
    {
        if (! empty($context['suppress'])) {
            return true;
        }
        $ticket = $context['ticket'] ?? null;

        return $ticket && in_array($ticket->status ?? null, ['called', 'serving'], true);
    }

    public static function normaliseCategory(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }
        $c = Str::of($category)->lower()->ascii()->trim()->value();
        $map = ['coffee' => 'cafe', 'tea' => 'cafe', 'food' => 'restaurant', 'books' => 'bookstore', 'book' => 'bookstore',
            'medical' => 'pharmacy', 'clinic' => 'hospital', 'shop' => 'shopping', 'mall' => 'shopping', 'ride' => 'transport'];
        $c = $map[$c] ?? $c;

        return array_key_exists($c, config('ads.categories')) ? $c : null;
    }

    /**
     * Live campaigns eligible for this placement + context, scored by contextual relevance.
     *
     * @return Collection<int, AdCampaign>
     */
    public function eligible(string $placement, array $context = [], ?User $user = null, ?string $viewer = null): Collection
    {
        if (! $this->enabled() || ! array_key_exists($placement, config('ads.placements')) || $this->suppressed($context)) {
            return collect();
        }
        $user ??= auth()->user();
        if ($this->viewerOptedOut($user)) {
            return collect();
        }
        $viewer ??= $this->viewerHash($user);
        $locationId = isset($context['location_id']) ? (int) $context['location_id'] : null;
        $category = self::normaliseCategory($context['category'] ?? null);
        $cap = (int) config('ads.delivery.frequency_cap_per_day', 6);

        return AdCampaign::live()->with('advertiser')->get()
            ->filter(fn (AdCampaign $c) => $c->hasPlacement($placement))
            // Location targeting is strict: a location-targeted ad only appears in that location's context.
            ->filter(fn (AdCampaign $c) => $c->target_location_id === null || $c->target_location_id === $locationId)
            // Category targeting: must match when the page has a category; general pages may show it.
            ->filter(fn (AdCampaign $c) => $c->target_category === null || $category === null || $c->target_category === $category)
            ->reject(fn (AdCampaign $c) => $this->spend->overBudget($c))
            ->reject(fn (AdCampaign $c) => $cap > 0 && AdEvent::where('campaign_id', $c->id)->where('type', 'impression')
                ->where('session_hash', $viewer)->where('created_at', '>=', now()->startOfDay())->count() >= $cap)
            ->map(function (AdCampaign $c) use ($locationId, $category) {
                $c->setAttribute('relevance', ($locationId && $c->target_location_id === $locationId ? 2 : 0)
                    + ($category && $c->target_category === $category ? 1 : 0));

                return $c;
            })
            ->values();
    }

    /** Best contextual match; ties rotate randomly so advertisers share inventory fairly. */
    public function pick(string $placement, array $context = [], ?User $user = null): ?AdCampaign
    {
        $eligible = $this->eligible($placement, $context, $user);
        if ($eligible->isEmpty()) {
            return null;
        }
        $top = $eligible->max('relevance');

        return $eligible->where('relevance', $top)->shuffle()->first();
    }

    // ---------------- Signed slot tokens (beacon cannot be forged for arbitrary campaigns) ----------------

    public function slotToken(AdCampaign $c, string $placement): string
    {
        $payload = $c->id.'.'.$placement.'.'.now()->timestamp;

        return $payload.'.'.substr(hash_hmac('sha256', $payload, 'ads-slot|'.config('app.key')), 0, 32);
    }

    /** @return array{campaign_id:int, placement:string}|null */
    public function parseToken(?string $token): ?array
    {
        if (! is_string($token) || ! preg_match('/^(\d+)\.([a-z_]+)\.(\d+)\.([a-f0-9]{32})$/', $token, $m)) {
            return null;
        }
        $payload = $m[1].'.'.$m[2].'.'.$m[3];
        $expected = substr(hash_hmac('sha256', $payload, 'ads-slot|'.config('app.key')), 0, 32);
        $ttl = (int) config('ads.delivery.token_ttl_minutes', 60) * 60;
        if (! hash_equals($expected, $m[4]) || (int) $m[3] < now()->timestamp - $ttl || (int) $m[3] > now()->timestamp + 60) {
            return null;
        }
        if (! array_key_exists($m[2], config('ads.placements'))) {
            return null;
        }

        return ['campaign_id' => (int) $m[1], 'placement' => $m[2]];
    }

    // ---------------- Event recording (real interactions only) ----------------

    /** Records an impression unless the same viewer saw this campaign in this placement within the dedupe window. */
    public function recordImpression(AdCampaign $c, string $placement, ?User $user = null, ?string $viewer = null): bool
    {
        return $this->recordDeduped('impression', $c, $placement, $user, $viewer);
    }

    public function recordClick(AdCampaign $c, string $placement, ?User $user = null, ?string $viewer = null): bool
    {
        return $this->recordDeduped('click', $c, $placement, $user, $viewer);
    }

    public function record(string $type, AdCampaign $c, string $placement, ?User $user = null, ?string $viewer = null): AdEvent
    {
        return AdEvent::create([
            'campaign_id' => $c->id, 'placement' => $placement, 'type' => $type,
            'user_id' => $user?->id, 'session_hash' => $viewer ?? $this->viewerHash($user),
        ]);
    }

    private function recordDeduped(string $type, AdCampaign $c, string $placement, ?User $user, ?string $viewer): bool
    {
        $user ??= auth()->user();
        $viewer ??= $this->viewerHash($user);
        $window = (int) config('ads.delivery.dedupe_minutes', 30);
        $lock = cache()->lock('ads:'.$type.':'.$c->id.':'.$placement.':'.$viewer, 5);

        return (bool) $lock->get(function () use ($type, $c, $placement, $user, $viewer, $window) {
            $seen = AdEvent::where('session_hash', $viewer)->where('campaign_id', $c->id)->where('placement', $placement)
                ->where('type', $type)->where('created_at', '>=', now()->subMinutes($window))->exists();
            if ($seen) {
                return false;
            }
            $this->record($type, $c, $placement, $user, $viewer);

            return true;
        });
    }
}
