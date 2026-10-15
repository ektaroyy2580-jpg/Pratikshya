<?php

namespace App\Notifications\Games;

class CompetitionStarting extends GameNotification
{
    public function kind(): string
    {
        return 'game_starting';
    }
}
