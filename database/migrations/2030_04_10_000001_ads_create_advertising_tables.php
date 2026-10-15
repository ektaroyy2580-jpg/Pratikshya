<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advertising module: advertisers, their portal users, campaigns, delivery events and rewarded-ad claims.
 * Ads never touch queue tables — no FK or column here influences tickets/services ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('category', 20);                       // cafe|restaurant|pharmacy|hospital|bookstore|shopping|transport|other
            $t->string('status', 20)->default('pending');      // pending|approved|suspended
            $t->string('contact_name', 120)->nullable();
            $t->string('contact_email', 160)->nullable();
            $t->string('contact_phone', 30)->nullable();
            $t->boolean('is_demo')->default(false);
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique('name');
            $t->index('status');
        });

        Schema::create('advertiser_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20)->default('owner');          // owner|manager|viewer
            $t->timestamps();
            $t->unique(['user_id', 'advertiser_id']);
        });

        Schema::create('ad_campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('headline', 80);
            $t->string('body', 200)->nullable();
            $t->string('cta_label', 30)->default('Learn more');
            $t->string('cta_url', 255);                         // internal path (/...) or https:// only
            $t->string('creative_image')->nullable();           // public disk path under ads/
            $t->string('creative_theme', 20)->default('blue'); // generated gradient creative
            $t->foreignId('target_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->string('target_category', 20)->nullable();
            $t->json('placements');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->decimal('budget_total', 12, 2)->default(0);
            $t->decimal('daily_budget', 12, 2)->default(0);
            $t->decimal('cpm_rate', 12, 2)->default(0);         // NPR per 1000 impressions
            $t->decimal('cpc_rate', 12, 2)->default(0);         // NPR per click
            $t->boolean('allows_rewarded')->default(false);
            $t->string('status', 20)->default('draft');         // draft|pending_review|active|paused|ended|rejected
            $t->boolean('is_demo')->default(false);
            $t->boolean('is_healthcare')->default(false);
            $t->string('healthcare_disclosure', 255)->nullable();
            $t->string('review_note', 255)->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'starts_on', 'ends_on']);
        });

        Schema::create('ad_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $t->string('placement', 30);
            $t->string('type', 20);                             // impression|click|reward_start|reward_complete
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('session_hash', 64);                     // HMAC of session/user — never a raw id
            $t->timestamp('created_at')->useCurrent();
            $t->index(['campaign_id', 'type', 'created_at']);
            $t->index(['session_hash', 'campaign_id', 'placement', 'type', 'created_at'], 'ad_events_dedupe_idx');
        });

        Schema::create('ad_reward_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $t->date('claim_date');
            $t->string('placement', 30)->default('game_hub');
            $t->string('reward_type', 20);                      // extra_round|cosmetic|points
            $t->string('status', 20)->default('started');       // started|completed|expired
            $t->timestamp('started_at');                        // server clock only
            $t->timestamp('completed_at')->nullable();
            $t->string('verified_by', 20)->nullable();          // demo_timer|provider
            $t->string('provider_txn', 120)->nullable()->unique();
            $t->integer('points_awarded')->default(0);
            $t->timestamp('consumed_at')->nullable();           // extra_round tokens: when a game used it
            $t->timestamps();
            $t->unique(['user_id', 'campaign_id', 'claim_date']);
            $t->index(['user_id', 'status', 'claim_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_reward_claims');
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('advertiser_users');
        Schema::dropIfExists('advertisers');
    }
};
