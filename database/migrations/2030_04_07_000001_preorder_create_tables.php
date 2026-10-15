<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cafe preordering module: participating restaurants, menus, orders.
 * Additive only. Money columns are decimal(12,2) NPR; app code does the
 * arithmetic in integer paisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->foreignId('nearby_place_id')->nullable()->constrained('nearby_places')->nullOnDelete();
            $t->string('name');
            $t->string('slug', 80)->unique();
            $t->string('cuisine', 80);
            $t->string('tagline')->nullable();
            $t->string('art', 30)->default('momo');          // CSS illustration key for the cover
            $t->text('pickup_instructions')->nullable();
            $t->unsignedSmallInteger('prep_buffer_minutes')->default(5);
            $t->boolean('accepts_preorders')->default(true)->index();
            $t->string('opening_note')->nullable();
            $t->string('merchant_contact', 60)->nullable();
            $t->decimal('lat', 10, 6)->nullable();
            $t->decimal('lng', 10, 6)->nullable();
            $t->unsignedSmallInteger('walk_minutes')->nullable(); // fallback when no nearby place / coordinates
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
        });

        Schema::create('restaurant_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20)->default('merchant');
            $t->timestamps();
            $t->unique(['user_id', 'restaurant_id']);
        });

        Schema::create('restaurant_menus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['restaurant_id', 'name']);
        });

        Schema::create('menu_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('restaurant_menu_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['restaurant_menu_id', 'name']);
        });

        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('menu_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('description', 500)->nullable();
            $t->decimal('price', 12, 2);
            $t->unsignedSmallInteger('prep_minutes')->default(10);
            $t->boolean('available')->default(true);
            $t->json('dietary_tags')->nullable();
            $t->string('art', 30)->default('momo');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['restaurant_id', 'name']);
            $t->index(['restaurant_id', 'available']);
        });

        Schema::create('food_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete(); // queue context, read-only
            $t->string('status', 20)->default('placed');   // placed|accepted|preparing|ready|collected|cancelled|rejected
            $t->decimal('subtotal', 12, 2);
            $t->decimal('service_charge', 12, 2);
            $t->decimal('vat', 12, 2);
            $t->decimal('total', 12, 2);
            $t->string('payment_mode', 20);                // demo|cash_on_pickup
            $t->string('payment_status', 20)->default('unpaid'); // unpaid|demo_paid
            $t->string('special_instructions', 500)->nullable();
            $t->unsignedSmallInteger('prep_minutes')->default(0);
            $t->timestamp('estimated_ready_at')->nullable();
            $t->string('pickup_code', 10);
            $t->string('timing_state', 20)->nullable();    // ok|tight|conflict|no_ticket
            $t->json('timing_snapshot')->nullable();
            $t->boolean('conflict_acknowledged')->default(false);
            $t->string('status_reason', 255)->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('preparing_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->timestamp('collected_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->string('idempotency_key', 64)->nullable()->unique();
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['restaurant_id', 'status']);
        });

        Schema::create('food_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('food_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('art', 30)->nullable();
            $t->unsignedSmallInteger('qty');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('line_total', 12, 2);
            $t->unsignedSmallInteger('prep_minutes')->default(0);
            $t->string('notes', 200)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_order_items');
        Schema::dropIfExists('food_orders');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('restaurant_menus');
        Schema::dropIfExists('restaurant_staff');
        Schema::dropIfExists('restaurants');
    }
};
