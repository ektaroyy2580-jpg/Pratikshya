<?php

namespace Database\Seeders\Modules;

use App\Models\Game;
use App\Services\Games\QuestionBank;
use Illuminate\Database\Seeder;

/**
 * Games module reference data (safe for any database, idempotent):
 * the Quick Brain Battle game row + the curated question bank (48 MCQs).
 * Demo players/tickets live in GamesDemoSeeder (demo databases only).
 */
class GamesSeeder extends Seeder
{
    public function run(): void
    {
        Game::quickBrainBattle();
        $n = QuestionBank::seed();
        $this->command?->info("Games: {$n} bank questions seeded.");
    }
}
