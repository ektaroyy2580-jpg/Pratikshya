<?php

namespace App\Http\Controllers\Platform\Concerns;

use App\Models\Location;
use App\Models\Organization;
use App\Services\Platform\OrgAccess;
use Illuminate\Database\Eloquent\Builder;

/** Shared scoping helpers: every lookup in the console goes through these. */
trait ScopesToOrganization
{
    protected function access(): OrgAccess
    {
        return app(OrgAccess::class);
    }

    protected function authorizeLocation(?int $locationId): void
    {
        abort_unless($this->access()->canManageLocation(request()->user(), $locationId), 403, 'This location belongs to another organization.');
    }

    /** Locations visible to the current manager. */
    protected function scopedLocations(): Builder
    {
        $ids = $this->access()->scopeOrgIds(request()->user());

        return Location::query()->when($ids !== null, fn ($q) => $q->whereIn('organization_id', $ids ?: [0]));
    }

    /** Organizations the current manager may assign records to. */
    protected function scopedOrganizations()
    {
        $ids = $this->access()->scopeOrgIds(request()->user());

        return Organization::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]))->orderBy('name')->get();
    }
}
