<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A settlement record. A participant clicking "I paid" only creates a `claimed` row.
 * Only the bill owner can move it to `confirmed_by_owner` (or `disputed`). No payment-provider
 * callback is implemented, so nothing is ever confirmed automatically.
 */
class BillPayment extends Model
{
    public const METHODS = ['demo', 'cash', 'esewa_link', 'khalti_link'];
    public const METHOD_LABELS = ['demo' => 'Demo payment', 'cash' => 'Cash', 'esewa_link' => 'eSewa (outside app)', 'khalti_link' => 'Khalti (outside app)'];
    public const STATUSES = ['claimed', 'confirmed_by_owner', 'disputed'];

    protected $fillable = ['amount', 'method', 'reference', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'confirmed_at' => 'datetime'];
    }

    public function bill()
    {
        return $this->belongsTo(GroupBill::class, 'group_bill_id');
    }

    public function participant()
    {
        return $this->belongsTo(BillParticipant::class, 'bill_participant_id');
    }

    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->method] ?? $this->method;
    }
}
