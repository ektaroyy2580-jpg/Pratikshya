<?php

namespace App\Notifications\Ops;

use App\Models\ServiceAnnouncement;
use Illuminate\Notifications\Notification;

/** Database notification: an office announcement affects a queue the user is in. Public text only. */
class OfficeAnnouncement extends Notification
{
    public function __construct(public ServiceAnnouncement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $a = $this->announcement;

        return [
            'kind' => 'office_announcement',
            'message' => $a->title.': '.$a->public_message,
            'announcement_id' => $a->id,
            'location_id' => $a->location_id,
            'service_id' => $a->service_id,
            'severity' => $a->severity,
        ];
    }
}
