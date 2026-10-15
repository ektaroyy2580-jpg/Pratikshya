<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodOrder extends Model
{
    public const STATUSES = ['placed', 'accepted', 'preparing', 'ready', 'collected', 'cancelled', 'rejected'];

    public const OPEN = ['placed', 'accepted', 'preparing', 'ready'];

    public const STEPS = ['placed', 'accepted', 'preparing', 'ready', 'collected'];

    /** Merchant-driven transitions. Users may only cancel while `placed` (see userCanCancel). */
    public const TRANSITIONS = [
        'placed' => ['accepted', 'rejected', 'cancelled'],
        'accepted' => ['preparing', 'ready'],
        'preparing' => ['ready'],
        'ready' => ['collected'],
        'collected' => [],
        'cancelled' => [],
        'rejected' => [],
    ];

    public const PAYMENT_MODES = ['demo', 'cash_on_pickup'];

    // Status, money, payment and pickup code are only written by PreorderService (forceFill).
    protected $fillable = ['special_instructions'];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'vat' => 'decimal:2',
        'total' => 'decimal:2',
        'timing_snapshot' => 'array',
        'conflict_acknowledged' => 'boolean',
        'estimated_ready_at' => 'datetime',
        'accepted_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'collected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'prep_minutes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function items()
    {
        return $this->hasMany(FoodOrderItem::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Free cancellation only before the café accepts. */
    public function userCanCancel(): bool
    {
        return $this->status === 'placed';
    }

    public function reference(): string
    {
        return 'PO-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return [
            'placed' => 'Waiting for the café to accept',
            'accepted' => 'Accepted by the café',
            'preparing' => 'Being prepared',
            'ready' => 'Ready for pickup',
            'collected' => 'Collected — enjoy!',
            'cancelled' => 'Cancelled',
            'rejected' => 'Declined by the café',
        ][$this->status] ?? ucfirst($this->status);
    }

    /** Progress step index for the tracker (0..4), or null when the order ended early. */
    public function step(): ?int
    {
        $i = array_search($this->status, self::STEPS, true);

        return $i === false ? null : $i;
    }

    public function minutesToReady(): ?int
    {
        if (! $this->estimated_ready_at || ! in_array($this->status, ['placed', 'accepted', 'preparing'], true)) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInSeconds($this->estimated_ready_at, false) / 60));
    }

    public function stateArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'step' => $this->step(),
            'is_open' => $this->isOpen(),
            'can_cancel' => $this->userCanCancel(),
            'payment_mode' => $this->payment_mode,
            'payment_status' => $this->payment_status,
            'estimated_ready_at' => $this->estimated_ready_at?->toIso8601String(),
            'estimated_ready_label' => $this->estimated_ready_at?->format('g:i A'),
            'minutes_to_ready' => $this->minutesToReady(),
            'pickup_code' => in_array($this->status, ['accepted', 'preparing', 'ready'], true) ? $this->pickup_code : null,
            'status_reason' => $this->status_reason,
            'total' => $this->total,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
