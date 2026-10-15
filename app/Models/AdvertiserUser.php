<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertiserUser extends Model
{
    public const ROLES = ['owner', 'manager', 'viewer'];

    protected $fillable = ['user_id', 'advertiser_id', 'role'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function advertiser()
    {
        return $this->belongsTo(Advertiser::class);
    }
}
