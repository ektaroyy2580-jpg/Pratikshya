<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('area');
            $t->string('type');           // government, bank, hospital, ward
            $t->string('icon');
            $t->decimal('lat', 10, 6);
            $t->decimal('lng', 10, 6);
            $t->timestamps();
        });

        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('prefix', 2);
            $t->unsignedInteger('counter');
            $t->unsignedInteger('avg_minutes');
            $t->unsignedInteger('current_number');   // token currently at the counter
            $t->unsignedInteger('last_number');      // last token issued
            $t->json('documents');
            $t->timestamps();
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('number');
            $t->string('name')->default('You');
            $t->string('status')->default('waiting');  // waiting, called, serving, completed
            $t->boolean('docs_ready')->default(false);
            $t->boolean('docs_verified')->default(false);
            $t->unsignedInteger('initial_wait');
            $t->unsignedInteger('game_points')->default(0);
            $t->timestamp('called_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('nearby_places', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('category');
            $t->string('emoji');
            $t->string('tip');
            $t->decimal('lat', 10, 6);
            $t->decimal('lng', 10, 6);
            $t->unsignedInteger('walk_minutes');
            $t->unsignedInteger('visit_minutes');
            $t->decimal('rating', 2, 1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nearby_places');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('services');
        Schema::dropIfExists('locations');
    }
};
