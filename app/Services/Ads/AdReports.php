<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use Illuminate\Support\Collection;

/** Reporting built ONLY from recorded ad_events. Nothing here is estimated except spend (labelled as an estimate). */
class AdReports
{
    public function __construct(private AdSpend $spend) {}

    /** @return array<string, int> keyed by campaign id => [type => count] */
    private function typeCounts(array $campaignIds): Collection
    {
        if (! $campaignIds) {
            return collect();
        }

        return AdEvent::whereIn('campaign_id', $campaignIds)->selectRaw('campaign_id, type, count(*) as n')
            ->groupBy('campaign_id', 'type')->get()->groupBy('campaign_id')
            ->map(fn ($rows) => $rows->pluck('n', 'type')->map(fn ($n) => (int) $n));
    }

    /** @param  Collection<int, AdCampaign>  $campaigns */
    public function forCampaigns(Collection $campaigns): Collection
    {
        $counts = $this->typeCounts($campaigns->pluck('id')->all());

        return $campaigns->mapWithKeys(function (AdCampaign $c) use ($counts) {
            $t = $counts->get($c->id, collect());
            $imp = (int) $t->get('impression', 0);
            $clk = (int) $t->get('click', 0);
            $spent = $this->spend->paisaFor($c, $imp, $clk);
            $budget = AdSpend::toPaisa($c->budget_total);

            return [$c->id => [
                'impressions' => $imp,
                'clicks' => $clk,
                'ctr' => $imp > 0 ? round($clk / $imp * 100, 1) : null,
                'reward_starts' => (int) $t->get('reward_start', 0),
                'reward_completions' => (int) $t->get('reward_complete', 0),
                'spend_paisa' => $spent,
                'budget_paisa' => $budget,
                'budget_used_pct' => $budget > 0 ? min(100, (int) round($spent / $budget * 100)) : 0,
            ]];
        });
    }

    public function totals(Collection $rows): array
    {
        $imp = (int) $rows->sum('impressions');
        $clk = (int) $rows->sum('clicks');

        return [
            'impressions' => $imp, 'clicks' => $clk, 'ctr' => $imp > 0 ? round($clk / $imp * 100, 1) : null,
            'reward_completions' => (int) $rows->sum('reward_completions'), 'spend_paisa' => (int) $rows->sum('spend_paisa'),
        ];
    }

    /** Last N days of impressions/clicks for one campaign (real events only). */
    public function daily(AdCampaign $c, int $days = 7): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = AdEvent::where('campaign_id', $c->id)->whereIn('type', ['impression', 'click'])->where('created_at', '>=', $from)
            ->get(['type', 'created_at'])->groupBy(fn ($e) => $e->created_at->toDateString());
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            $g = $rows->get($d->toDateString(), collect());
            $out[] = ['date' => $d, 'impressions' => $g->where('type', 'impression')->count(), 'clicks' => $g->where('type', 'click')->count()];
        }

        return $out;
    }

    /** Per-placement overview for admins: live campaigns and last-7-day events. */
    public function placements(): array
    {
        $live = AdCampaign::live()->get();
        $since = now()->subDays(7);
        $events = AdEvent::where('created_at', '>=', $since)->selectRaw('placement, type, count(*) as n')
            ->groupBy('placement', 'type')->get()->groupBy('placement');
        $out = [];
        foreach (config('ads.placements') as $key => $label) {
            $e = $events->get($key, collect())->pluck('n', 'type');
            $imp = (int) ($e['impression'] ?? 0);
            $clk = (int) ($e['click'] ?? 0);
            $out[$key] = [
                'label' => $label,
                'live' => $live->filter(fn ($c) => $c->hasPlacement($key))->count(),
                'impressions' => $imp, 'clicks' => $clk,
                'ctr' => $imp > 0 ? round($clk / $imp * 100, 1) : null,
            ];
        }

        return $out;
    }
}
