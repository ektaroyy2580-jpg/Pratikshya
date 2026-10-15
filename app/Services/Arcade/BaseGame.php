<?php

namespace App\Services\Arcade;

use App\Models\ArcadeSession;

abstract class BaseGame implements ArcadeGame
{
    public function variants(): array
    {
        return ['standard'];
    }

    public function interact(ArcadeSession $session, array $input): array
    {
        throw new ArcadeRejected('This game has no step actions.', false, 400);
    }

    /** Reject results that arrive faster than a human could possibly play. */
    protected function assertMinElapsed(ArcadeSession $session, int $minMs): void
    {
        if ($session->elapsedMs() < $minMs) {
            throw new ArcadeRejected('That was faster than humanly possible, so the result was not accepted.');
        }
    }

    protected function shuffle(array $items): array
    {
        return (new \Random\Randomizer)->shuffleArray($items);
    }

    protected function pick(array $items, int $n): array
    {
        return array_slice($this->shuffle($items), 0, min($n, count($items)));
    }
}
