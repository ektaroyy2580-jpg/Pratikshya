<?php

namespace App\Services\Ads;

use RuntimeException;

/** A user-facing advertising rule violation (message is safe to show). */
class AdException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
