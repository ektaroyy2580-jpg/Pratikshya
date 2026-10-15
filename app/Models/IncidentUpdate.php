<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Internal timeline entry for an incident (created, status change, comment, assignment, published). */
class IncidentUpdate extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['operational_incident_id', 'author_id', 'type', 'from_status', 'to_status', 'body'];

    protected $casts = ['created_at' => 'datetime'];

    public function incident()
    {
        return $this->belongsTo(OperationalIncident::class, 'operational_incident_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
