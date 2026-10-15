<?php

namespace App\Services\Platform;

use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who may manage what. Platform admins (users.role = admin) manage everything;
 * organization admins (organization_user.role = org_admin) manage ONLY the
 * locations, services, counters, places and staff assignments of their own
 * organization(s). Everyone else manages nothing.
 */
class OrgAccess
{
    /** @var array<int, array<int>> */
    private array $cache = [];

    public function isPlatformAdmin(?User $user): bool
    {
        return $user !== null && $user->isAdmin();
    }

    public function isOrgAdmin(?User $user): bool
    {
        return $user !== null && $this->membershipOrgIds($user) !== [];
    }

    public function isManager(?User $user): bool
    {
        return $this->isPlatformAdmin($user) || $this->isOrgAdmin($user);
    }

    /** @return array<int> organization ids this user administers via membership */
    public function membershipOrgIds(User $user): array
    {
        return $this->cache[$user->id] ??= DB::table('organization_user')
            ->where('user_id', $user->id)->where('role', 'org_admin')
            ->pluck('organization_id')->map(fn ($id) => (int) $id)->all();
    }

    /** null = unrestricted (platform admin); otherwise the allowed organization ids. */
    public function scopeOrgIds(User $user): ?array
    {
        return $this->isPlatformAdmin($user) ? null : $this->membershipOrgIds($user);
    }

    /** null = unrestricted; otherwise the allowed location ids. */
    public function scopeLocationIds(User $user): ?array
    {
        $orgIds = $this->scopeOrgIds($user);
        if ($orgIds === null) {
            return null;
        }

        return $orgIds === [] ? [] : Location::whereIn('organization_id', $orgIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function canManageOrganization(User $user, Organization $org): bool
    {
        return $this->isPlatformAdmin($user) || in_array((int) $org->id, $this->membershipOrgIds($user), true);
    }

    public function canManageLocation(User $user, ?int $locationId): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }
        if (! $locationId) {
            return false;
        }
        $orgId = Location::whereKey($locationId)->value('organization_id');

        return $orgId !== null && in_array((int) $orgId, $this->membershipOrgIds($user), true);
    }

    public function forget(User $user): void
    {
        unset($this->cache[$user->id]);
    }
}
