<?php

namespace App\Policies;

use App\Models\Competition;
use App\Models\User;

class CompetitionPolicy
{
    /** Room, live state and results: players of this competition only (any seat status). */
    public function view(User $user, Competition $competition): bool
    {
        return $competition->players()->where('user_id', $user->id)->exists();
    }

    /** Submitting answers / ready / leave: must hold a seat (the service enforces the seat status). */
    public function play(User $user, Competition $competition): bool
    {
        return $this->view($user, $competition);
    }

    public function host(User $user, Competition $competition): bool
    {
        return $competition->host_id === $user->id;
    }
}
