<?php

namespace App\Services\Explore;

use App\Models\NearbyPlace;

/**
 * Canonical explore categories. `nearby_places.category` is free text (older rows use
 * labels like "Cafe", "Heritage", "Errand"), so every place is normalised to a key here.
 */
class PlaceCategories
{
    /** key => [label, bootstrap icon, colour, recommendable while waiting?, aliases] */
    public const ALL = [
        'cafe' => ['Cafes', 'bi-cup-hot-fill', '#b45309', true, ['cafe', 'coffee', 'tea']],
        'restaurant' => ['Restaurants', 'bi-egg-fried', '#e11d48', true, ['restaurant', 'food']],
        'bookstore' => ['Bookstores', 'bi-book-half', '#7046e8', true, ['bookstore', 'books']],
        'shopping' => ['Shopping', 'bi-bag-fill', '#db2777', true, ['shopping centre', 'shopping center', 'shopping', 'market', 'mall']],
        'park' => ['Parks', 'bi-tree-fill', '#16a34a', true, ['park', 'garden']],
        'pharmacy' => ['Pharmacies', 'bi-capsule', '#0d9488', true, ['pharmacy', 'chemist']],
        'atm' => ['ATMs', 'bi-cash-coin', '#2f5bea', true, ['atm', 'errand', 'bank']],
        'convenience' => ['Convenience', 'bi-shop', '#ea580c', true, ['convenience store', 'convenience', 'grocery']],
        'sights' => ['Sights', 'bi-bank', '#9333ea', true, ['heritage', 'temple', 'sight']],
        'hospital' => ['Hospitals', 'bi-hospital-fill', '#dc2626', false, ['hospital', 'clinic']],
        'transport' => ['Transport', 'bi-signpost-split-fill', '#0f1535', false, ['transport', 'pickup point', 'taxi stand', 'bus stop']],
    ];

    public static function keyFor(?string $category): string
    {
        $c = strtolower(trim((string) $category));
        foreach (self::ALL as $key => $def) {
            if ($c === $key || in_array($c, $def[4], true)) {
                return $key;
            }
        }

        return 'other';
    }

    public static function keyOf(NearbyPlace $place): string
    {
        return self::keyFor($place->category);
    }

    public static function label(string $key): string
    {
        return self::ALL[$key][0] ?? 'Other';
    }

    public static function icon(string $key): string
    {
        return self::ALL[$key][1] ?? 'bi-geo-alt-fill';
    }

    public static function color(string $key): string
    {
        return self::ALL[$key][2] ?? '#667091';
    }

    /** Keys that make sense as a "while you wait" activity (not hospitals/pickup points). */
    public static function recommendable(): array
    {
        return array_keys(array_filter(self::ALL, fn ($d) => $d[3]));
    }

    /** Keys for UI chips. */
    public static function chips(): array
    {
        return collect(self::ALL)->map(fn ($d, $k) => ['key' => $k, 'label' => $d[0], 'icon' => $d[1], 'color' => $d[2]])->values()->all();
    }
}
