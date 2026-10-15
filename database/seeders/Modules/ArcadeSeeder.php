<?php

namespace Database\Seeders\Modules;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Arcade demo data: a demo player account. Games generate their own puzzles at play time,
 * so no game content needs seeding (question banks live in the game classes).
 */
class ArcadeSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'arcade.demo@waitwise.np'],
            ['name' => 'Arcade Demo (demo account)', 'password' => 'Demo@1234', 'phone' => '9800000002'],
        );
    }
}
