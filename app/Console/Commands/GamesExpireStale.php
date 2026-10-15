<?php

namespace App\Console\Commands;

use App\Services\Games\CompetitionService;
use Illuminate\Console\Command;

/** Cancels expired battle lobbies and finalises overdue games (the hub also does this lazily). */
class GamesExpireStale extends Command
{
    protected $signature = 'games:expire-stale';

    protected $description = 'Expire stale multiplayer competitions and finalise finished ones';

    public function handle(CompetitionService $games): int
    {
        $n = $games->expireStale();
        $this->info("Updated {$n} competition(s).");

        return self::SUCCESS;
    }
}
