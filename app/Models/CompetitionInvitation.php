<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Invitation to a co-waiting participant. Only the invitee may accept or decline. */
class CompetitionInvitation extends Model
{
    protected $fillable = [];

    protected $casts = ['responded_at' => 'datetime'];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee()
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
