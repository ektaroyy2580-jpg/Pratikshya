<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\Platform\OrgAccess;

/** Platform admins: everything. Org admins: view/update their own organization only. */
class OrganizationPolicy
{
    private function access(): OrgAccess
    {
        return app(OrgAccess::class);
    }

    public function viewAny(User $user): bool
    {
        return $this->access()->isManager($user);
    }

    public function view(User $user, Organization $org): bool
    {
        return $this->access()->canManageOrganization($user, $org);
    }

    public function update(User $user, Organization $org): bool
    {
        return $this->access()->canManageOrganization($user, $org);
    }

    public function create(User $user): bool
    {
        return $this->access()->isPlatformAdmin($user);
    }

    public function delete(User $user, Organization $org): bool
    {
        return $this->access()->isPlatformAdmin($user);
    }

    public function manageMembers(User $user, Organization $org): bool
    {
        return $this->access()->isPlatformAdmin($user);
    }
}
