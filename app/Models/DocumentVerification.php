<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVerification extends Model
{
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'staff_id', 'result', 'missing', 'reason', 'verified_at'];

    protected $casts = ['missing' => 'array', 'verified_at' => 'datetime'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
