<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arcade (solo games): one row per play. The puzzle / answers / deck live in `payload`
 * (server-side only, never sent wholesale to the browser). A session can be finished once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arcade_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('game_key', 30);
            $t->string('variant', 30)->nullable();          // difficulty / opponents
            $t->json('payload');                            // server-held seed, puzzle, answers, game state
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->integer('score')->nullable();
            $t->boolean('verified')->default(false);
            $t->string('status', 20)->default('active');    // active | finished | rejected
            $t->json('result')->nullable();                 // summary shown on the result screen
            $t->unsignedInteger('points_awarded')->default(0);
            $t->timestamps();
            $t->index(['user_id', 'game_key', 'started_at']);
            $t->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arcade_sessions');
    }
};
