<?php

namespace App\Services\Games;

use RuntimeException;

/** A user-facing rule violation in the games module (message is safe to show). */
class GameException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422)
    {
        parent::__construct($message);
    }
}
