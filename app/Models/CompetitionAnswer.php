<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One answer per question per player (DB unique constraint). Correctness and points are computed server-side. */
class CompetitionAnswer extends Model
{
    protected $fillable = [];

    protected $casts = ['is_correct' => 'boolean', 'choice' => 'integer', 'points' => 'integer', 'response_ms' => 'integer'];

    public function competitionQuestion()
    {
        return $this->belongsTo(CompetitionQuestion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
