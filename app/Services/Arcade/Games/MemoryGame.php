<?php

namespace App\Services\Arcade\Games;

use App\Models\ArcadeSession;
use App\Services\Arcade\ArcadeRejected;
use App\Services\Arcade\BaseGame;
use App\Services\Arcade\Outcome;

/**
 * Memory Match — 8 pairs. The browser submits its full move list; the server replays
 * every flip against the deck it dealt, so a forged "8 moves, 3 seconds" result fails.
 */
class MemoryGame extends BaseGame
{
    public const PAIRS = 8;

    private const FACES = ['bi-airplane', 'bi-bicycle', 'bi-camera', 'bi-cup-hot', 'bi-flower1', 'bi-gem', 'bi-heart', 'bi-moon-stars', 'bi-music-note-beamed', 'bi-palette', 'bi-snow', 'bi-sun', 'bi-tree', 'bi-umbrella', 'bi-lightning', 'bi-compass'];

    public function key(): string
    {
        return 'memory';
    }

    public function start(string $variant): array
    {
        $faces = $this->pick(self::FACES, self::PAIRS);
        $deck = $this->shuffle(array_merge($faces, $faces));

        return ['payload' => ['deck' => $deck], 'client' => ['deck' => $deck, 'pairs' => self::PAIRS]];
    }

    public function finish(ArcadeSession $session, array $input): Outcome
    {
        $deck = $session->payload['deck'];
        $n = count($deck);
        $moves = $input['moves'] ?? null;
        if (! is_array($moves) || count($moves) < self::PAIRS || count($moves) > 200) {
            throw new ArcadeRejected('Move list is missing or impossible (at least '.self::PAIRS.' moves are needed).');
        }

        $matched = [];
        foreach ($moves as $m) {
            if (! is_array($m) || count($m) !== 2) {
                throw new ArcadeRejected('Invalid move.');
            }
            [$a, $b] = array_map(fn ($v) => filter_var($v, FILTER_VALIDATE_INT), array_values($m));
            if ($a === false || $b === false || $a === $b || $a < 0 || $b < 0 || $a >= $n || $b >= $n || isset($matched[$a]) || isset($matched[$b])) {
                throw new ArcadeRejected('Invalid move in replay.');
            }
            if ($deck[$a] === $deck[$b]) {
                $matched[$a] = $matched[$b] = true;
            }
        }
        if (count($matched) !== $n) {
            throw new ArcadeRejected('Not every pair was matched.');
        }

        $count = count($moves);
        // Each move is two taps plus a reveal animation; ~0.5 s per move is the human floor.
        $this->assertMinElapsed($session, max(6000, $count * 500));

        $seconds = intdiv($session->elapsedMs(), 1000);
        $score = max(50, 1000 - ($count - self::PAIRS) * 35 - $seconds * 3);
        $points = $count <= 12 ? 15 : ($count <= 18 ? 10 : 6);

        return new Outcome($score, $points, true, [
            'headline' => 'All '.self::PAIRS.' pairs in '.$count.' moves',
            'stats' => [
                ['label' => 'Moves', 'value' => (string) $count],
                ['label' => 'Time', 'value' => gmdate('i:s', $seconds)],
                ['label' => 'Perfect would be', 'value' => (string) self::PAIRS],
            ],
        ]);
    }
}
