<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ops module: operational incidents (+ update trail), public service announcements
 * (+ per-user delivery dedupe), QR public codes for services/locations and a
 * staff permission flag for location-wide notices. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->foreignId('counter_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $t->string('equipment', 120)->nullable();
            $t->string('category', 30);              // counter_closed|projector|printer|network|service_delay|queue_redirect|office_closed|repair|security|other
            $t->text('description');                  // INTERNAL — never shown publicly
            $t->string('priority', 10)->default('medium'); // low|medium|high|critical
            $t->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reported_at')->useCurrent();
            $t->string('photo_path')->nullable();     // private `local` disk
            $t->string('status', 20)->default('open'); // open|acknowledged|in_progress|resolved|closed
            $t->string('assigned_technician', 120)->nullable();
            $t->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('resolution_notes')->nullable();  // INTERNAL
            $t->boolean('is_internal')->default(false);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['location_id', 'status']);
            $t->index(['status', 'priority']);
        });

        Schema::create('incident_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operational_incident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 20);                   // created|status|comment|assignment|published
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20)->nullable();
            $t->text('body')->nullable();             // INTERNAL notes
            $t->timestamp('created_at')->useCurrent();
            $t->index(['operational_incident_id', 'created_at']);
        });

        Schema::create('service_announcements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete(); // null = whole location
            $t->foreignId('counter_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('operational_incident_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title', 80);
            $t->string('public_message', 280);
            $t->string('severity', 10)->default('info'); // info|warning|critical
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 12)->default('draft'); // draft|published|expired
            $t->timestamps();
            $t->index(['location_id', 'status', 'starts_at']);
            $t->index(['service_id', 'status']);
        });

        Schema::create('announcement_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['service_announcement_id', 'user_id']);
        });

        Schema::table('staff_assignments', function (Blueprint $t) {
            $t->boolean('can_publish_location_notices')->default(false);
        });

        Schema::table('services', function (Blueprint $t) {
            $t->string('qr_code', 32)->nullable()->unique();
        });
        Schema::table('locations', function (Blueprint $t) {
            $t->string('qr_code', 32)->nullable()->unique();
        });

        // Backfill public QR slugs (random, identify the destination only).
        foreach (['services', 'locations'] as $table) {
            foreach (DB::table($table)->whereNull('qr_code')->pluck('id') as $id) {
                do {
                    $code = Str::lower(Str::random(10));
                } while (DB::table($table)->where('qr_code', $code)->exists());
                DB::table($table)->where('id', $id)->update(['qr_code' => $code]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $t) {
            $t->dropUnique(['qr_code']);
            $t->dropColumn('qr_code');
        });
        Schema::table('services', function (Blueprint $t) {
            $t->dropUnique(['qr_code']);
            $t->dropColumn('qr_code');
        });
        Schema::table('staff_assignments', fn (Blueprint $t) => $t->dropColumn('can_publish_location_notices'));
        Schema::dropIfExists('announcement_deliveries');
        Schema::dropIfExists('service_announcements');
        Schema::dropIfExists('incident_updates');
        Schema::dropIfExists('operational_incidents');
    }
};
