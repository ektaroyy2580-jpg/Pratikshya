<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Notification;

/** Database notification for queue changes (approaching, called, counter changed…). */
class QueueUpdate extends Notification
{
    public function __construct(public Ticket $ticket, public string $kind, public string $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'message' => $this->message,
            'ticket_id' => $this->ticket->id,
            'token' => $this->ticket->token(),
        ];
    }
}
