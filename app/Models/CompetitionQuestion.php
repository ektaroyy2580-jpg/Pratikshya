<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A question slot in a competition with a server-clock window [opens_at_ms, closes_at_ms]. */
class CompetitionQuestion extends Model
{
    protected $fillable = [];

    protected $casts = ['opens_at_ms' => 'integer', 'closes_at_ms' => 'integer', 'position' => 'integer'];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function question()
    {
        return $this->belongsTo(GameQuestion::class, 'game_question_id');
    }

    public function answers()
    {
        return $this->hasMany(CompetitionAnswer::class);
    }
}
