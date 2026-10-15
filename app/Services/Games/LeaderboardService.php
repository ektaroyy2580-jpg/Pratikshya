<?php

namespace App\Services\Games;

use App\Models\CompetitionPlayer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Weekly and all-time standings from completed competitions.
 * Privacy: other people appear under a stable pseudonym (e.g. "Player 7F3A"), never name/email.
 */
class LeaderboardService
{
    public static function alias(int $userId): string
    {
        return 'Player '.strtoupper(substr(hash('sha256', config('app.key').'|games|'.$userId), 0, 4));
    }

    /** @return Collection<int, array{user_id:int, name:string, is_you:bool, points:int, wins:int, games:int, rewards:int, position:int}> */
    public function standings(string $period = 'weekly', ?User $viewer = null, int $limit = 10): Collection
    {
        $rows = CompetitionPlayer::query()
            ->join('competitions', 'competitions.id', '=', 'competition_players.competition_id')
            ->where('competitions.status', 'completed')
            ->whereNotNull('competition_players.rank')
            ->when($period === 'weekly', fn ($q) => $q->where('competitions.completed_at', '>=', now()->startOfWeek()))
            ->groupBy('competition_players.user_id')
            ->selectRaw('competition_players.user_id as user_id, SUM(competition_players.score) as points, '
                .'SUM(CASE WHEN competition_players.rank = 1 THEN 1 ELSE 0 END) as wins, COUNT(*) as games, '
                .'COALESCE(SUM(competition_players.reward_points), 0) as rewards')
            ->orderByDesc('points')->orderByDesc('wins')->orderBy('user_id')
            ->limit($limit)->get();

        return $rows->values()->map(fn ($r, $i) => [
            'user_id' => (int) $r->user_id,
            'name' => $viewer && $viewer->id === (int) $r->user_id ? 'You' : self::alias((int) $r->user_id),
            'is_you' => $viewer && $viewer->id === (int) $r->user_id,
            'points' => (int) $r->points,
            'wins' => (int) $r->wins,
            'games' => (int) $r->games,
            'rewards' => (int) $r->rewards,
            'position' => $i + 1,
        ]);
    }

    /** Participation stats for the admin card. */
    public function stats(): array
    {
        return [
            'competitions' => \App\Models\Competition::count(),
            'completed' => \App\Models\Competition::where('status', 'completed')->count(),
            'live' => \App\Models\Competition::whereIn('status', \App\Models\Competition::LIVE)->count(),
            'players' => CompetitionPlayer::distinct('user_id')->count('user_id'),
            'answers' => \App\Models\CompetitionAnswer::count(),
            'questions' => \App\Models\GameQuestion::where('status', 'approved')->count(),
            'pending_questions' => \App\Models\GameQuestion::where('status', 'pending')->count(),
            'points_awarded' => (int) \App\Models\RewardTransaction::where('source', 'competition')->sum('points'),
            'this_week' => \App\Models\Competition::where('status', 'completed')->where('completed_at', '>=', now()->startOfWeek())->count(),
        ];
    }
}
