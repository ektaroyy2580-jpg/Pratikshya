<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $guarded = [];

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function places()
    {
        return $this->hasMany(NearbyPlace::class);
    }

    public function counters()
    {
        return $this->hasMany(Counter::class);
    }
}
