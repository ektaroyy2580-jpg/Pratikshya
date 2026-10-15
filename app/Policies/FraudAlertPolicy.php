<?php

namespace App\Policies;

use App\Models\FraudAlert;
use App\Models\User;

/** Dalal AI alerts are internal: admins only. Users never see alerts about anyone, including themselves. */
class FraudAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, FraudAlert $alert): bool
    {
        return $user->isAdmin();
    }

    public function review(User $user, FraudAlert $alert): bool
    {
        return $user->isAdmin();
    }
}
