<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrity module: Dalal AI signals + alerts, and the voluntary queue exchange.
 * Additive only. Signals hold no raw IPs (salted HMAC only) and are pruned after
 * config('integrity.retention_days') by `php artisan integrity:prune`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->boolean('requires_exchange_approval')->default(false);
        });

        Schema::create('integrity_signals', function (Blueprint $t) {
            $t->id();
            $t->string('type', 40);                       // duplicate_join, idempotent_retry, left, rejected_transition, rule_violation, staff_action, exchange_*
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();      // subject (ticket owner)
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // who acted
            $t->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->string('actor_role', 10)->nullable();
            $t->string('network_hash', 32)->nullable();   // HMAC-SHA256(ip, app key), truncated — never the raw IP
            $t->string('ua_family', 24)->nullable();      // coarse browser family only, e.g. "Chrome · mobile"
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['type', 'created_at']);
            $t->index(['user_id', 'type', 'created_at']);
            $t->index(['actor_id', 'type', 'created_at']);
            $t->index(['service_id', 'type', 'created_at']);
        });

        Schema::create('fraud_alerts', function (Blueprint $t) {
            $t->id();
            $t->string('type', 40);                       // duplicate_tokens, churn, multi_tokens, account_burst, exchanges, staff_actions, transitions, rule_bypass, staff_report
            $t->string('rule', 60);
            $t->unsignedTinyInteger('risk_score')->default(0);   // 0–100, an internal review signal only
            $t->string('severity', 10)->default('low');           // low | medium | high
            $t->string('status', 12)->default('open');            // open | reviewing | dismissed | confirmed | escalated
            $t->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('subject_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $t->foreignId('subject_service_id')->nullable()->constrained('services')->nullOnDelete();
            $t->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('evidence')->nullable();
            $t->text('explanation')->nullable();
            $t->string('explanation_provider', 80)->nullable();
            $t->json('recommended_actions')->nullable();
            $t->string('fingerprint', 120)->unique();
            $t->unsignedInteger('signal_count')->default(0);
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('review_outcome', 30)->nullable();
            $t->text('review_note')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'severity']);
            $t->index('rule');
        });

        Schema::create('queue_exchange_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requester_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $t->foreignId('target_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $t->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            // pending | awaiting_staff | completed | declined | cancelled | expired | rejected | failed
            $t->string('status', 16)->default('pending');
            // Set to the requester ticket id while the request is open; NULL once it closes.
            // The unique index allows only one open outgoing request per ticket.
            $t->unsignedBigInteger('open_key')->nullable()->unique();
            $t->unsignedInteger('requester_number_before');
            $t->unsignedInteger('target_number_before');
            $t->timestamp('expires_at');
            $t->timestamp('responded_at')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('decision_note', 255)->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'expires_at']);
            $t->index(['recipient_id', 'status']);
            $t->index(['requester_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_exchange_requests');
        Schema::dropIfExists('fraud_alerts');
        Schema::dropIfExists('integrity_signals');
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('requires_exchange_approval'));
    }
};
