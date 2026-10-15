<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform module: user privacy/notification preferences, a demo partner reward
 * catalogue with one-time redemption codes, and optional business-listing fields
 * on nearby places. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('notification_channels')->nullable();   // {database, browser_push, email, sms}
            $t->boolean('marketing_opt_in')->default(false);
            $t->boolean('personalised_ads')->default(false);
            $t->boolean('location_suggestions')->default(true);
            $t->timestamp('anonymised_at')->nullable();
            $t->timestamps();
        });

        Schema::create('reward_catalogue_items', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('title');
            $t->string('partner');
            $t->string('description', 500)->nullable();
            $t->string('icon', 40)->default('bi-gift');
            $t->unsignedInteger('points_cost');
            $t->unsignedInteger('stock')->nullable();        // null = unlimited
            $t->unsignedInteger('valid_days')->default(30);
            $t->boolean('is_active')->default(true);
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
        });

        Schema::create('reward_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reward_catalogue_item_id')->constrained('reward_catalogue_items')->cascadeOnDelete();
            $t->foreignId('reward_transaction_id')->nullable()->constrained('reward_transactions')->nullOnDelete();
            $t->string('reference_key', 120)->unique();
            $t->string('code', 24)->unique();
            $t->unsignedInteger('points');
            $t->string('status', 20)->default('issued');    // issued | used | expired
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });

        Schema::table('nearby_places', function (Blueprint $t) {
            if (! Schema::hasColumn('nearby_places', 'is_business_listing')) {
                $t->boolean('is_business_listing')->default(false);
            }
            if (! Schema::hasColumn('nearby_places', 'listing_phone')) {
                $t->string('listing_phone', 30)->nullable();
            }
            if (! Schema::hasColumn('nearby_places', 'listing_hours')) {
                $t->string('listing_hours', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('nearby_places', function (Blueprint $t) {
            $t->dropColumn(['is_business_listing', 'listing_phone', 'listing_hours']);
        });
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('reward_catalogue_items');
        Schema::dropIfExists('user_preferences');
    }
};
