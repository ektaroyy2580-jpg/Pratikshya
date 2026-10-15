<?php

namespace App\Policies;

use App\Models\RideRequest;
use App\Models\User;

/** Rides are private to the person who planned them — no staff/admin read-through. */
class RideRequestPolicy
{
    public function view(User $user, RideRequest $ride): bool
    {
        return $ride->user_id === $user->id;
    }

    public function update(User $user, RideRequest $ride): bool
    {
        return $ride->user_id === $user->id;
    }

    public function cancel(User $user, RideRequest $ride): bool
    {
        return $ride->user_id === $user->id && $ride->isCancellable();
    }
}
