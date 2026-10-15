<?php

namespace App\Policies;

use App\Models\OperationalIncident;
use App\Models\User;
use App\Services\Ops\OpsAccess;

/** Staff see/update incidents of their assigned locations only; admins see all. */
class OperationalIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return OpsAccess::isOperator($user);
    }

    public function view(User $user, OperationalIncident $incident): bool
    {
        return OpsAccess::canOperate($user, $incident->location_id);
    }

    public function update(User $user, OperationalIncident $incident): bool
    {
        return OpsAccess::canOperate($user, $incident->location_id);
    }

    /** Internal / security incidents can never become public notices. */
    public function publish(User $user, OperationalIncident $incident): bool
    {
        return $this->update($user, $incident) && ! $incident->isConfidential();
    }
}
