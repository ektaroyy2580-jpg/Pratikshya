<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A multiplayer Quick Brain Battle scoped to one service (queue).
 * Lifecycle: waiting_for_players → ready → starting → in_progress → completed / cancelled.
 * All state changes go through App\Services\Games\CompetitionService.
 */
class Competition extends Model
{
    public const LOBBY = ['waiting_for_players', 'ready'];

    public const LIVE = ['waiting_for_players', 'ready', 'starting', 'in_progress'];

    public const FINISHED = ['completed', 'cancelled'];

    public const MIN_PLAYERS = 2;

    public const MAX_PLAYERS = 4;

    // Status/timing columns are only set by CompetitionService via forceFill.
    protected $fillable = ['category', 'visibility', 'max_players', 'question_count', 'seconds_per_question'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'rewards_processed' => 'boolean',
        'starts_at_ms' => 'integer',
        'max_players' => 'integer',
        'question_count' => 'integer',
        'seconds_per_question' => 'integer',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players()
    {
        return $this->hasMany(CompetitionPlayer::class);
    }

    public function questions()
    {
        return $this->hasMany(CompetitionQuestion::class)->orderBy('position');
    }

    public function answers()
    {
        return $this->hasMany(CompetitionAnswer::class);
    }

    public function invitations()
    {
        return $this->hasMany(CompetitionInvitation::class);
    }

    public function playerFor(?User $user): ?CompetitionPlayer
    {
        if (! $user) {
            return null;
        }

        return $this->relationLoaded('players')
            ? $this->players->firstWhere('user_id', $user->id)
            : $this->players()->where('user_id', $user->id)->first();
    }

    public function isLobby(): bool
    {
        return in_array($this->status, self::LOBBY, true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    public function scopeLive($q)
    {
        return $q->whereIn('status', self::LIVE);
    }

    public function statusLabel(): string
    {
        return [
            'waiting_for_players' => 'Waiting for players',
            'ready' => 'Ready to start',
            'starting' => 'Starting',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function categoryLabel(): string
    {
        return $this->category ? (GameQuestion::CATEGORIES[$this->category] ?? ucfirst($this->category)) : 'Mixed topics';
    }
}
