<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Official (here: demo) hospital information shown on the hospital assistance page. */
class HospitalProfile extends Model
{
    protected $fillable = [
        'location_id', 'departments', 'lab_services', 'assistance_desks', 'accessibility',
        'emergency_contacts', 'emergency_desk', 'official_source', 'is_demo',
    ];

    protected $casts = [
        'departments' => 'array',
        'lab_services' => 'array',
        'assistance_desks' => 'array',
        'accessibility' => 'array',
        'emergency_contacts' => 'array',
        'is_demo' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
