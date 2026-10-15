<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group bill splitting (module: bills). Money is decimal(12,2); all arithmetic is done in integer paisa
 * by App\Services\Bills\BillCalculator. Bills never touch queue tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('restaurant_name', 120)->nullable();
            $table->string('currency', 3)->default('NPR');
            $table->decimal('subtotal', 12, 2)->default(0);           // cached from the engine
            $table->decimal('total', 12, 2)->default(0);              // cached grand total (incl. tip)
            $table->string('discount_type', 10)->default('none');     // none|amount|percent
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('service_charge_percent', 5, 2)->default(10);
            $table->decimal('vat_percent', 5, 2)->default(13);
            $table->boolean('vat_on_service_charge')->default(true);  // Nepal convention: VAT on (net + service charge)
            $table->decimal('tip_amount', 12, 2)->default(0);
            $table->string('tip_allocation', 12)->default('equal');   // equal|proportional|custom
            $table->string('split_mode', 10)->default('equal');       // equal|itemized|custom
            $table->boolean('members_can_select')->default(true);     // members may pick their own items
            $table->string('payment_note', 160)->nullable();          // e.g. "eSewa ID 98XXXXXXXX" (shown to participants only)
            $table->string('status', 10)->default('open');            // open|settling|settled|archived
            $table->string('invite_token_hash', 64)->nullable()->unique(); // sha256 of the 40-char token
            $table->text('invite_token_encrypted')->nullable();       // encrypted copy so the owner can re-display the link/QR
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('invite_revoked_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('group_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_bill_id')->constrained('group_bills')->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('is_shared')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['group_bill_id', 'position']);
        });

        Schema::create('bill_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_bill_id')->constrained('group_bills')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 60);
            $table->string('role', 10)->default('member');            // owner|member
            $table->string('status', 10)->default('joined');          // invited|joined
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('custom_amount', 12, 2)->nullable();      // split_mode=custom
            $table->decimal('custom_tip', 12, 2)->nullable();         // tip_allocation=custom
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['group_bill_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('bill_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_bill_id')->constrained('group_bills')->cascadeOnDelete();
            $table->foreignId('group_bill_item_id')->constrained('group_bill_items')->cascadeOnDelete();
            $table->foreignId('bill_participant_id')->constrained('bill_participants')->cascadeOnDelete();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();
            $table->unique(['group_bill_item_id', 'bill_participant_id']);
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_bill_id')->constrained('group_bills')->cascadeOnDelete();
            $table->foreignId('bill_participant_id')->constrained('bill_participants')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 12);                              // demo|cash|esewa_link|khalti_link
            $table->string('status', 20)->default('claimed');         // claimed|confirmed_by_owner|disputed
            $table->string('reference', 60)->nullable();
            $table->string('note', 160)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['group_bill_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bill_allocations');
        Schema::dropIfExists('bill_participants');
        Schema::dropIfExists('group_bill_items');
        Schema::dropIfExists('group_bills');
    }
};
