<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnglishPracticeTurn extends Model
{
    public const UPDATED_AT = null;

    // There is deliberately no audio attribute: only text ever reaches the server.
    protected $fillable = ['turn_index', 'prompt', 'user_text', 'input_mode', 'elapsed_seconds', 'feedback'];

    protected $casts = [
        'feedback' => 'array',
        'elapsed_seconds' => 'integer',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(EnglishPracticeSession::class, 'english_practice_session_id');
    }
}
