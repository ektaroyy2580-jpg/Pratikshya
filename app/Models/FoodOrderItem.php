<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodOrderItem extends Model
{
    protected $fillable = ['food_order_id', 'menu_item_id', 'name', 'art', 'qty', 'unit_price', 'line_total', 'prep_minutes', 'notes'];

    protected $casts = ['qty' => 'integer', 'prep_minutes' => 'integer', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function order()
    {
        return $this->belongsTo(FoodOrder::class, 'food_order_id');
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
