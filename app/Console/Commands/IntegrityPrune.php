<?php

namespace App\Console\Commands;

use App\Models\IntegritySignal;
use App\Services\Integrity\ExchangeService;
use Illuminate\Console\Command;

/**
 * Data-retention job for Dalal AI. Deletes integrity signals (hashed network ids, browser
 * families, event types) older than config('integrity.retention_days'), default 30.
 * Alerts and their review outcomes are kept as the audit record; they contain counts
 * and time spans only. Schedule daily.
 */
class IntegrityPrune extends Command
{
    protected $signature = 'integrity:prune {--days= : Override retention days} {--dry-run : Only count}';

    protected $description = 'Delete Dalal AI integrity signals older than the retention period';

    public function handle(ExchangeService $exchanges): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('integrity.retention_days')));
        $query = IntegritySignal::where('created_at', '<', now()->subDays($days));
        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} signals older than {$days} days would be deleted.");

            return self::SUCCESS;
        }

        $query->delete();
        $expired = $exchanges->expireStale();
        $this->info("Deleted {$count} integrity signals older than {$days} days. Expired {$expired} stale exchange requests.");

        return self::SUCCESS;
    }
}
