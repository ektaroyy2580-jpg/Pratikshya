<?php

namespace App\Console\Commands;

use App\Services\Integrity\DalalEngine;
use Illuminate\Console\Command;

/** Full Dalal AI pass over the last 24h of integrity signals. Schedule every 15 minutes. */
class DalalScan extends Command
{
    protected $signature = 'dalal:scan {--no-ai : Keep deterministic template explanations}';

    protected $description = 'Run Dalal AI integrity rules over recent signals, expire stale exchange requests';

    public function handle(DalalEngine $engine): int
    {
        $r = $engine->scan(! $this->option('no-ai'));
        $this->info("Signals checked: {$r['signals']} · rule matches: {$r['alerts_matched']} · exchanges expired: {$r['exchanges_expired']} · AI reworded: {$r['ai_reworded']}");

        return self::SUCCESS;
    }
}
