<?php

namespace App\Policies;

use App\Models\Advertiser;
use App\Models\User;

class AdvertiserPolicy
{
    public function view(User $user, Advertiser $advertiser): bool
    {
        return $user->isAdmin() || $advertiser->hasMember($user);
    }

    /** Approve / reject / suspend an advertiser: platform admins only. */
    public function review(User $user, Advertiser $advertiser): bool
    {
        return $user->isAdmin();
    }
}
