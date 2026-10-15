<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One solo-game play. `payload` holds server-only data (answers, puzzle solution, deck)
 * and is hidden from serialization so it can never leak into a JSON response.
 */
class ArcadeSession extends Model
{
    protected $fillable = ['user_id', 'game_key', 'variant', 'payload', 'started_at'];

    protected $hidden = ['payload'];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'verified' => 'boolean',
        'score' => 'integer',
        'points_awarded' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->finished_at === null;
    }

    public function elapsedMs(): int
    {
        return (int) max(0, $this->started_at->diffInMilliseconds(now()));
    }
}
