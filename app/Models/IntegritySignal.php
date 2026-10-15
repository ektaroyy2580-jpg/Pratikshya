<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A minimal queue-integrity observation (Dalal AI input). No raw IPs, no free text
 * from users: only types, ids, counts, a salted network hash and a coarse UA family.
 */
class IntegritySignal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'user_id', 'actor_id', 'ticket_id', 'service_id', 'location_id', 'actor_role', 'network_hash', 'ua_family', 'meta'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public const LABELS = [
        'joined' => 'Joined a queue',
        'duplicate_join' => 'Tried to take a second token for the same service',
        'idempotent_retry' => 'Browser retry of the same join (handled safely)',
        'left' => 'Left the queue',
        'rejected_transition' => 'Status change rejected by the queue rules',
        'rule_violation' => 'Action blocked by a queue rule',
        'staff_call_next' => 'Staff called the next token',
        'staff_absent' => 'Staff marked a token absent',
        'staff_cancel' => 'Staff cancelled a token',
        'staff_move' => 'Staff moved a token to another counter',
        'staff_report' => 'Staff reported a duplicate-token suspicion',
        'exchange_requested' => 'Requested a queue exchange',
        'exchange_completed' => 'Queue exchange completed',
        'exchange_declined' => 'Queue exchange declined',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function label(): string
    {
        $base = self::LABELS[$this->type] ?? str_replace('_', ' ', ucfirst($this->type));
        if ($this->type === 'rule_violation' && ($a = $this->meta['rule'] ?? null)) {
            $base .= ': '.str_replace('_', ' ', $a);
        }

        return $base;
    }
}
