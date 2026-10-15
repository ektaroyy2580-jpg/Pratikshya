<?php

namespace App\Notifications\Integrity;

use App\Models\QueueExchangeRequest;
use Illuminate\Notifications\Notification;

/** Database notification for voluntary queue exchange steps (request, accepted, declined, completed…). */
class ExchangeUpdate extends Notification
{
    public function __construct(public QueueExchangeRequest $exchange, public string $kind, public string $message, public ?int $ticketId = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'exchange_'.$this->kind,
            'message' => $this->message,
            'exchange_id' => $this->exchange->id,
            'ticket_id' => $this->ticketId,
        ];
    }
}
