<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    protected $fillable = ['restaurant_menu_id', 'name', 'sort'];

    public function menu()
    {
        return $this->belongsTo(RestaurantMenu::class, 'restaurant_menu_id');
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort');
    }
}
