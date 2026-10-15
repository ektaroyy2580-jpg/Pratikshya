<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Multiple-choice question. Only `approved` questions are ever served to players. */
class GameQuestion extends Model
{
    public const CATEGORIES = [
        'nepal_gk' => 'Nepal GK',
        'general' => 'General knowledge',
        'cybersecurity' => 'Cybersecurity',
        'logic' => 'Logic',
    ];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public const STATUSES = ['approved', 'pending', 'disabled'];

    protected $fillable = ['question', 'options', 'answer_index', 'explanation', 'category', 'difficulty', 'source', 'status', 'hash'];

    protected $casts = ['options' => 'array', 'answer_index' => 'integer'];

    protected $attributes = ['source' => 'bank', 'status' => 'approved'];

    public static function hashFor(string $question): string
    {
        return hash('sha256', Str::of($question)->lower()->squish()->replaceMatches('/[^\p{L}\p{N} ]/u', '')->value());
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }
}
