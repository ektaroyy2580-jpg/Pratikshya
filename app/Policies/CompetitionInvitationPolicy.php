<?php

namespace App\Policies;

use App\Models\CompetitionInvitation;
use App\Models\User;

class CompetitionInvitationPolicy
{
    /** Only the invited person may accept or decline. */
    public function respond(User $user, CompetitionInvitation $invitation): bool
    {
        return $invitation->invitee_id === $user->id;
    }
}
