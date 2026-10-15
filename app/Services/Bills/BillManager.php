<?php

namespace App\Services\Bills;

use App\Models\AuditLog;
use App\Models\BillAllocation;
use App\Models\BillParticipant;
use App\Models\BillPayment;
use App\Models\GroupBill;
use App\Models\GroupBillItem;
use App\Models\User;
use App\Notifications\Bills\BillActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * All state changes for group bills. Controllers authorize first; this class enforces business rules
 * (bill status, ownership of rows, amounts) and runs critical operations in transactions.
 * Bills never read or write queue tables.
 */
class BillManager
{
    public function __construct(private BillCalculator $calc) {}

    // ---------------- Create / update ----------------

    public function create(User $owner, array $data): GroupBill
    {
        return DB::transaction(function () use ($owner, $data) {
            $bill = new GroupBill;
            $bill->fill($this->settings($data));
            $bill->forceFill(['owner_id' => $owner->id, 'status' => 'open'])->save();

            $me = new BillParticipant(['display_name' => mb_substr($owner->name, 0, 60)]);
            $me->forceFill(['group_bill_id' => $bill->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'joined', 'joined_at' => now()])->save();

            $this->syncItems($bill, $data['items'] ?? []);
            $bill->rotateInviteToken();
            $this->recompute($bill);
            AuditLog::record('bill_created', $bill);

            return $bill;
        });
    }

    public function update(GroupBill $bill, array $data): GroupBill
    {
        $this->assertOpen($bill);

        return DB::transaction(function () use ($bill, $data) {
            $bill->fill($this->settings($data))->save();
            $this->syncItems($bill, $data['items'] ?? []);
            $this->recompute($bill);
            AuditLog::record('bill_updated', $bill);

            return $bill;
        });
    }

    private function settings(array $d): array
    {
        $type = $d['discount_type'] ?? 'none';

        return [
            'title' => $d['title'],
            'restaurant_name' => $d['restaurant_name'] ?? null,
            'discount_type' => $type,
            'discount_value' => $type === 'none' ? 0 : BillCalculator::toDecimal(BillCalculator::toPaisa($d['discount_value'] ?? 0)),
            'service_charge_percent' => BillCalculator::toDecimal(BillCalculator::toPaisa($d['service_charge_percent'] ?? 0)),
            'vat_percent' => BillCalculator::toDecimal(BillCalculator::toPaisa($d['vat_percent'] ?? 0)),
            'vat_on_service_charge' => (bool) ($d['vat_on_service_charge'] ?? false),
            'tip_amount' => BillCalculator::toDecimal(BillCalculator::toPaisa($d['tip_amount'] ?? 0)),
            'tip_allocation' => $d['tip_allocation'] ?? 'equal',
            'split_mode' => $d['split_mode'] ?? 'equal',
            'members_can_select' => (bool) ($d['members_can_select'] ?? false),
            'payment_note' => $d['payment_note'] ?? null,
        ];
    }

    /** Items carrying an `id` must belong to this bill (IDOR-safe); missing ones are deleted. */
    private function syncItems(GroupBill $bill, array $rows): void
    {
        $existing = $bill->items()->get()->keyBy('id');
        $keep = [];
        $everyone = $bill->joinedParticipants()->pluck('id')->all();
        foreach (array_values($rows) as $pos => $row) {
            $attrs = [
                'name' => trim($row['name']),
                'unit_price' => BillCalculator::toDecimal(BillCalculator::toPaisa($row['unit_price'])),
                'quantity' => (int) $row['quantity'],
                'is_shared' => (bool) ($row['is_shared'] ?? false),
                'position' => $pos,
            ];
            $id = isset($row['id']) ? (int) $row['id'] : null;
            if ($id && $existing->has($id)) {
                $item = $existing[$id];
                $wasShared = $item->is_shared;
                $item->update($attrs);
                if (! $wasShared && $item->is_shared && $item->allocations()->count() === 0) {
                    $this->assignToAll($bill, $item, $everyone);
                }
                if ($wasShared && ! $item->is_shared && $item->allocations()->count() > 1) {
                    $item->allocations()->delete(); // ambiguous now — owner reassigns
                }
            } else {
                $item = new GroupBillItem($attrs);
                $item->forceFill(['group_bill_id' => $bill->id])->save();
                if ($item->is_shared) {
                    $this->assignToAll($bill, $item, $everyone);
                }
            }
            $keep[] = $item->id;
        }
        $bill->items()->whereNotIn('id', $keep)->get()->each->delete();
    }

    private function assignToAll(GroupBill $bill, GroupBillItem $item, array $participantIds): void
    {
        foreach ($participantIds as $pid) {
            $a = BillAllocation::where('group_bill_item_id', $item->id)->where('bill_participant_id', $pid)->first() ?? new BillAllocation;
            $a->forceFill(['group_bill_id' => $bill->id, 'group_bill_item_id' => $item->id, 'bill_participant_id' => $pid, 'weight' => $a->weight ?: 1])->save();
        }
    }

    /** Refreshes cached subtotal/total columns from the engine (the engine stays the source of truth). */
    public function recompute(GroupBill $bill): array
    {
        $bill->unsetRelation('items')->unsetRelation('joinedParticipants');
        $r = $this->calc->forBill($bill);
        $bill->forceFill([
            'subtotal' => BillCalculator::toDecimal($r['totals']['subtotal']),
            'total' => BillCalculator::toDecimal($r['totals']['grand_total']),
        ])->save();

        return $r;
    }

    // ---------------- Assignments ----------------

    /**
     * Owner saves the whole assignment matrix: [itemId => [participantId => weight]].
     * Unknown item/participant ids are rejected (never trusted).
     */
    public function setAllocations(GroupBill $bill, array $matrix): void
    {
        $this->assertOpen($bill);
        $items = $bill->items()->get()->keyBy('id');
        $pids = $bill->joinedParticipants()->pluck('id')->all();
        $clean = [];
        foreach ($matrix as $itemId => $row) {
            if (! $items->has((int) $itemId) || ! is_array($row)) {
                throw ValidationException::withMessages(['allocations' => 'That item is not part of this bill.']);
            }
            foreach ($row as $pid => $weight) {
                if (! in_array((int) $pid, $pids, true)) {
                    throw ValidationException::withMessages(['allocations' => 'That person is not a participant of this bill.']);
                }
                $weight = (int) $weight;
                if ($weight < 0 || $weight > 20) {
                    throw ValidationException::withMessages(['allocations' => 'Share weights must be between 0 and 20.']);
                }
                if ($weight > 0) {
                    $clean[(int) $itemId][(int) $pid] = $weight;
                }
            }
            $item = $items[(int) $itemId];
            if (! $item->is_shared && count($clean[(int) $itemId] ?? []) > 1) {
                throw ValidationException::withMessages(['allocations' => '"'.$item->name.'" is not shared — assign it to one person, or mark it shared.']);
            }
        }

        DB::transaction(function () use ($bill, $items, $clean) {
            BillAllocation::where('group_bill_id', $bill->id)->whereIn('group_bill_item_id', $items->keys())->delete();
            foreach ($clean as $itemId => $row) {
                foreach ($row as $pid => $w) {
                    $a = new BillAllocation(['weight' => $w]);
                    $a->forceFill(['group_bill_id' => $bill->id, 'group_bill_item_id' => $itemId, 'bill_participant_id' => $pid])->save();
                }
            }
            $this->recompute($bill);
            AuditLog::record('bill_allocations_saved', $bill);
        });
    }

    /** A member ticks/unticks an item for themselves (only if the owner allows it and the bill is open). */
    public function toggleMine(GroupBill $bill, BillParticipant $me, GroupBillItem $item, bool $on, int $weight = 1): void
    {
        $this->assertOpen($bill);
        if ((int) $item->group_bill_id !== (int) $bill->id || (int) $me->group_bill_id !== (int) $bill->id) {
            abort(404);
        }
        if (! $me->isOwner() && ! $bill->members_can_select) {
            throw ValidationException::withMessages(['allocations' => 'The bill owner has locked item selection.']);
        }
        $weight = max(1, min(20, $weight));
        DB::transaction(function () use ($bill, $me, $item, $on, $weight) {
            $mine = BillAllocation::where('group_bill_item_id', $item->id)->where('bill_participant_id', $me->id)->first();
            if (! $on) {
                $mine?->delete();
            } else {
                if (! $item->is_shared) {
                    $other = BillAllocation::where('group_bill_item_id', $item->id)->where('bill_participant_id', '!=', $me->id)->with('participant')->first();
                    if ($other) {
                        throw ValidationException::withMessages(['allocations' => '"'.$item->name.'" is already picked by '.$other->participant->display_name.'. Ask the owner to mark it shared.']);
                    }
                    $weight = 1;
                }
                $mine ??= new BillAllocation;
                $mine->forceFill(['group_bill_id' => $bill->id, 'group_bill_item_id' => $item->id, 'bill_participant_id' => $me->id, 'weight' => $weight])->save();
            }
            $this->recompute($bill);
        });
    }

    /** Owner sets custom amounts / custom tip shares per participant (decimal strings keyed by participant id). */
    public function setCustom(GroupBill $bill, array $amounts, array $tips): void
    {
        $this->assertOpen($bill);
        $people = $bill->joinedParticipants()->get()->keyBy('id');
        foreach (array_keys($amounts + $tips) as $pid) {
            if (! $people->has((int) $pid)) {
                throw ValidationException::withMessages(['custom' => 'That person is not a participant of this bill.']);
            }
        }
        DB::transaction(function () use ($bill, $people, $amounts, $tips) {
            foreach ($people as $pid => $p) {
                $fill = [];
                if (array_key_exists($pid, $amounts)) {
                    $fill['custom_amount'] = $amounts[$pid] === null || $amounts[$pid] === '' ? null : BillCalculator::toDecimal(BillCalculator::toPaisa($amounts[$pid]));
                }
                if (array_key_exists($pid, $tips)) {
                    $fill['custom_tip'] = $tips[$pid] === null || $tips[$pid] === '' ? null : BillCalculator::toDecimal(BillCalculator::toPaisa($tips[$pid]));
                }
                if ($fill) {
                    $p->forceFill($fill)->save();
                }
            }
            $this->recompute($bill);
        });
    }

    // ---------------- Participants & invitations ----------------

    public function join(GroupBill $bill, User $user): BillParticipant
    {
        if ($bill->status !== 'open') {
            throw ValidationException::withMessages(['join' => 'This bill is no longer accepting new people.']);
        }

        return DB::transaction(function () use ($bill, $user) {
            $p = BillParticipant::where('group_bill_id', $bill->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($p && $p->status === 'joined') {
                return $p;
            }
            if ($bill->joinedParticipants()->count() >= (int) config('bills.max_participants', 30)) {
                throw ValidationException::withMessages(['join' => 'This bill already has the maximum number of people.']);
            }
            $p ??= new BillParticipant(['display_name' => mb_substr($user->name, 0, 60)]);
            $p->forceFill(['group_bill_id' => $bill->id, 'user_id' => $user->id, 'role' => 'member', 'status' => 'joined', 'joined_at' => now()])->save();

            // New people are automatically included in shared items.
            foreach ($bill->items()->where('is_shared', true)->get() as $item) {
                $this->assignToAll($bill, $item, [$p->id]);
            }
            $this->recompute($bill);
            AuditLog::record('bill_joined', $bill, ['participant_id' => $p->id]);
            if (! $bill->isOwner($user)) {
                $bill->owner?->notify(new BillActivity('member_joined', $bill, $p->display_name.' joined "'.$bill->title.'".'));
            }

            return $p;
        });
    }

    /**
     * Authenticated invite by email. Returns nothing on purpose: the caller must give the same response whether
     * or not the email belongs to an account (no account enumeration).
     */
    public function inviteByEmail(GroupBill $bill, User $inviter, string $email): void
    {
        $this->assertOpen($bill);
        $user = User::where('email', mb_strtolower(trim($email)))->first();
        if (! $user || $user->id === $inviter->id) {
            AuditLog::record('bill_invite_email', $bill, ['matched' => false]);

            return;
        }
        $existing = BillParticipant::where('group_bill_id', $bill->id)->where('user_id', $user->id)->first();
        if ($existing) {
            return; // already invited or joined — silently do nothing
        }
        $p = new BillParticipant(['display_name' => mb_substr($user->name, 0, 60)]);
        $p->forceFill(['group_bill_id' => $bill->id, 'user_id' => $user->id, 'role' => 'member', 'status' => 'invited', 'invited_by' => $inviter->id])->save();
        $user->notify(new BillActivity('invited', $bill, $inviter->name.' invited you to split "'.$bill->title.'". Open Bills to accept.'));
        AuditLog::record('bill_invite_email', $bill, ['matched' => true]);
    }

    public function acceptInvitation(BillParticipant $invite, User $user): BillParticipant
    {
        if ((int) $invite->user_id !== (int) $user->id || $invite->status !== 'invited') {
            abort(404);
        }

        return $this->join($invite->bill, $user);
    }

    public function declineInvitation(BillParticipant $invite, User $user): void
    {
        if ((int) $invite->user_id !== (int) $user->id || $invite->status !== 'invited') {
            abort(404);
        }
        $invite->delete();
    }

    public function removeParticipant(GroupBill $bill, BillParticipant $p): void
    {
        $this->assertOpen($bill);
        if ((int) $p->group_bill_id !== (int) $bill->id) {
            abort(404);
        }
        if ($p->isOwner()) {
            throw ValidationException::withMessages(['participants' => 'The owner cannot be removed.']);
        }
        if ($p->payments()->exists()) {
            throw ValidationException::withMessages(['participants' => 'This person already has payment records.']);
        }
        DB::transaction(function () use ($bill, $p) {
            $p->delete();
            $this->recompute($bill);
            AuditLog::record('bill_participant_removed', $bill, ['participant_id' => $p->id]);
        });
    }

    // ---------------- Status ----------------

    public function transition(GroupBill $bill, string $to): void
    {
        $from = $bill->status;
        $ok = match ([$from, $to]) {
            ['open', 'settling'] => true,
            ['settling', 'open'] => true,
            ['open', 'archived'], ['settling', 'archived'], ['settled', 'archived'] => true,
            default => false,
        };
        if (! $ok) {
            throw ValidationException::withMessages(['status' => 'That status change is not allowed.']);
        }
        if ($to === 'settling') {
            $r = $this->recompute($bill);
            if (! $r['verification']['ok']) {
                throw ValidationException::withMessages(['status' => 'Fix the verification issues before starting settlement.']);
            }
            if (collect($r['items'])->contains(fn ($i) => ! $i['assigned'] && $i['total'] > 0)) {
                throw ValidationException::withMessages(['status' => 'Assign every item to someone before starting settlement.']);
            }
            if ($bill->joinedParticipants()->count() < 2) {
                throw ValidationException::withMessages(['status' => 'Invite at least one person before settling.']);
            }
        }
        if ($from === 'settling' && $to === 'open' && $bill->payments()->whereIn('status', ['claimed', 'confirmed_by_owner'])->exists()) {
            throw ValidationException::withMessages(['status' => 'Payments are already recorded — the amounts are locked.']);
        }
        $bill->forceFill(['status' => $to])->save();
        AuditLog::record('bill_status', $bill, ['from' => $from, 'to' => $to]);
    }

    // ---------------- Settlement ----------------

    /**
     * Per-participant balances. The owner paid the restaurant, so the owner's own share is "covered";
     * everyone else owes the owner their share. Only owner-confirmed payments reduce the balance.
     */
    public function balances(GroupBill $bill, ?array $calc = null): array
    {
        $calc ??= $this->calc->forBill($bill);
        $payments = $bill->payments()->get()->groupBy('bill_participant_id');
        $rows = [];
        $toCollect = 0;
        $collected = 0;
        $pending = 0;
        foreach ($calc['people'] as $pid => $row) {
            $ps = $payments->get($pid, collect());
            $confirmed = $ps->where('status', 'confirmed_by_owner')->sum(fn ($p) => BillCalculator::toPaisa($p->amount));
            $claimed = $ps->where('status', 'claimed')->sum(fn ($p) => BillCalculator::toPaisa($p->amount));
            $p = $bill->joinedParticipants->firstWhere('id', $pid);
            $isOwner = $p?->isOwner() ?? false;
            $share = $row['total'];
            $remaining = $isOwner ? 0 : max(0, $share - $confirmed);
            if (! $isOwner) {
                $toCollect += $share;
                $collected += min($share, $confirmed);
                $pending += $claimed;
            }
            $rows[$pid] = ['share' => $share, 'confirmed' => $confirmed, 'claimed' => $claimed, 'remaining' => $remaining,
                'claimable' => $isOwner ? 0 : max(0, $share - $confirmed - $claimed), 'is_owner' => $isOwner,
                'state' => $isOwner ? 'covered' : ($remaining === 0 ? 'paid' : ($claimed > 0 ? 'claimed' : 'due'))];
        }

        return ['rows' => $rows, 'to_collect' => $toCollect, 'collected' => $collected, 'pending' => $pending,
            'remaining' => max(0, $toCollect - $collected), 'progress' => $toCollect > 0 ? (int) floor($collected * 100 / $toCollect) : 0];
    }

    /** "I paid" — creates a CLAIM only. It never reduces the balance until the owner confirms. */
    public function claimPayment(GroupBill $bill, BillParticipant $me, array $data): BillPayment
    {
        if ($bill->status !== 'settling') {
            throw ValidationException::withMessages(['payment' => 'Payments can be recorded once the owner starts settlement.']);
        }
        if ($me->isOwner()) {
            throw ValidationException::withMessages(['payment' => 'As the owner you paid the restaurant — your share is already covered.']);
        }

        return DB::transaction(function () use ($bill, $me, $data) {
            BillParticipant::whereKey($me->id)->lockForUpdate()->first();
            $bal = $this->balances($bill)['rows'][$me->id] ?? null;
            $amount = BillCalculator::toPaisa($data['amount']);
            if (! $bal || $amount <= 0 || $amount > $bal['claimable']) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount up to NPR '.BillCalculator::money($bal['claimable'] ?? 0).'.']);
            }
            $pay = new BillPayment(['amount' => BillCalculator::toDecimal($amount), 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null]);
            $pay->forceFill(['group_bill_id' => $bill->id, 'bill_participant_id' => $me->id, 'status' => 'claimed'])->save();
            AuditLog::record('bill_payment_claimed', $bill, ['payment_id' => $pay->id]);
            $bill->owner?->notify(new BillActivity('payment_claimed', $bill, $me->display_name.' says they paid NPR '.BillCalculator::money($amount).' for "'.$bill->title.'". Confirm when you receive it.'));

            return $pay;
        });
    }

    public function confirmPayment(GroupBill $bill, BillPayment $payment, User $owner): void
    {
        $this->assertPaymentOfBill($bill, $payment);
        if (! in_array($payment->status, ['claimed', 'disputed'], true)) {
            throw ValidationException::withMessages(['payment' => 'This payment is already confirmed.']);
        }
        DB::transaction(function () use ($bill, $payment, $owner) {
            $payment->forceFill(['status' => 'confirmed_by_owner', 'confirmed_by' => $owner->id, 'confirmed_at' => now()])->save();
            AuditLog::record('bill_payment_confirmed', $bill, ['payment_id' => $payment->id]);
            $payment->participant->user?->notify(new BillActivity('payment_confirmed', $bill, $bill->owner->name.' confirmed your NPR '.BillCalculator::money(BillCalculator::toPaisa($payment->amount)).' payment for "'.$bill->title.'".'));
            $this->maybeSettle($bill);
        });
    }

    public function disputePayment(GroupBill $bill, BillPayment $payment): void
    {
        $this->assertPaymentOfBill($bill, $payment);
        if ($payment->status !== 'claimed') {
            throw ValidationException::withMessages(['payment' => 'Only unconfirmed claims can be disputed.']);
        }
        $payment->forceFill(['status' => 'disputed'])->save();
        AuditLog::record('bill_payment_disputed', $bill, ['payment_id' => $payment->id]);
        $payment->participant->user?->notify(new BillActivity('payment_disputed', $bill, $bill->owner->name.' has not received your NPR '.BillCalculator::money(BillCalculator::toPaisa($payment->amount)).' payment for "'.$bill->title.'" yet.'));
    }

    private function maybeSettle(GroupBill $bill): void
    {
        $bill->refresh();
        if ($bill->status !== 'settling') {
            return;
        }
        $b = $this->balances($bill);
        if ($b['to_collect'] > 0 && $b['remaining'] === 0) {
            $bill->forceFill(['status' => 'settled', 'settled_at' => now()])->save();
            AuditLog::record('bill_settled', $bill);
        }
    }

    private function assertPaymentOfBill(GroupBill $bill, BillPayment $payment): void
    {
        if ((int) $payment->group_bill_id !== (int) $bill->id) {
            abort(404);
        }
        if (! in_array($bill->status, ['settling', 'settled'], true)) {
            throw ValidationException::withMessages(['payment' => 'This bill is not being settled.']);
        }
    }

    private function assertOpen(GroupBill $bill): void
    {
        if (! $bill->isEditable()) {
            throw ValidationException::withMessages(['bill' => 'This bill is locked for settlement. Reopen it to make changes.']);
        }
    }
}
