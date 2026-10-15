<?php

namespace App\Notifications\Games;

use App\Models\Competition;
use Illuminate\Notifications\Notification;

/** Base database notification for the games module. Messages only ever mention token labels. */
abstract class GameNotification extends Notification
{
    public function __construct(public Competition $competition, public string $message)
    {
    }

    abstract public function kind(): string;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind(),
            'message' => $this->message,
            'competition_id' => $this->competition->id,
            'url' => $this->kind() === 'game_result'
                ? route('games.competitions.results', $this->competition)
                : ($this->kind() === 'game_invitation' ? route('games.hub') : route('games.competitions.show', $this->competition)),
        ];
    }
}
