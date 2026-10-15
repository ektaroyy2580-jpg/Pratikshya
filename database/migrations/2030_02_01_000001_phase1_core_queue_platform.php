<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: roles, staff assignments, counters, queue events, document
 * verification, password resets and integrity constraints on tickets.
 * Additive only — existing rows are kept and backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('user')->index();   // user | staff | admin
            $t->string('phone', 20)->nullable();
        });

        Schema::table('services', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
            $t->boolean('is_paused')->default(false);
            $t->boolean('allow_exchange')->default(true);
            $t->boolean('requires_all_documents')->default(false);
        });

        Schema::create('counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('number');
            $t->string('label')->nullable();
            $t->boolean('is_open')->default(true);
            $t->timestamps();
            $t->unique(['location_id', 'number']);
        });

        Schema::create('staff_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_approved')->default(true);
            $t->timestamps();
            $t->unique(['user_id', 'location_id']);
        });

        Schema::table('tickets', function (Blueprint $t) {
            $t->foreignId('counter_id')->nullable()->constrained()->nullOnDelete();
            $t->json('doc_checklist')->nullable();
            $t->timestamp('serving_at')->nullable();
            $t->timestamp('left_at')->nullable();
            // Set to "service:user" while the entry is active, NULL otherwise.
            // A unique index on it blocks duplicate active entries on any DB engine.
            $t->string('active_key')->nullable()->unique();
            $t->string('idempotency_key', 64)->nullable()->unique();
            $t->unique(['service_id', 'number']);
        });

        Schema::create('queue_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 40);
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['ticket_id', 'created_at']);
        });

        Schema::create('document_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $t->string('result', 20);              // verified | incomplete | rejected
            $t->json('missing')->nullable();
            $t->string('reason')->nullable();
            $t->timestamp('verified_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 60);
            $t->nullableMorphs('subject');
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('action');
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('type');
                $t->morphs('notifiable');
                $t->text('data');
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
            });
        }

        // Backfill: one counter per existing service, linked to its tickets.
        foreach (DB::table('services')->get() as $s) {
            $counterId = DB::table('counters')->where('location_id', $s->location_id)->where('number', $s->counter)->value('id')
                ?? DB::table('counters')->insertGetId([
                    'location_id' => $s->location_id, 'service_id' => $s->id, 'number' => $s->counter,
                    'label' => 'Counter '.$s->counter, 'is_open' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('tickets')->where('service_id', $s->id)->update(['counter_id' => $counterId]);
        }
        foreach (DB::table('tickets')->whereNotNull('user_id')->whereIn('status', ['waiting', 'called', 'serving'])->orderByDesc('id')->get() as $tk) {
            $key = $tk->service_id.':'.$tk->user_id;
            if (! DB::table('tickets')->where('active_key', $key)->exists()) {
                DB::table('tickets')->where('id', $tk->id)->update(['active_key' => $key]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('document_verifications');
        Schema::dropIfExists('queue_events');
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropUnique(['service_id', 'number']);
            $t->dropUnique(['active_key']);
            $t->dropUnique(['idempotency_key']);
            $t->dropConstrainedForeignId('counter_id');
            $t->dropColumn(['doc_checklist', 'serving_at', 'left_at', 'active_key', 'idempotency_key']);
        });
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('counters');
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn(['is_active', 'is_paused', 'allow_exchange', 'requires_all_documents']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone']));
    }
};
