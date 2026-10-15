<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAssignment extends Model
{
    protected $fillable = ['user_id', 'location_id', 'is_approved'];

    protected $casts = ['is_approved' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
