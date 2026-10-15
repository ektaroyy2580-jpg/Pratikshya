<?php

namespace App\Services\Explore;

use App\Models\Location;
use App\Models\NearbyPlace;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cross-module links (rides, preorder). Only rendered when the other module's route exists,
 * so Explore never shows a dead button.
 */
class ExploreLinks
{
    public static function ride(NearbyPlace $place): ?string
    {
        return self::safe('rides.plan', ['place' => $place->id, 'location' => $place->location_id]);
    }

    public static function rideToLocation(Location $location): ?string
    {
        return self::safe('rides.plan', ['location' => $location->id]);
    }

    /**
     * Preorder only where the Preorder module has a participating business linked to this place
     * (restaurants.nearby_place_id). Without that module, no button is shown.
     */
    public static function preorder(NearbyPlace $place): ?string
    {
        if (! Route::has('preorder.restaurant')) {
            return null;
        }
        $restaurant = self::restaurants()[$place->id] ?? null;
        if (! $restaurant) {
            return null;
        }
        // Pre-ordering lives inside the café page.
        $cafe = self::safe('cafes.show', [$place]);

        return $cafe ? $cafe.'#menu' : self::safe('preorder.restaurant', [$restaurant]);
    }

    /** Café hub URL for cafés & restaurants (null for other places). */
    public static function cafe(NearbyPlace $place): ?string
    {
        return in_array(PlaceCategories::keyOf($place), ['cafe', 'restaurant'], true) ? self::safe('cafes.show', [$place]) : null;
    }

    /** @return array<int, \Illuminate\Database\Eloquent\Model> participating restaurants keyed by nearby_place_id (memoised per request) */
    private static function restaurants(): array
    {
        return once(function () {
            $class = 'App\Models\Restaurant';
            try {
                if (! class_exists($class) || ! Schema::hasTable('restaurants') || ! Schema::hasColumn('restaurants', 'nearby_place_id')) {
                    return [];
                }
                $q = $class::query()->whereNotNull('nearby_place_id');
                if (Schema::hasColumn('restaurants', 'accepts_preorders')) {
                    $q->where('accepts_preorders', true);
                }

                return $q->get()->keyBy('nearby_place_id')->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    private static function safe(string $name, array $params): ?string
    {
        if (! Route::has($name)) {
            return null;
        }
        try {
            return route($name, $params);
        } catch (Throwable) {
            return null; // route exists but expects different parameters
        }
    }
}
