<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Platform module: organizations own locations; organization admins manage only
 * their own organization. Additive only. Existing locations are backfilled into
 * clearly demo-labelled organizations (one per sensible group).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug', 120)->unique();
            $t->string('type', 20)->index();            // government | bank | hospital | ward | private
            $t->string('contact_email')->nullable();
            $t->string('contact_phone', 30)->nullable();
            $t->string('website')->nullable();
            $t->json('settings')->nullable();           // { allow_queue_exchange: bool, ... }
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
        });

        Schema::table('locations', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
        });

        Schema::create('organization_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20)->default('org_admin');
            $t->timestamps();
            $t->unique(['organization_id', 'user_id']);
            $t->index('user_id');
        });

        // ---- Backfill: group existing locations into demo organizations ----
        $now = now();
        $orgId = function (string $name, string $type) use ($now): int {
            $slug = Str::slug($name);
            $id = DB::table('organizations')->where('slug', $slug)->value('id');

            return $id ?: DB::table('organizations')->insertGetId([
                'name' => $name, 'slug' => $slug, 'type' => $type,
                'settings' => json_encode(['allow_queue_exchange' => true]),
                'is_demo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        foreach (DB::table('locations')->whereNull('organization_id')->get() as $loc) {
            $name = strtolower($loc->name);
            $type = strtolower((string) $loc->type);
            [$org, $orgType] = match (true) {
                str_contains($name, 'transport') => ['Department of Transport Management (demo)', 'government'],
                str_contains($name, 'passport') => ['Department of Passports (demo)', 'government'],
                str_contains($type, 'bank') => ['Sagarmatha Demo Bank (fictional)', 'bank'],
                str_contains($type, 'hospital') || str_contains($name, 'hospital') => ['Kathmandu City Hospital Network (demo)', 'hospital'],
                str_contains($type, 'ward') || str_contains($name, 'ward') => ['Kathmandu Metropolitan City (demo)', 'ward'],
                str_contains($type, 'government') => ['Government Service Offices (demo)', 'government'],
                default => ['Independent Service Providers (demo)', 'private'],
            };
            DB::table('locations')->where('id', $loc->id)->update(['organization_id' => $orgId($org, $orgType)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::table('locations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('organization_id');
        });
        Schema::dropIfExists('organizations');
    }
};
