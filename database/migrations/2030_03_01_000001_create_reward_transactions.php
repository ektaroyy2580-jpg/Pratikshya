<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Shared WaitPoints ledger used by games, rewarded ads and other modules. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->integer('points');                       // positive = earned, negative = redeemed
            $t->string('source', 40);                    // game, competition, rewarded_ad, queue_completed, redemption…
            $t->string('reason');
            $t->string('reference_key', 120)->unique();  // idempotency: one award per reference
            $t->nullableMorphs('subject');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_transactions');
    }
};
