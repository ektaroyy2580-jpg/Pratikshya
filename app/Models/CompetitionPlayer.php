<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A participant. Others only ever see `token_label` (e.g. "D-24"), never names or emails. */
class CompetitionPlayer extends Model
{
    /** Still taking part (lobby or game). */
    public const ACTIVE = ['joined', 'ready', 'playing'];

    // Everything here is server-controlled (score, rank, status) — set via forceFill only.
    protected $fillable = [];

    protected $casts = [
        'score' => 'integer', 'correct_count' => 'integer', 'total_response_ms' => 'integer',
        'rank' => 'integer', 'reward_points' => 'integer',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}
