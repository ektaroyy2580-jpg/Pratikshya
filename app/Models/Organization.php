<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform module: an organization (government office, bank, hospital, ward, private) that owns locations. */
class Organization extends Model
{
    public const TYPES = ['government', 'bank', 'hospital', 'ward', 'private'];

    public const DEFAULT_SETTINGS = ['allow_queue_exchange' => true];

    protected $fillable = ['name', 'slug', 'type', 'contact_email', 'contact_phone', 'website', 'settings', 'is_demo'];

    protected $casts = ['settings' => 'array', 'is_demo' => 'boolean'];

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'organization_user')->withPivot('role')->withTimestamps();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? $default;
    }

    /** Whether voluntary queue exchange is allowed for a location (true when the location has no organization). */
    public static function allowsQueueExchange(?int $locationId): bool
    {
        $orgId = $locationId ? Location::whereKey($locationId)->value('organization_id') : null;
        $org = $orgId ? static::find($orgId) : null;

        return $org ? (bool) $org->setting('allow_queue_exchange', true) : true;
    }
}
