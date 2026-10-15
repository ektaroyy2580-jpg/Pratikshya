<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * English speaking practice (module: english).
 * Privacy by design: there is NO audio column anywhere. Speech recognition runs in the
 * browser and only the transcript text reaches the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('english_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('type', 24)->index();            // conversation|interview|everyday|pronunciation|speaking_prompt
            $table->string('level', 16)->default('beginner'); // beginner|intermediate|advanced
            $table->string('title', 120);
            $table->string('description', 400)->nullable();
            $table->string('icon', 40)->default('bi-chat-dots');
            $table->json('turns');                           // [{prompt, hint?, target?, follow_up?}]
            $table->json('target_vocabulary')->nullable();   // ["experience", "responsible", ...]
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('english_practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('english_scenario_id')->constrained('english_scenarios')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->unsignedTinyInteger('duration_target_minutes')->default(5);
            $table->string('mode', 8)->default('text');      // voice|text
            $table->boolean('consent_audio')->default(false); // acknowledged the mic notice (audio is never stored)
            $table->string('status', 16)->default('active');  // active|completed
            $table->json('plan')->nullable();                 // server-chosen prompt order for this session
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('summary')->nullable();
            $table->json('scores')->nullable();               // clearly-named heuristic metrics, never a "pronunciation score"
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'ended_at']);
        });

        Schema::create('english_practice_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('english_practice_session_id')->constrained('english_practice_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('turn_index');
            $table->text('prompt');
            $table->text('user_text');                        // typed text or browser transcript
            $table->string('input_mode', 8)->default('text'); // voice|text
            $table->unsignedSmallInteger('elapsed_seconds')->nullable();
            $table->json('feedback')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['english_practice_session_id', 'turn_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('english_practice_turns');
        Schema::dropIfExists('english_practice_sessions');
        Schema::dropIfExists('english_scenarios');
    }
};
