<?php

namespace App\Notifications\Games;

class InvitationReceived extends GameNotification
{
    public function kind(): string
    {
        return 'game_invitation';
    }
}
