<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnglishPracticeSession extends Model
{
    public const DURATIONS = [3, 5, 10];

    // user_id, status, plan, summary and scores are set by the server only (never mass-assigned from input).
    protected $fillable = ['english_scenario_id', 'ticket_id', 'duration_target_minutes', 'mode', 'consent_audio'];

    protected $attributes = ['status' => 'active', 'mode' => 'text', 'consent_audio' => false];

    protected $casts = [
        'consent_audio' => 'boolean',
        'plan' => 'array',
        'summary' => 'array',
        'scores' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_target_minutes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scenario()
    {
        return $this->belongsTo(EnglishScenario::class, 'english_scenario_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function turns()
    {
        return $this->hasMany(EnglishPracticeTurn::class)->orderBy('turn_index');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** The prompt (array) the learner should answer next, or null when the plan is finished. */
    public function nextPrompt(?int $answered = null): ?array
    {
        $answered ??= $this->turns()->count();
        $plan = $this->plan ?? [];
        if (! array_key_exists($answered, $plan)) {
            return null;
        }
        $turns = $this->scenario->turns ?? [];

        return $turns[$plan[$answered]] ?? null;
    }

    public function plannedCount(): int
    {
        return count($this->plan ?? []);
    }
}
