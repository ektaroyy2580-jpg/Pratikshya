<?php

namespace App\Services\Arcade;

use RuntimeException;

/**
 * A submission the server refuses. `final` = the session is closed as rejected (cheating /
 * impossible result); otherwise the player may keep playing (e.g. sudoku not solved yet).
 */
class ArcadeRejected extends RuntimeException
{
    public function __construct(string $message, public readonly bool $final = true, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
