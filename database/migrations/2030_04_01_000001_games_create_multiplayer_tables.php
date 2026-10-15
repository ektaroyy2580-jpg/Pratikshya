<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Games module: question bank + server-validated multiplayer competitions.
 * Scores live on competition_players; every answer is one row in competition_answers
 * (unique per question per player). Nothing here touches queue order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->string('title', 120);
            $t->string('description')->nullable();
            $t->string('mode', 20)->default('multiplayer'); // multiplayer | solo
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('game_questions', function (Blueprint $t) {
            $t->id();
            $t->string('question', 255);
            $t->json('options');                          // exactly 4 strings
            $t->unsignedTinyInteger('answer_index');      // 0..3
            $t->text('explanation');
            $t->string('category', 40);                   // nepal_gk | general | cybersecurity | logic
            $t->string('difficulty', 10);                 // easy | medium | hard
            $t->string('source', 10)->default('bank');    // bank | ai | admin
            $t->string('status', 12)->default('approved'); // approved | pending | disabled
            $t->string('hash', 64)->unique();             // dedupe on normalised question text
            $t->unsignedInteger('times_used')->default(0);
            $t->timestamps();
            $t->index(['status', 'category']);
        });

        Schema::create('competitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $t->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $t->string('status', 24)->default('waiting_for_players'); // waiting_for_players|ready|starting|in_progress|completed|cancelled
            $t->string('visibility', 12)->default('open');            // open | invite
            $t->string('category', 40)->nullable();
            $t->unsignedTinyInteger('max_players')->default(4);
            $t->unsignedTinyInteger('question_count')->default(5);
            $t->unsignedTinyInteger('seconds_per_question')->default(15);
            $t->unsignedBigInteger('starts_at_ms')->nullable();       // server clock, epoch ms
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->string('end_reason', 60)->nullable();
            $t->boolean('rewards_processed')->default(false);
            $t->timestamps();
            $t->index(['service_id', 'status']);
            $t->index(['status', 'expires_at']);
        });

        Schema::create('competition_players', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $t->string('token_label', 20);                // e.g. "D-24" — the only identity shown to others
            $t->string('status', 12)->default('joined');  // joined | ready | playing | paused | left | finished
            $t->integer('score')->default(0);
            $t->unsignedSmallInteger('correct_count')->default(0);
            $t->unsignedInteger('total_response_ms')->default(0);
            $t->unsignedTinyInteger('rank')->nullable();
            $t->integer('reward_points')->nullable();
            $t->string('left_reason', 60)->nullable();
            $t->timestamps();
            $t->unique(['competition_id', 'user_id']);
            $t->index(['user_id', 'status']);
        });

        Schema::create('competition_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $t->foreignId('game_question_id')->constrained('game_questions')->cascadeOnDelete();
            $t->unsignedTinyInteger('position');          // 0-based
            $t->unsignedBigInteger('opens_at_ms');
            $t->unsignedBigInteger('closes_at_ms');       // per-question deadline (server clock)
            $t->timestamps();
            $t->unique(['competition_id', 'position']);
        });

        Schema::create('competition_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $t->foreignId('competition_question_id')->constrained('competition_questions')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('choice');
            $t->boolean('is_correct');
            $t->unsignedSmallInteger('points');
            $t->unsignedInteger('response_ms');
            $t->timestamps();
            $t->unique(['competition_question_id', 'user_id']); // one answer per question per player
        });

        Schema::create('competition_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $t->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $t->string('status', 12)->default('pending'); // pending | accepted | declined | expired
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
            $t->unique(['competition_id', 'invitee_id']);
            $t->index(['invitee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_invitations');
        Schema::dropIfExists('competition_answers');
        Schema::dropIfExists('competition_questions');
        Schema::dropIfExists('competition_players');
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('game_questions');
        Schema::dropIfExists('games');
    }
};
