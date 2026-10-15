<?php

namespace App\Policies;

use App\Models\ArcadeSession;
use App\Models\User;

class ArcadeSessionPolicy
{
    /** Only the player who started a session may act on it (answers, moves, finish). */
    public function play(User $user, ArcadeSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
