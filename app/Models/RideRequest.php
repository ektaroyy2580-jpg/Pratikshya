<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A trip the user planned in the Smart Explore & Ride planner.
 * It is never a provider-confirmed booking: see config/rides.php honesty note.
 */
class RideRequest extends Model
{
    public const STATUSES = [
        'quoted', 'handed_off',
        'demo_requested', 'demo_driver_assigned', 'demo_arriving', 'demo_on_trip', 'demo_completed',
        'cancelled',
    ];

    public const DEMO_ACTIVE = ['demo_requested', 'demo_driver_assigned', 'demo_arriving', 'demo_on_trip'];

    // user_id, ticket_id, status, is_demo, fare fields and timestamps are set only by RidePlanner/providers.
    protected $fillable = ['pickup_label', 'destination_label'];

    protected $casts = [
        'pickup_lat' => 'float', 'pickup_lng' => 'float',
        'destination_lat' => 'float', 'destination_lng' => 'float',
        'distance_km' => 'float',
        'est_minutes' => 'integer',
        'fare_low' => 'decimal:2', 'fare_high' => 'decimal:2',
        'is_demo' => 'boolean',
        'consent_share' => 'boolean',
        'handed_off_at' => 'datetime',
        'demo_started_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function nearbyPlace()
    {
        return $this->belongsTo(NearbyPlace::class);
    }

    public function scopeOwnedBy($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function providerConfig(): array
    {
        return config('rides.providers.'.$this->provider, []);
    }

    public function providerName(): string
    {
        return $this->providerConfig()['name'] ?? ucfirst($this->provider);
    }

    public function vehicleConfig(): array
    {
        return $this->providerConfig()['vehicles'][$this->vehicle_type] ?? [];
    }

    public function vehicleLabel(): string
    {
        return $this->vehicleConfig()['label'] ?? ucfirst($this->vehicle_type);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, array_merge(['quoted', 'handed_off'], self::DEMO_ACTIVE), true);
    }

    public function fareRange(): string
    {
        return 'NPR '.number_format((float) $this->fare_low).'–'.number_format((float) $this->fare_high);
    }
}
