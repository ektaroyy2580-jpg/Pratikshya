<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explore module: richer nearby place details, user explore preferences,
 * dismissed recommendations and hospital assistance profiles.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nearby_places', function (Blueprint $t) {
            $t->string('subcategory')->nullable();
            $t->string('address')->nullable();
            $t->string('phone', 40)->nullable();
            $t->text('opening_hours')->nullable();           // e.g. "07:00-20:00"; shown only when verified
            $t->boolean('hours_verified')->default(false);
            $t->boolean('is_partner')->default(false);       // participating business (e.g. preorder) — never boosts ranking
            $t->boolean('is_sponsored')->default(false);     // paid listing — never in neutral recommendations
            $t->text('accessibility_notes')->nullable();
            $t->index(['location_id', 'category']);
        });

        Schema::create('explore_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->boolean('suggestions_enabled')->default(true);
            $t->json('categories')->nullable();              // null = all recommendable categories
            $t->timestamps();
        });

        Schema::create('explore_dismissals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('nearby_place_id')->constrained('nearby_places')->cascadeOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'nearby_place_id']);
        });

        Schema::create('hospital_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('departments')->nullable();
            $t->json('lab_services')->nullable();
            $t->json('assistance_desks')->nullable();
            $t->json('accessibility')->nullable();
            $t->json('emergency_contacts')->nullable();
            $t->string('emergency_desk')->nullable();
            $t->string('official_source')->nullable();
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_profiles');
        Schema::dropIfExists('explore_dismissals');
        Schema::dropIfExists('explore_preferences');
        Schema::table('nearby_places', function (Blueprint $t) {
            $t->dropIndex(['location_id', 'category']);
            $t->dropColumn(['subcategory', 'address', 'phone', 'opening_hours', 'hours_verified', 'is_partner', 'is_sponsored', 'accessibility_notes']);
        });
    }
};
