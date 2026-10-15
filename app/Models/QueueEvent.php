<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'actor_id', 'type', 'from_status', 'to_status', 'meta'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
