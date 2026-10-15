<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillParticipant extends Model
{
    // group_bill_id, user_id, role, status and custom amounts are always set explicitly (forceFill) — never mass-assigned from input.
    protected $fillable = ['display_name'];

    protected function casts(): array
    {
        return ['custom_amount' => 'decimal:2', 'custom_tip' => 'decimal:2', 'joined_at' => 'datetime'];
    }

    public function bill()
    {
        return $this->belongsTo(GroupBill::class, 'group_bill_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function allocations()
    {
        return $this->hasMany(BillAllocation::class);
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->display_name)) ?: [];
        $i = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));

        return $i !== '' ? $i : '?';
    }
}
