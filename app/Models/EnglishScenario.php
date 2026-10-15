<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnglishScenario extends Model
{
    public const TYPES = [
        'conversation' => ['Conversation', 'bi-chat-heart'],
        'interview' => ['Interview', 'bi-briefcase'],
        'everyday' => ['Everyday', 'bi-cup-hot'],
        'pronunciation' => ['Pronunciation', 'bi-soundwave'],
        'speaking_prompt' => ['Speaking prompt', 'bi-easel'],
    ];

    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    protected $fillable = ['slug', 'type', 'level', 'title', 'description', 'icon', 'turns', 'target_vocabulary', 'is_active', 'sort'];

    protected $casts = [
        'turns' => 'array',
        'target_vocabulary' => 'array',
        'is_active' => 'boolean',
    ];

    public function sessions()
    {
        return $this->hasMany(EnglishPracticeSession::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst($this->type);
    }

    public function isPronunciation(): bool
    {
        return $this->type === 'pronunciation';
    }

    public function promptCount(): int
    {
        return count($this->turns ?? []);
    }
}
