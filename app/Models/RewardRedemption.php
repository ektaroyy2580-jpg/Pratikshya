<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform module: an issued one-time redemption code. Status/code are set by RedemptionService only. */
class RewardRedemption extends Model
{
    protected $guarded = ['*'];

    protected $casts = ['expires_at' => 'datetime', 'points' => 'integer'];

    public function item()
    {
        return $this->belongsTo(RewardCatalogueItem::class, 'reward_catalogue_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
