<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Café hub: representative photos (with attribution) for places, cafés and dishes,
 * and an optional link from a group bill to the café (and food order) it was split at.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['nearby_places', 'restaurants', 'menu_items'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'image_path')) {
                    $t->string('image_path', 191)->nullable();      // relative to public/, e.g. images/cafes/momo-plate.jpg
                }
                if (! Schema::hasColumn($table, 'image_credit')) {
                    $t->string('image_credit', 255)->nullable();    // short attribution line
                }
            });
        }

        if (Schema::hasTable('nearby_places') && ! Schema::hasColumn('nearby_places', 'gallery')) {
            Schema::table('nearby_places', function (Blueprint $t) {
                $t->json('gallery')->nullable();                     // [{path, credit, alt}] representative photos
            });
        }

        if (Schema::hasTable('group_bills')) {
            Schema::table('group_bills', function (Blueprint $t) {
                if (! Schema::hasColumn('group_bills', 'nearby_place_id')) {
                    $t->foreignId('nearby_place_id')->nullable()->constrained('nearby_places')->nullOnDelete();
                }
                if (! Schema::hasColumn('group_bills', 'food_order_id') && Schema::hasTable('food_orders')) {
                    $t->foreignId('food_order_id')->nullable()->constrained('food_orders')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_bills')) {
            Schema::table('group_bills', function (Blueprint $t) {
                if (Schema::hasColumn('group_bills', 'food_order_id')) {
                    $t->dropConstrainedForeignId('food_order_id');
                }
                if (Schema::hasColumn('group_bills', 'nearby_place_id')) {
                    $t->dropConstrainedForeignId('nearby_place_id');
                }
            });
        }
        if (Schema::hasTable('nearby_places') && Schema::hasColumn('nearby_places', 'gallery')) {
            Schema::table('nearby_places', fn (Blueprint $t) => $t->dropColumn('gallery'));
        }
        foreach (['nearby_places', 'restaurants', 'menu_items'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'image_path')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['image_path', 'image_credit']));
            }
        }
    }
};
