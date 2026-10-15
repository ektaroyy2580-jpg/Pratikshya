<?php

namespace App\Services\Preorder;

use RuntimeException;

/** A business-rule violation that should be shown to the user (never a 500). */
class PreorderException extends RuntimeException
{
    public function __construct(string $message, public string $field = 'preorder')
    {
        parent::__construct($message);
    }
}
