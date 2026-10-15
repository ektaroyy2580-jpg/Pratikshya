<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantMenu extends Model
{
    protected $fillable = ['restaurant_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function categories()
    {
        return $this->hasMany(MenuCategory::class)->orderBy('sort');
    }
}
