<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GroupBill extends Model
{
    public const STATUSES = ['open', 'settling', 'settled', 'archived'];
    public const SPLIT_MODES = ['equal', 'itemized', 'custom'];
    public const TIP_ALLOCATIONS = ['equal', 'proportional', 'custom'];
    public const DISCOUNT_TYPES = ['none', 'amount', 'percent'];

    // owner_id, status, cached totals and invite fields are set explicitly by the controller/services only.
    protected $fillable = [
        'title', 'restaurant_name', 'discount_type', 'discount_value', 'service_charge_percent', 'vat_percent',
        'vat_on_service_charge', 'tip_amount', 'tip_allocation', 'split_mode', 'members_can_select', 'payment_note',
    ];

    protected $hidden = ['invite_token_hash', 'invite_token_encrypted'];

    protected $attributes = ['currency' => 'NPR', 'status' => 'open'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'discount_value' => 'decimal:2', 'tip_amount' => 'decimal:2',
            'service_charge_percent' => 'decimal:2', 'vat_percent' => 'decimal:2',
            'vat_on_service_charge' => 'boolean', 'members_can_select' => 'boolean',
            'invite_token_encrypted' => 'encrypted',
            'invite_expires_at' => 'datetime', 'invite_revoked_at' => 'datetime', 'settled_at' => 'datetime',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Café the bill was split at (set by the café page "Split a bill here"; never mass-assigned). */
    public function nearbyPlace()
    {
        return $this->belongsTo(NearbyPlace::class);
    }

    /** The owner's own preorder that prefilled the items (owner-scoped when linked). */
    public function foodOrder()
    {
        return $this->belongsTo(FoodOrder::class);
    }

    public function items()
    {
        return $this->hasMany(GroupBillItem::class)->orderBy('position')->orderBy('id');
    }

    public function participants()
    {
        return $this->hasMany(BillParticipant::class)->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")->orderBy('id');
    }

    public function joinedParticipants()
    {
        return $this->participants()->where('status', 'joined');
    }

    public function allocations()
    {
        return $this->hasMany(BillAllocation::class);
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class)->latest('id');
    }

    /** Joined participant row for this user, or null. Every bill lookup is scoped through this. */
    public function participantFor(?User $user): ?BillParticipant
    {
        if (! $user) {
            return null;
        }

        return $this->participants()->where('user_id', $user->id)->where('status', 'joined')->first();
    }

    public function isOwner(?User $user): bool
    {
        return $user !== null && (int) $this->owner_id === (int) $user->id;
    }

    public function isEditable(): bool
    {
        return $this->status === 'open';
    }

    // ---------------- Invite link ----------------

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Creates a fresh token (invalidating the previous one) and returns the plain value. */
    public function rotateInviteToken(?int $ttlDays = null): string
    {
        $token = Str::random(40);
        $this->forceFill([
            'invite_token_hash' => self::hashToken($token),
            'invite_token_encrypted' => $token,
            'invite_expires_at' => now()->addDays($ttlDays ?? (int) config('bills.invite_ttl_days', 7)),
            'invite_revoked_at' => null,
        ])->save();

        return $token;
    }

    public function revokeInviteToken(): void
    {
        $this->forceFill(['invite_token_hash' => null, 'invite_token_encrypted' => null, 'invite_revoked_at' => now()])->save();
    }

    public function inviteIsActive(): bool
    {
        return $this->invite_token_hash !== null && $this->invite_revoked_at === null
            && $this->invite_expires_at !== null && $this->invite_expires_at->isFuture();
    }

    /** Plain token for the owner's share panel (null when revoked/expired). */
    public function plainInviteToken(): ?string
    {
        return $this->inviteIsActive() ? $this->invite_token_encrypted : null;
    }

    /** Finds a bill by a still-valid invite token (lookup by sha256 hash; plain tokens are never queried). */
    public static function findByInviteToken(string $token): ?self
    {
        if (strlen($token) !== 40 || ! ctype_alnum($token)) {
            return null;
        }
        $bill = static::where('invite_token_hash', self::hashToken($token))->first();

        return $bill && $bill->inviteIsActive() ? $bill : null;
    }
}
