<?php

namespace App\Services\Arcade;

use App\Models\ArcadeSession;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\Rewards\RewardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates solo games: issues server-side play sessions, routes step actions to the
 * game, validates the final result once, and awards capped WaitPoints exactly once.
 * Nothing here touches tickets — games never change queue position.
 */
class ArcadeManager
{
    public const GAMES = [
        'memory' => Games\MemoryGame::class,
        'word' => Games\WordGame::class,
        'quiz' => Games\QuizGame::class,
        'cyber' => Games\CyberGame::class,
        'logic' => Games\LogicGame::class,
        'reaction' => Games\ReactionGame::class,
        'sudoku' => Games\SudokuGame::class,
        'uno' => Games\UnoGame::class,
    ];

    public function __construct(private RewardService $rewards) {}

    public function has(string $key): bool
    {
        return isset(self::GAMES[$key]);
    }

    public function game(string $key): ArcadeGame
    {
        abort_unless($this->has($key), 404);

        return app(self::GAMES[$key]);
    }

    /** Registry entry (title, icon...) from config/games.php. */
    public function meta(string $key): array
    {
        return collect(config('games.arcade', []))->firstWhere('key', $key) ?? ['key' => $key, 'title' => ucfirst($key), 'icon' => 'bi-controller', 'description' => '', 'players' => '1 player', 'tone' => 'blue'];
    }

    /** @return array{0: ArcadeSession, 1: array} */
    public function start(User $user, string $key, ?string $variant): array
    {
        $game = $this->game($key);
        $variants = $game->variants();
        $variant = in_array($variant, $variants, true) ? $variant : $variants[0];

        // Close this user's abandoned sessions of the same game (no reward, kept for history).
        ArcadeSession::where('user_id', $user->id)->where('game_key', $key)->where('status', 'active')
            ->update(['status' => 'abandoned', 'finished_at' => now(), 'updated_at' => now()]);

        ['payload' => $payload, 'client' => $client] = $game->start($variant);
        $session = ArcadeSession::create([
            'user_id' => $user->id, 'game_key' => $key, 'variant' => $variant, 'payload' => $payload, 'started_at' => now(),
        ]);

        return [$session, $client];
    }

    /** Step action (answer / move). Serialised per session so answers can't race. */
    public function interact(ArcadeSession $session, array $input): array
    {
        return Cache::lock('arcade:session:'.$session->id, 10)->block(5, function () use ($session, $input) {
            $session->refresh();
            $this->assertOpen($session);
            [$payload, $response] = $this->game($session->game_key)->interact($session, $input);
            $session->payload = $payload;
            $session->save();

            return $response;
        });
    }

    /** Validate and close a session exactly once. Returns the result data for the client. */
    public function finish(ArcadeSession $session, User $user, array $input): array
    {
        return Cache::lock('arcade:session:'.$session->id, 10)->block(5, function () use ($session, $user, $input) {
            $session->refresh();
            if ($session->status !== 'active') {
                throw new ArcadeRejected('This game was already submitted.', true, 409);
            }
            $this->assertOpen($session);

            try {
                $outcome = $this->game($session->game_key)->finish($session, $input);
            } catch (ArcadeRejected $e) {
                if ($e->final) {
                    $this->close($session, 'rejected', 0, false, ['headline' => 'Result not accepted', 'reason' => $e->getMessage()]);
                }
                throw $e;
            }

            if (! $this->close($session, 'finished', $outcome->score, $outcome->verified, $outcome->summary)) {
                throw new ArcadeRejected('This game was already submitted.', true, 409);
            }

            [$awarded, $note] = $this->award($session->fresh(), $user, $outcome);

            return [
                'score' => $outcome->score,
                'verified' => $outcome->verified,
                'points' => $awarded,
                'points_note' => $note,
                'summary' => $outcome->summary,
                'best' => $this->best($user, $session->game_key),
                'balance' => $this->rewards->balance($user),
            ];
        });
    }

    /** Atomic single-finish: only one request can flip active → finished/rejected. */
    private function close(ArcadeSession $session, string $status, int $score, bool $verified, array $summary): bool
    {
        return ArcadeSession::whereKey($session->id)->where('status', 'active')->update([
            'status' => $status, 'finished_at' => now(), 'score' => $score, 'verified' => $verified,
            'result' => json_encode($summary), 'updated_at' => now(),
        ]) === 1;
    }

    private function assertOpen(ArcadeSession $session): void
    {
        if (! $session->isActive()) {
            throw new ArcadeRejected('This game session has ended. Start a new game.', true, 409);
        }
        if ($session->started_at->lt(now()->subMinutes((int) config('arcade.session_max_minutes', 120)))) {
            $this->close($session, 'abandoned', 0, false, ['headline' => 'Session expired']);
            throw new ArcadeRejected('This game session expired. Start a new game.', true, 409);
        }
    }

    /** @return array{0:int,1:?string} */
    private function award(ArcadeSession $session, User $user, Outcome $outcome): array
    {
        if (! $outcome->verified || $outcome->points <= 0) {
            return [0, $outcome->points <= 0 ? null : 'Unverified results do not earn points.'];
        }
        $cap = (int) config('arcade.daily_points_cap', 120);
        $today = $this->earnedToday($user);
        $points = min($outcome->points, max(0, $cap - $today));
        if ($points <= 0) {
            return [0, 'Daily arcade limit of '.$cap.' WaitPoints reached. Play for fun!'];
        }
        $title = $this->meta($session->game_key)['title'] ?? $session->game_key;
        $tx = $this->rewards->award($user, $points, 'arcade', 'Arcade: '.$title, 'arcade:'.$session->id, $session);
        $given = $tx?->points ?? 0;
        if ($given > 0) {
            DB::table('arcade_sessions')->where('id', $session->id)->update(['points_awarded' => $given]);
        }

        return [$given, $given < $outcome->points ? 'Capped by the daily WaitPoints limit.' : null];
    }

    public function earnedToday(User $user): int
    {
        return (int) RewardTransaction::where('user_id', $user->id)->where('source', 'arcade')
            ->where('created_at', '>=', now()->startOfDay())->sum('points');
    }

    public function best(User $user, string $key): ?int
    {
        $best = ArcadeSession::where('user_id', $user->id)->where('game_key', $key)
            ->where('status', 'finished')->where('verified', true)->max('score');

        return $best === null || (int) $best <= 0 ? null : (int) $best;
    }

    public function playedCount(User $user, string $key): int
    {
        return ArcadeSession::where('user_id', $user->id)->where('game_key', $key)->where('status', 'finished')->count();
    }
}
