<?php

namespace App\Policies;

use App\Models\QueueExchangeRequest;
use App\Models\User;

class QueueExchangeRequestPolicy
{
    /** Only the two participants (and staff of that location) can see an exchange. */
    public function view(User $user, QueueExchangeRequest $x): bool
    {
        return in_array($user->id, [$x->requester_id, $x->recipient_id], true) || $this->decide($user, $x);
    }

    /** Accept / decline: the recipient only. */
    public function respond(User $user, QueueExchangeRequest $x): bool
    {
        return $user->id === $x->recipient_id;
    }

    /** Cancel: the requester only. */
    public function cancel(User $user, QueueExchangeRequest $x): bool
    {
        return $user->id === $x->requester_id;
    }

    /** Approve / reject: staff (or admin) operating the service's location. */
    public function decide(User $user, QueueExchangeRequest $x): bool
    {
        return in_array($user->role, ['staff', 'admin'], true)
            && $user->canOperateLocation((int) $x->service->location_id);
    }
}
