<?php

namespace App\Services\Platform;

use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only analytics computed from local data. Optional modules are read
 * defensively: a missing table yields ['installed' => false] instead of an error.
 * Everything here is local demo data — never presented as real-world statistics.
 */
class PlatformAnalytics
{
    /** @param array<int>|null $locationIds null = all locations */
    public function overview(?array $locationIds = null): array
    {
        $services = Service::with('location')->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))->get();
        $serviceIds = $services->pluck('id')->all();
        $tickets = fn () => Ticket::whereIn('service_id', $serviceIds);

        $called = $tickets()->whereNotNull('called_at')->where('created_at', '>=', now()->subDays(30))->get(['id', 'service_id', 'created_at', 'called_at', 'serving_at', 'completed_at', 'initial_wait']);
        $avgWait = $called->avg(fn ($t) => max(0, $t->created_at->diffInSeconds($t->called_at)) / 60);

        $hours = array_fill(0, 24, 0);
        foreach ($tickets()->where('created_at', '>=', now()->subDays(30))->pluck('created_at') as $c) {
            $hours[(int) $c->format('G')]++;
        }

        $delays = $services->map(function (Service $s) use ($called) {
            $mine = $called->where('service_id', $s->id);
            $actualWait = $mine->avg(fn ($t) => max(0, $t->created_at->diffInSeconds($t->called_at)) / 60);
            $estWait = $mine->avg('initial_wait');
            $served = $mine->filter(fn ($t) => $t->completed_at);
            $actualService = $served->avg(fn ($t) => max(0, ($t->serving_at ?? $t->called_at)->diffInSeconds($t->completed_at)) / 60);

            return [
                'service' => $s,
                'paused' => (bool) $s->is_paused,
                'active' => (bool) $s->is_active,
                'waiting' => $s->waitingCount(),
                'samples' => $mine->count(),
                'est_wait' => $estWait !== null ? round($estWait, 1) : null,
                'actual_wait' => $actualWait !== null ? round($actualWait, 1) : null,
                'avg_minutes' => (int) $s->avg_minutes,
                'actual_service' => $actualService !== null ? round($actualService, 1) : null,
            ];
        })->sortByDesc(fn ($r) => [$r['paused'] ? 1 : 0, ($r['actual_wait'] ?? 0) - ($r['est_wait'] ?? 0)])->values();

        return [
            'users' => $locationIds === null ? User::count() : null,
            'active_queues' => $services->where('is_active', true)->where('is_paused', false)->count(),
            'paused_queues' => $services->where('is_paused', true)->count(),
            'waiting_now' => $tickets()->where('status', 'waiting')->count(),
            'virtual_waiting' => $services->sum(fn ($s) => $s->waitingCount()),
            'avg_wait' => $avgWait !== null ? round($avgWait, 1) : null,
            'avg_wait_samples' => $called->count(),
            'completed' => $tickets()->where('status', 'completed')->count(),
            'completed_today' => $tickets()->where('status', 'completed')->where('completed_at', '>=', now()->startOfDay())->count(),
            'hours' => $hours,
            'delays' => $delays,
        ];
    }

    /** Optional-module metrics; each entry: label, icon, installed, value, hint. */
    public function modules(): array
    {
        return [
            'games' => $this->metric('Game participation', 'bi-controller', ['competition_players', 'arcade_sessions', 'game_scores'], function () {
                $total = 0;
                $users = collect();
                foreach (['competition_players', 'arcade_sessions', 'game_scores'] as $t) {
                    if (Schema::hasTable($t)) {
                        $total += DB::table($t)->count();
                        if (Schema::hasColumn($t, 'user_id')) {
                            $users = $users->merge(DB::table($t)->whereNotNull('user_id')->distinct()->pluck('user_id'));
                        }
                    }
                }

                return [$total, $users->unique()->count().' distinct players · competitions + arcade'];
            }),
            'rewards' => $this->metric('Reward transactions', 'bi-stars', ['reward_transactions'], function (string $table) {
                $earned = (int) DB::table($table)->where('points', '>', 0)->sum('points');
                $spent = (int) -DB::table($table)->where('points', '<', 0)->sum('points');

                return [DB::table($table)->count(), number_format($earned).' pts earned · '.number_format($spent).' redeemed'];
            }),
            'campaigns' => $this->metric('Active ad campaigns', 'bi-megaphone', ['ad_campaigns'], function (string $table) {
                $q = DB::table($table);
                if (Schema::hasColumn($table, 'status')) {
                    $q->where('status', 'active');
                } elseif (Schema::hasColumn($table, 'is_active')) {
                    $q->where('is_active', true);
                }

                return [$q->count(), DB::table($table)->count().' campaigns in total'];
            }),
            'incidents' => $this->metric('Incident reports', 'bi-exclamation-triangle', ['operational_incidents'], function (string $table) {
                $open = Schema::hasColumn($table, 'status') ? DB::table($table)->whereNotIn('status', ['resolved', 'closed'])->count() : null;

                return [DB::table($table)->count(), $open !== null ? "{$open} open" : 'all time'];
            }),
            'fraud' => $this->metric('Integrity alerts', 'bi-shield-exclamation', ['fraud_alerts'], fn (string $t) => [DB::table($t)->count(), 'Dalal AI alerts, all time']),
            'exchanges' => $this->metric('Queue exchange requests', 'bi-arrow-left-right', ['queue_exchange_requests'], fn (string $t) => [DB::table($t)->count(), 'all time']),
        ];
    }

    private function metric(string $label, string $icon, array $tables, callable $fn): array
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                [$value, $hint] = $fn($table);

                return compact('label', 'icon', 'value', 'hint') + ['installed' => true];
            } catch (Throwable $e) {
                report($e);

                return compact('label', 'icon') + ['installed' => true, 'value' => null, 'hint' => 'Could not read this module\'s data'];
            }
        }

        return compact('label', 'icon') + ['installed' => false, 'value' => null, 'hint' => 'Module not installed'];
    }
}
