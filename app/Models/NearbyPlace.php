<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NearbyPlace extends Model
{
    protected $guarded = [];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'rating' => 'float'];
}
