<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Demo data must use fictional businesses (master prompt §29). The original
 * seed used some real business names; rename them in place (ids unchanged,
 * so existing tickets, places and orders stay linked). Public landmarks and
 * government offices are kept.
 */
return new class extends Migration
{
    private const RENAMES = [
        'locations' => [
            'Nabil Bank — New Road' => 'Sagarmatha Demo Bank — New Road',
            'Bir Hospital OPD' => 'Kathmandu City Hospital OPD',
        ],
        'nearby_places' => [
            'Himalayan Java Coffee' => 'Jhamsikhel Brew Café',
            'Kumari Bank ATM & Print Shop' => 'Ekantakuna ATM & Print Shop',
            'Ekta Books' => 'Tripureshwor Book Corner',
            'Pilgrims Book House' => 'New Road Page Turners',
        ],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $table => $map) {
            foreach ($map as $old => $new) {
                DB::table($table)->where('name', $old)->update(['name' => $new]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $table => $map) {
            foreach ($map as $old => $new) {
                DB::table($table)->where('name', $new)->update(['name' => $old]);
            }
        }
    }
};
