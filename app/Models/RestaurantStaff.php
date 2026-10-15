<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Links a user account to a restaurant they may manage (merchant). */
class RestaurantStaff extends Model
{
    protected $table = 'restaurant_staff';

    protected $fillable = ['user_id', 'restaurant_id', 'role'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
