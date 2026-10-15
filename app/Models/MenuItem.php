<?php

namespace App\Models;

use App\Services\Preorder\Money;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'restaurant_id', 'menu_category_id', 'name', 'description', 'price',
        'prep_minutes', 'available', 'dietary_tags', 'art', 'sort',
    ];

    protected $attributes = ['available' => true, 'prep_minutes' => 10, 'art' => 'momo', 'sort' => 0];

    protected $casts = [
        'price' => 'decimal:2',
        'available' => 'boolean',
        'dietary_tags' => 'array',
        'prep_minutes' => 'integer',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category()
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function pricePaisa(): int
    {
        return Money::toPaisa($this->price);
    }
}
