<?php

namespace App\Services\Arcade;

/** Server-computed result of a finished game. */
final class Outcome
{
    public function __construct(
        public readonly int $score,
        public readonly int $points,
        public readonly bool $verified,
        public readonly array $summary = [],
    ) {}
}
