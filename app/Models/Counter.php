<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    protected $fillable = ['location_id', 'service_id', 'number', 'label', 'is_open'];

    protected $casts = ['is_open' => 'boolean'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
