<?php

namespace App\Policies;

use App\Models\GroupBill;
use App\Models\User;

/** Only joined participants can see a bill; only its owner can change it. Admins get no implicit access to private bills. */
class GroupBillPolicy
{
    public function view(User $user, GroupBill $bill): bool
    {
        return $bill->participantFor($user) !== null;
    }

    public function manage(User $user, GroupBill $bill): bool
    {
        return $bill->isOwner($user);
    }
}
