<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Voluntary swap of two waiting positions in the SAME service. Nothing is paid or
 * traded; positions change only once every required party has approved.
 */
class QueueExchangeRequest extends Model
{
    public const OPEN = ['pending', 'awaiting_staff'];

    public const STATUS_LABELS = [
        'pending' => 'Waiting for reply',
        'awaiting_staff' => 'Awaiting staff approval',
        'completed' => 'Exchanged',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'rejected' => 'Rejected by staff',
        'failed' => 'Could not complete',
    ];

    // Workflow columns are written only by ExchangeService (forceFill).
    protected $fillable = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function requesterTicket()
    {
        return $this->belongsTo(Ticket::class, 'requester_ticket_id');
    }

    public function targetTicket()
    {
        return $this->belongsTo(Ticket::class, 'target_ticket_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isExpired(): bool
    {
        return $this->isOpen() && $this->expires_at->isPast();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', self::OPEN);
    }
}
