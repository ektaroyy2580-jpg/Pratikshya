<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Explore & Ride Booking (module "rides").
 * A ride_request is OUR record of a trip the user planned. It never represents a booking
 * confirmed by a real provider: fares are our estimates, hand-offs open the provider's
 * official site/app, and demo bookings are simulated (is_demo = true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('vehicle_type', 32);
            $table->string('mode', 16); // walking|bike|car|transit
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('pickup_label', 120)->nullable();
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lng', 10, 7);
            $table->string('destination_label', 120)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('nearby_place_id')->nullable()->constrained('nearby_places')->nullOnDelete();
            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('est_minutes');
            $table->decimal('fare_low', 12, 2);
            $table->decimal('fare_high', 12, 2);
            $table->string('fare_source', 16)->default('estimate');
            // quoted|handed_off|demo_requested|demo_driver_assigned|demo_arriving|demo_on_trip|demo_completed|cancelled
            $table->string('status', 32)->default('quoted');
            $table->boolean('is_demo')->default(false);
            $table->string('external_reference', 120)->nullable();
            $table->boolean('consent_share')->default(false);
            $table->timestamp('handed_off_at')->nullable();
            $table->timestamp('demo_started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['consent_share', 'location_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_requests');
    }
};
