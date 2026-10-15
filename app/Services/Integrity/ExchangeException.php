<?php

namespace App\Services\Integrity;

use App\Services\Queue\QueueException;

/** An exchange rule violation with a message that is safe to show the user. */
class ExchangeException extends QueueException
{
}
