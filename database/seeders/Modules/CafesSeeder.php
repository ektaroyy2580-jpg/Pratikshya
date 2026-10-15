<?php

namespace Database\Seeders\Modules;

use App\Models\MenuItem;
use App\Models\NearbyPlace;
use App\Models\Restaurant;
use App\Services\Cafes\CafeImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Café hub: assigns representative Wikimedia Commons photos (catalogue in config/cafes.php)
 * to every café / restaurant in nearby_places, to preorder restaurants and to menu items.
 * Idempotent: it only (re)writes image_path / image_credit / gallery, keyed by the row itself.
 * Run after ExploreSeeder and PreorderSeeder. Creates no users.
 */
class CafesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('nearby_places', 'image_path')) {
            $this->command?->warn('Run the 2030_06_15 cafés migration first.');

            return;
        }

        $places = 0;
        $ordinal = []; // nth place of each theme → rotate the gallery so neighbours get different heroes
        NearbyPlace::orderBy('id')->get()->filter(fn ($p) => CafeImages::isCafe($p))->each(function (NearbyPlace $p) use (&$places, &$ordinal) {
            $theme = CafeImages::themeFor($p->name, $p->category);
            $n = $ordinal[$theme] = ($ordinal[$theme] ?? -1) + 1;
            $keys = CafeImages::themeGallery($theme, $n);
            $gallery = [];
            foreach ($keys as $k) {
                $img = CafeImages::get($k);
                if ($img && is_file(public_path($img['path']))) {
                    $gallery[] = ['path' => $img['path'], 'credit' => CafeImages::credit($img), 'alt' => $img['alt']];
                }
            }
            $hero = $gallery[0] ?? null;
            $p->forceFill([
                'image_path' => $hero['path'] ?? null,
                'image_credit' => $hero['credit'] ?? null,
                'gallery' => $gallery ? json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ])->save();
            $places++;
        });

        $restaurants = 0;
        if (Schema::hasTable('restaurants') && Schema::hasColumn('restaurants', 'image_path')) {
            Restaurant::with('nearbyPlace')->get()->each(function (Restaurant $r) use (&$restaurants) {
                $path = $r->nearbyPlace?->image_path;
                $credit = $r->nearbyPlace?->image_credit;
                if (! $path) {
                    $img = CafeImages::get(CafeImages::themeGallery(CafeImages::themeFor($r->name.' '.$r->cuisine))[0] ?? null);
                    $path = $img['path'] ?? null;
                    $credit = CafeImages::credit($img);
                }
                $r->forceFill(['image_path' => $path, 'image_credit' => $credit])->save();
                $restaurants++;
            });
        }

        $dishes = 0;
        if (Schema::hasTable('menu_items') && Schema::hasColumn('menu_items', 'image_path')) {
            MenuItem::orderBy('id')->get()->each(function (MenuItem $i) use (&$dishes) {
                $img = CafeImages::get(CafeImages::dishKeyFor($i->name, $i->art));
                $ok = $img && is_file(public_path($img['path']));
                $i->forceFill(['image_path' => $ok ? $img['path'] : null, 'image_credit' => $ok ? CafeImages::credit($img) : null])->save();
                $dishes += $ok ? 1 : 0;
            });
        }

        $this->command?->info("Cafés: photos for {$places} places, {$restaurants} restaurants, {$dishes} menu items.");
    }
}
