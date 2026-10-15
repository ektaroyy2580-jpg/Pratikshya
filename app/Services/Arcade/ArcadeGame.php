<?php

namespace App\Services\Arcade;

use App\Models\ArcadeSession;

/**
 * Contract every solo game implements. The server owns the puzzle/answers (payload);
 * the browser only receives `client` data and submits moves/answers/results for checking.
 */
interface ArcadeGame
{
    public function key(): string;

    /** Allowed variants (difficulty, opponents...). First entry is the default. */
    public function variants(): array;

    /** @return array{payload: array, client: array} */
    public function start(string $variant): array;

    /** Per-step interaction (quiz answer, UNO move). Returns [payload, response]. */
    public function interact(ArcadeSession $session, array $input): array;

    /** Validate the final result against the server payload. Throws ArcadeRejected. */
    public function finish(ArcadeSession $session, array $input): Outcome;
}
