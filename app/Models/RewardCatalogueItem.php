<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform module: a partner reward that can be redeemed with WaitPoints (demo partners only). */
class RewardCatalogueItem extends Model
{
    protected $fillable = ['slug', 'title', 'partner', 'description', 'icon', 'points_cost', 'stock', 'valid_days', 'is_active', 'is_demo'];

    protected $casts = ['is_active' => 'boolean', 'is_demo' => 'boolean', 'points_cost' => 'integer', 'stock' => 'integer'];

    public function inStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }
}
