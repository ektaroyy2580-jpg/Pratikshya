<?php

namespace App\Models;

use App\Services\Queue\WaitTimeEstimator;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    public const ACTIVE = ['waiting', 'called', 'serving'];

    public const INACTIVE = ['cancelled', 'absent', 'left'];

    /** Allowed state machine transitions. */
    public const TRANSITIONS = [
        'waiting' => ['called', 'cancelled', 'left', 'absent'],
        'called' => ['serving', 'absent', 'cancelled', 'waiting'],
        'serving' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'absent' => [],
        'left' => [],
    ];

    // Queue-integrity columns (number, status, active_key, counter, user) are only set by QueueManager.
    protected $fillable = ['name', 'docs_ready', 'doc_checklist'];

    protected $casts = [
        'docs_ready' => 'boolean',
        'docs_verified' => 'boolean',
        'doc_checklist' => 'array',
        'called_at' => 'datetime',
        'serving_at' => 'datetime',
        'completed_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function events()
    {
        return $this->hasMany(QueueEvent::class)->latest('id');
    }

    public function verifications()
    {
        return $this->hasMany(DocumentVerification::class)->latest('id');
    }

    /** Voluntary exchange requests this entry sent (integrity module). */
    public function exchangeRequestsOut()
    {
        return $this->hasMany(QueueExchangeRequest::class, 'requester_ticket_id')->latest('id');
    }

    /** Voluntary exchange requests this entry received (integrity module). */
    public function exchangeRequestsIn()
    {
        return $this->hasMany(QueueExchangeRequest::class, 'target_ticket_id')->latest('id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function token(): string
    {
        return $this->service->token($this->number);
    }

    public function counterNumber(): int
    {
        return $this->counter?->number ?? $this->service->counter;
    }

    public function ahead(): int
    {
        if ($this->status !== 'waiting') {
            return 0;
        }
        $s = $this->service;
        $dropped = $s->tickets()
            ->whereIn('status', self::INACTIVE)
            ->where('number', '>', $s->current_number)
            ->where('number', '<', $this->number)
            ->count();

        return max(0, $this->number - $s->current_number - 1 - $dropped);
    }

    /** Document readiness: [ready, total, percent]. */
    public function documentReadiness(): array
    {
        $required = $this->service->documents ?? [];
        $list = $this->doc_checklist ?? [];
        $ready = collect($required)->filter(fn ($d) => ($list[$d] ?? null) === 'available')->count();
        $total = count($required);

        return [$ready, $total, $total ? (int) round($ready / $total * 100) : 100];
    }

    public function stateArray(): array
    {
        $s = $this->service;
        $ahead = $this->ahead();
        $est = app(WaitTimeEstimator::class)->forPeopleAhead($s, $ahead);
        $remaining = $this->status === 'waiting' ? $est['minutes'] : 0;

        return [
            'id' => $this->id,
            'token' => $this->token(),
            'status' => $this->status,
            'current_token' => $s->token($s->current_number),
            'counter' => $this->counterNumber(),
            'ahead' => $ahead,
            'waiting' => $s->waitingCount(),
            'wait_minutes' => $remaining,
            'wait_low' => $this->status === 'waiting' ? $est['low'] : 0,
            'wait_high' => $this->status === 'waiting' ? $est['high'] : 0,
            'wait_explanation' => $est['explanation'],
            'wait_confidence' => $est['confidence'],
            'queue_paused' => (bool) $s->is_paused,
            'initial_wait' => $this->initial_wait,
            'progress' => $this->initial_wait > 0
                ? max(0, min(100, (int) round(100 - $remaining / $this->initial_wait * 100)))
                : 100,
            'docs_verified' => (bool) $this->docs_verified,
            'game_points' => $this->game_points,
            'total_points' => 50 + $this->game_points,
        ];
    }
}
