<?php

namespace App\Policies;

use App\Models\ServiceAnnouncement;
use App\Models\User;
use App\Services\Ops\OpsAccess;

class ServiceAnnouncementPolicy
{
    /** Service/counter notices: any operator of the location. Location-wide: admin or flagged staff. */
    public function manage(User $user, ServiceAnnouncement $announcement): bool
    {
        if (! OpsAccess::canOperate($user, $announcement->location_id)) {
            return false;
        }

        return ! $announcement->isLocationWide() || OpsAccess::canPublishLocationWide($user, $announcement->location_id);
    }
}
