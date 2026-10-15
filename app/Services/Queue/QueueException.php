<?php

namespace App\Services\Queue;

use RuntimeException;

/** A business-rule violation with a message that is safe to show the user. */
class QueueException extends RuntimeException
{
}
