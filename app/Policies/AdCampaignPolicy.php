<?php

namespace App\Policies;

use App\Models\AdCampaign;
use App\Models\Advertiser;
use App\Models\AdvertiserUser;
use App\Models\User;

/**
 * Advertisers only ever see and change their own campaigns. Only admins review (approve/reject).
 * Campaign status can never be set by an advertiser to "active" directly.
 */
class AdCampaignPolicy
{
    private function memberRole(User $user, AdCampaign $campaign): ?string
    {
        return AdvertiserUser::where('user_id', $user->id)->where('advertiser_id', $campaign->advertiser_id)->value('role');
    }

    /** Portal access: user belongs to at least one advertiser. */
    public function viewAny(User $user): bool
    {
        return AdvertiserUser::where('user_id', $user->id)->exists();
    }

    public function view(User $user, AdCampaign $campaign): bool
    {
        return $this->memberRole($user, $campaign) !== null;
    }

    public function create(User $user, ?Advertiser $advertiser = null): bool
    {
        if ($advertiser === null) {
            return AdvertiserUser::where('user_id', $user->id)->whereIn('role', ['owner', 'manager'])->exists();
        }

        return $advertiser->status !== 'suspended' && AdvertiserUser::where('user_id', $user->id)
            ->where('advertiser_id', $advertiser->id)->whereIn('role', ['owner', 'manager'])->exists();
    }

    public function update(User $user, AdCampaign $campaign): bool
    {
        return in_array($this->memberRole($user, $campaign), ['owner', 'manager'], true)
            && in_array($campaign->status, AdCampaign::EDITABLE, true)
            && $campaign->advertiser?->status !== 'suspended';
    }

    public function review(User $user, AdCampaign $campaign): bool
    {
        return $user->isAdmin();
    }
}
