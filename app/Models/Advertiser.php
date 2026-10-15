<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advertiser extends Model
{
    public const STATUSES = ['pending', 'approved', 'suspended'];

    // status / is_demo / reviewed_* are set explicitly by admin or seeder code, never mass-assigned from requests.
    protected $fillable = ['name', 'category', 'contact_name', 'contact_email', 'contact_phone'];

    protected $casts = ['is_demo' => 'boolean', 'reviewed_at' => 'datetime'];

    public function campaigns()
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'advertiser_users')->withPivot('role')->withTimestamps();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isHealthcare(): bool
    {
        return in_array($this->category, config('ads.healthcare_categories', []), true);
    }

    public function hasMember(?User $user): bool
    {
        return $user !== null && AdvertiserUser::where('advertiser_id', $this->id)->where('user_id', $user->id)->exists();
    }

    /** Advertiser ids the user belongs to (portal scope). */
    public static function idsFor(?User $user): array
    {
        return $user ? AdvertiserUser::where('user_id', $user->id)->pluck('advertiser_id')->all() : [];
    }
}
