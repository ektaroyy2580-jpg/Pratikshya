<?php

namespace App\Services\Ops;

use App\Models\StaffAssignment;
use App\Models\User;

/** Central permission rules for the ops module (location scoping + location-wide notices). */
class OpsAccess
{
    public static function isOperator(?User $user): bool
    {
        return $user !== null && in_array($user->role, ['staff', 'admin'], true);
    }

    public static function canOperate(?User $user, ?int $locationId): bool
    {
        return self::isOperator($user) && $locationId !== null && $user->canOperateLocation((int) $locationId);
    }

    /** Location-wide notices need admin, or an approved assignment with the explicit flag. */
    public static function canPublishLocationWide(?User $user, ?int $locationId): bool
    {
        if (! self::canOperate($user, $locationId)) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return StaffAssignment::where('user_id', $user->id)
            ->where('location_id', $locationId)
            ->where('is_approved', true)
            ->where('can_publish_location_notices', true)
            ->exists();
    }
}
