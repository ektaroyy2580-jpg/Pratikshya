<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A business that takes queue-aware preorders (Cafe preordering module). */
class Restaurant extends Model
{
    protected $fillable = [
        'location_id', 'nearby_place_id', 'name', 'slug', 'cuisine', 'tagline', 'art',
        'pickup_instructions', 'prep_buffer_minutes', 'accepts_preorders', 'opening_note',
        'merchant_contact', 'lat', 'lng', 'walk_minutes', 'is_demo',
    ];

    /** Mirror DB defaults so freshly created models behave like loaded ones. */
    protected $attributes = ['accepts_preorders' => true, 'is_demo' => true, 'prep_buffer_minutes' => 5, 'art' => 'momo'];

    protected $casts = [
        'accepts_preorders' => 'boolean',
        'is_demo' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'prep_buffer_minutes' => 'integer',
        'walk_minutes' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeParticipating(Builder $q): Builder
    {
        return $q->where('accepts_preorders', true);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function nearbyPlace()
    {
        return $this->belongsTo(NearbyPlace::class);
    }

    public function menus()
    {
        return $this->hasMany(RestaurantMenu::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders()
    {
        return $this->hasMany(FoodOrder::class);
    }

    public function staffLinks()
    {
        return $this->hasMany(RestaurantStaff::class);
    }

    public function isManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || RestaurantStaff::where('restaurant_id', $this->id)->where('user_id', $user->id)->exists();
    }

    /** Restaurants this user may manage (admins: all). */
    public static function managedBy(User $user): Builder
    {
        return $user->isAdmin()
            ? static::query()
            : static::query()->whereIn('id', RestaurantStaff::where('user_id', $user->id)->select('restaurant_id'));
    }

    /** Quickest available item — used for "fits your wait?" hints. */
    public function quickestItem(): ?MenuItem
    {
        return $this->items()->where('available', true)->orderBy('prep_minutes')->first();
    }
}
