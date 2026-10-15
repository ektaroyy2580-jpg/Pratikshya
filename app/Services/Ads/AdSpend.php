<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use Carbon\CarbonInterface;

/**
 * Spend ESTIMATES derived only from recorded events (impressions × CPM/1000 + clicks × CPC).
 * All arithmetic in integer paisa. No billing happens anywhere in this app.
 */
class AdSpend
{
    public static function toPaisa(string|int|float|null $npr): int
    {
        return (int) round(((float) ($npr ?? 0)) * 100);
    }

    public static function format(int $paisa): string
    {
        return 'NPR '.number_format($paisa / 100, 2);
    }

    /** @return array{impressions:int, clicks:int} */
    public function counts(AdCampaign $c, ?CarbonInterface $since = null): array
    {
        $rows = AdEvent::where('campaign_id', $c->id)->whereIn('type', ['impression', 'click'])
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return ['impressions' => (int) ($rows['impression'] ?? 0), 'clicks' => (int) ($rows['click'] ?? 0)];
    }

    public function paisaFor(AdCampaign $c, int $impressions, int $clicks): int
    {
        $cpm = self::toPaisa($c->cpm_rate);
        $cpc = self::toPaisa($c->cpc_rate);

        // Round half up to the nearest paisa for the CPM part.
        return intdiv($impressions * $cpm + 500, 1000) + $clicks * $cpc;
    }

    public function spentPaisa(AdCampaign $c, ?CarbonInterface $since = null): int
    {
        ['impressions' => $i, 'clicks' => $k] = $this->counts($c, $since);

        return $this->paisaFor($c, $i, $k);
    }

    /** True when the total or today's budget is exhausted (a zero total budget never serves). */
    public function overBudget(AdCampaign $c): bool
    {
        $total = self::toPaisa($c->budget_total);
        if ($total <= 0 || $this->spentPaisa($c) >= $total) {
            return true;
        }
        $daily = self::toPaisa($c->daily_budget);

        return $daily > 0 && $this->spentPaisa($c, now()->startOfDay()) >= $daily;
    }
}
