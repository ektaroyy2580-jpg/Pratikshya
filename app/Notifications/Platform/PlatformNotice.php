<?php

namespace App\Notifications\Platform;

use Illuminate\Notifications\Notification;

/** Generic database notification (kind + message + optional same-site URL). */
class PlatformNotice extends Notification
{
    public function __construct(public string $kind, public string $message, public ?string $url = null, public array $extra = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge($this->extra, ['kind' => $this->kind, 'message' => $this->message, 'url' => $this->url]);
    }
}
