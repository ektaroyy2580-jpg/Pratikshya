<?php

namespace App\Services\Cafes;

use App\Models\NearbyPlace;
use App\Services\Explore\PlaceCategories;

/**
 * Representative photos for cafés, restaurants and dishes (catalogue in config/cafes.php).
 * Every lookup degrades to null so views can fall back to the CSS illustrations.
 */
class CafeImages
{
    public const CAFE_CATEGORIES = ['cafe', 'restaurant'];

    /** @return array<string, array{key:string, path:string, title:string, author:string, license:string, license_url:?string, source_url:string, alt:string}> */
    public static function catalog(): array
    {
        return once(function () {
            $out = [];
            foreach ((array) config('cafes.images', []) as $key => $row) {
                [$path, $title, $author, $license, $licenseUrl, $source, $alt] = array_pad(array_values($row), 7, null);
                $out[$key] = ['key' => $key, 'path' => $path, 'title' => $title, 'author' => $author, 'license' => $license,
                    'license_url' => $licenseUrl, 'source_url' => $source, 'alt' => $alt ?: 'Representative photo'];
            }

            return $out;
        });
    }

    public static function get(?string $key): ?array
    {
        return $key ? (self::catalog()[$key] ?? null) : null;
    }

    public static function byPath(?string $path): ?array
    {
        if (! $path) {
            return null;
        }
        foreach (self::catalog() as $img) {
            if ($img['path'] === $path) {
                return $img;
            }
        }

        return null;
    }

    /** Short attribution line stored in image_credit columns. */
    public static function credit(?array $img): ?string
    {
        return $img ? 'Photo: '.$img['author'].' · '.$img['license'].' · Wikimedia Commons' : null;
    }

    /** Public URL when the file really exists, otherwise null (→ CSS illustration). */
    public static function url(?string $path): ?string
    {
        if (! $path || str_contains($path, '..') || ! is_file(public_path($path))) {
            return null;
        }

        return asset($path);
    }

    public static function isCafe(NearbyPlace $place): bool
    {
        return in_array(PlaceCategories::keyOf($place), self::CAFE_CATEGORIES, true);
    }

    public static function themeFor(string $name, ?string $category = null): string
    {
        $hay = mb_strtolower($name);
        foreach ((array) config('cafes.theme_keywords', []) as $needle => $theme) {
            if (str_contains($hay, $needle)) {
                return $theme;
            }
        }

        return PlaceCategories::keyFor($category) === 'restaurant' ? 'restaurant' : 'cafe';
    }

    /** Gallery keys for a place; same-theme places rotate so heroes vary. @return string[] */
    public static function themeGallery(string $theme, int $seed = 0): array
    {
        $keys = array_values((array) (config('cafes.themes.'.$theme) ?: config('cafes.themes.cafe', [])));
        if (! $keys) {
            return [];
        }
        // Rotate only the café scenes (dish close-ups stay at the end) so a hero is always a café scene.
        $scenes = array_values(array_filter($keys, fn ($k) => str_starts_with($k, 'cafes/')));
        if (! $scenes) {
            return $keys;
        }
        $rest = array_values(array_diff($keys, $scenes));
        $shift = $seed % count($scenes);

        return array_merge(array_slice($scenes, $shift), array_slice($scenes, 0, $shift), $rest);
    }

    public static function dishKeyFor(string $name, ?string $art = null): ?string
    {
        $hay = mb_strtolower($name);
        foreach ((array) config('cafes.dish_keywords', []) as $needle => $key) {
            if (str_contains($hay, $needle)) {
                return $key;
            }
        }

        return $art ? (config('cafes.dish_art.'.$art) ?: null) : null;
    }

    /** Photo path for a dish by name/art (order lines keep no image of their own). */
    public static function dishPath(string $name, ?string $art = null): ?string
    {
        return self::get(self::dishKeyFor($name, $art))['path'] ?? null;
    }

    /**
     * Ready-to-render gallery for a café page: seeded `gallery` JSON first, then the theme.
     *
     * @return array<int, array{url:string, alt:string, credit:?string, source_url:?string}>
     */
    public static function galleryFor(NearbyPlace $place): array
    {
        $rows = [];
        $seeded = $place->gallery;
        if (is_string($seeded)) {
            $seeded = json_decode($seeded, true);
        }
        foreach ((array) $seeded as $g) {
            $path = is_array($g) ? ($g['path'] ?? null) : null;
            if ($url = self::url($path)) {
                $meta = self::byPath($path);
                $rows[$path] = ['url' => $url, 'alt' => $g['alt'] ?? ($meta['alt'] ?? $place->name), 'credit' => $g['credit'] ?? self::credit($meta), 'source_url' => $meta['source_url'] ?? null];
            }
        }
        if (! $rows) {
            foreach (self::themeGallery(self::themeFor($place->name, $place->category), (int) $place->id) as $key) {
                $img = self::get($key);
                if ($img && ($url = self::url($img['path']))) {
                    $rows[$img['path']] = ['url' => $url, 'alt' => $img['alt'], 'credit' => self::credit($img), 'source_url' => $img['source_url']];
                }
            }
        }

        return array_values($rows);
    }

    /** Hero thumbnail URL for list cards (null → icon / CSS art). */
    public static function heroUrl(NearbyPlace $place): ?string
    {
        if ($url = self::url($place->image_path ?? null)) {
            return $url;
        }

        return self::isCafe($place) ? (self::galleryFor($place)[0]['url'] ?? null) : null;
    }
}
