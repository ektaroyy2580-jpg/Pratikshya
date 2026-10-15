<?php

namespace App\Notifications\Games;

/** Final ranking + approved (server-validated) WaitPoints reward. */
class CompetitionResult extends GameNotification
{
    public function kind(): string
    {
        return 'game_result';
    }
}
