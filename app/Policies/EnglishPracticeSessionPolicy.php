<?php

namespace App\Policies;

use App\Models\EnglishPracticeSession;
use App\Models\User;

/** Practice sessions are private: only their owner can view or change them (staff/admin included). */
class EnglishPracticeSessionPolicy
{
    public function view(User $user, EnglishPracticeSession $session): bool
    {
        return (int) $session->user_id === (int) $user->id;
    }

    public function update(User $user, EnglishPracticeSession $session): bool
    {
        return (int) $session->user_id === (int) $user->id;
    }
}
