<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdRewardClaim extends Model
{
    // Timing/status fields are written only by RewardedAdService — never from request input.
    protected $guarded = ['id'];

    protected $casts = [
        'claim_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'consumed_at' => 'datetime',
        'points_awarded' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
