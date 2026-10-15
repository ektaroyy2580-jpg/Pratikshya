<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['impression', 'click', 'reward_start', 'reward_complete'];

    protected $fillable = ['campaign_id', 'placement', 'type', 'user_id', 'session_hash'];

    protected $casts = ['created_at' => 'datetime'];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
