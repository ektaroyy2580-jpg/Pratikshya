<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform module: per-user notification channels and privacy choices. */
class UserPreference extends Model
{
    public const DEFAULT_CHANNELS = ['database' => true, 'browser_push' => false, 'email' => false, 'sms' => false];

    protected $fillable = ['user_id', 'notification_channels', 'marketing_opt_in', 'personalised_ads', 'location_suggestions'];

    protected $casts = [
        'notification_channels' => 'array',
        'marketing_opt_in' => 'boolean',
        'personalised_ads' => 'boolean',
        'location_suggestions' => 'boolean',
        'anonymised_at' => 'datetime',
    ];

    protected $attributes = ['marketing_opt_in' => false, 'personalised_ads' => false, 'location_suggestions' => true];

    public static function for(User $user): self
    {
        return static::firstOrCreate(['user_id' => $user->id], ['notification_channels' => self::DEFAULT_CHANNELS]);
    }

    public function channels(): array
    {
        return array_merge(self::DEFAULT_CHANNELS, $this->notification_channels ?? [], ['database' => true]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
