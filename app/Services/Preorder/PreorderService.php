<?php

namespace App\Services\Preorder;

use App\Models\AuditLog;
use App\Models\FoodOrder;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantStaff;
use App\Models\User;
use App\Notifications\Preorder\FoodOrderUpdate;
use Illuminate\Support\Facades\DB;

/**
 * All preorder state changes go through here. This service never touches the
 * queue: tickets are only READ (for the timing check / context id).
 */
class PreorderService
{
    public function __construct(private OrderPricing $pricing, private TimingCheck $timing)
    {
    }

    /**
     * Validate a cart against the live menu and return priced lines.
     *
     * @param  array<int,int>  $qtyById  menu_item_id => qty
     * @param  array<int,string>  $notes
     */
    public function resolveLines(Restaurant $restaurant, array $qtyById, array $notes = []): array
    {
        if (! $restaurant->accepts_preorders) {
            throw new PreorderException("{$restaurant->name} isn't taking preorders right now.", 'restaurant');
        }
        $qtyById = array_filter(array_map('intval', $qtyById), fn ($q) => $q > 0);
        if (! $qtyById) {
            throw new PreorderException('Your cart is empty.', 'cart');
        }
        $max = (int) config('preorder.max_qty_per_item', 20);
        if (max($qtyById) > $max || array_sum($qtyById) > (int) config('preorder.max_items_per_order', 40)) {
            throw new PreorderException('Quantity is above the per-order limit.', 'qty');
        }
        $items = MenuItem::where('restaurant_id', $restaurant->id)->whereIn('id', array_keys($qtyById))->get()->keyBy('id');
        $lines = [];
        foreach ($qtyById as $id => $qty) {
            $item = $items->get($id);
            if (! $item) {
                throw new PreorderException('An item in your cart is not on this café\'s menu any more.', 'cart');
            }
            if (! $item->available) {
                throw new PreorderException("Sorry — {$item->name} is unavailable right now. Remove it to continue.", 'cart');
            }
            $lines[] = [
                'item' => $item,
                'qty' => $qty,
                'note' => isset($notes[$id]) ? mb_substr(trim((string) $notes[$id]), 0, 200) : null,
                'unit_paisa' => $item->pricePaisa(),
                'prep_minutes' => (int) $item->prep_minutes,
            ];
        }

        return $lines;
    }

    /** Timing preview for the checkout page (read-only). */
    public function preview(User $user, Restaurant $restaurant, array $lines): array
    {
        return $this->timing->evaluate($restaurant, $lines, $this->timing->contextTicket($user));
    }

    /**
     * Place an order. Re-checks everything server-side (participation, availability,
     * prices, timing). A timing conflict requires $acknowledgeConflict.
     */
    public function place(
        User $user,
        Restaurant $restaurant,
        array $qtyById,
        array $notes,
        ?string $instructions,
        string $paymentMode,
        bool $acknowledgeConflict,
        ?string $idempotencyKey = null,
    ): FoodOrder {
        if ($idempotencyKey && ($existing = FoodOrder::where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        if (! in_array($paymentMode, FoodOrder::PAYMENT_MODES, true)) {
            throw new PreorderException('Choose demo payment or cash on pickup.', 'payment_mode');
        }

        $lines = $this->resolveLines($restaurant, $qtyById, $notes);
        $ticket = $this->timing->contextTicket($user);
        $timing = $this->timing->evaluate($restaurant, $lines, $ticket);
        if ($timing['needs_confirmation'] && ! $acknowledgeConflict) {
            throw new PreorderException('This order may not be ready before your turn. Tick the confirmation box to order anyway.', 'acknowledge_conflict');
        }
        $quote = $this->pricing->quote(array_map(fn ($l) => ['unit_paisa' => $l['unit_paisa'], 'qty' => $l['qty']], $lines));

        $order = DB::transaction(function () use ($user, $restaurant, $lines, $instructions, $paymentMode, $timing, $acknowledgeConflict, $quote, $ticket, $idempotencyKey) {
            $order = new FoodOrder(['special_instructions' => $instructions !== null ? mb_substr(trim($instructions), 0, 500) ?: null : null]);
            $order->forceFill([
                'user_id' => $user->id,
                'restaurant_id' => $restaurant->id,
                'ticket_id' => $ticket?->id,
                'status' => 'placed',
                'subtotal' => Money::fromPaisa($quote['subtotal']),
                'service_charge' => Money::fromPaisa($quote['service_charge']),
                'vat' => Money::fromPaisa($quote['vat']),
                'total' => Money::fromPaisa($quote['total']),
                'payment_mode' => $paymentMode,
                // Demo mode only ever records "demo_paid". "paid" requires a verified provider callback (not implemented).
                'payment_status' => $paymentMode === 'demo' ? 'demo_paid' : 'unpaid',
                'prep_minutes' => $timing['prep_minutes'],
                'estimated_ready_at' => now()->addMinutes($timing['prep_minutes']),
                'pickup_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'timing_state' => $timing['state'],
                'timing_snapshot' => $timing,
                'conflict_acknowledged' => $timing['needs_confirmation'] && $acknowledgeConflict,
                'idempotency_key' => $idempotencyKey,
            ])->save();

            foreach ($lines as $i => $l) {
                $order->items()->create([
                    'menu_item_id' => $l['item']->id,
                    'name' => $l['item']->name,
                    'art' => $l['item']->art,
                    'qty' => $l['qty'],
                    'unit_price' => Money::fromPaisa($l['unit_paisa']),
                    'line_total' => Money::fromPaisa($quote['lines'][$i]),
                    'prep_minutes' => $l['prep_minutes'],
                    'notes' => $l['note'] ?: null,
                ]);
            }

            return $order;
        });

        AuditLog::record('preorder.placed', $order, ['restaurant_id' => $restaurant->id, 'total' => $order->total, 'timing' => $timing['state']]);
        $this->notifyCustomer($order, "Order {$order->reference()} sent to {$restaurant->name}. We'll tell you when they accept it.");
        foreach (RestaurantStaff::where('restaurant_id', $restaurant->id)->with('user')->get() as $link) {
            if ($link->user) {
                $this->notifyOnce($link->user, new FoodOrderUpdate(
                    $order, 'food_order_new',
                    "New preorder {$order->reference()} at {$restaurant->name} — ".Money::format($order->total),
                    "food_order:{$order->id}:new",
                    route('preorder.merchant', ['restaurant' => $restaurant->slug]),
                ));
            }
        }

        return $order;
    }

    /** Customer cancellation: free, and only while the order is still `placed`. */
    public function cancel(FoodOrder $order, User $user): FoodOrder
    {
        if ((int) $order->user_id !== (int) $user->id) {
            throw new PreorderException('You can only cancel your own orders.');
        }
        if (! $order->userCanCancel()) {
            throw new PreorderException('This order can no longer be cancelled — the café has already '.($order->status === 'accepted' ? 'accepted' : 'handled').' it. Please speak to the café.');
        }

        return $this->move($order, 'placed', 'cancelled', $user, 'Cancelled by customer');
    }

    /**
     * Merchant/admin transition. Authorization (manage this restaurant) is checked by
     * the caller's policy; the state machine and pickup code are enforced here.
     */
    public function transition(FoodOrder $order, string $to, User $actor, ?string $reason = null, ?string $pickupCode = null): FoodOrder
    {
        if (! $order->restaurant->isManagedBy($actor)) {
            throw new PreorderException('You can only manage orders for your own café.');
        }
        if (! $order->canTransitionTo($to) || $to === 'cancelled') {
            throw new PreorderException("Can't move an order from {$order->status} to {$to}.");
        }
        if ($to === 'collected' && ! hash_equals((string) $order->pickup_code, trim((string) $pickupCode))) {
            throw new PreorderException('Pickup code does not match. Ask the customer to show the code on their order page.', 'pickup_code');
        }
        if ($to === 'rejected' && trim((string) $reason) === '') {
            $reason = 'The café could not take this order right now.';
        }

        return $this->move($order, $order->status, $to, $actor, $reason);
    }

    /** Atomic compare-and-set so concurrent clicks can't double-transition or double-notify. */
    private function move(FoodOrder $order, string $from, string $to, User $actor, ?string $reason): FoodOrder
    {
        $now = now();
        $data = ['status' => $to, 'updated_at' => $now, $to.'_at' => $now];
        if ($reason !== null) {
            $data['status_reason'] = mb_substr($reason, 0, 255);
        }
        if ($to === 'accepted') {
            // Kitchen clock starts on acceptance.
            $data['estimated_ready_at'] = $now->copy()->addMinutes(max(1, (int) $order->prep_minutes));
        }
        if ($to === 'ready') {
            $data['estimated_ready_at'] = $now;
        }

        $changed = FoodOrder::whereKey($order->id)->where('status', $from)->update($data);
        if ($changed !== 1) {
            throw new PreorderException('This order was just updated by someone else. Refresh to see its latest status.');
        }
        $order->refresh();
        AuditLog::record('preorder.'.$to, $order, ['from' => $from, 'by' => $actor->id]);

        $place = $order->restaurant->name;
        $messages = [
            'accepted' => "{$place} accepted order {$order->reference()}. Estimated ready ~".$order->estimated_ready_at?->format('g:i A').'.',
            'preparing' => "{$place} is preparing order {$order->reference()}.",
            'ready' => "Order {$order->reference()} is ready at {$place}. Show pickup code {$order->pickup_code}.",
            'collected' => "Order {$order->reference()} collected. Enjoy your food!",
            'rejected' => "{$place} declined order {$order->reference()}: {$order->status_reason}",
            'cancelled' => "You cancelled order {$order->reference()}. No charge.",
        ];
        $this->notifyCustomer($order, $messages[$to] ?? "Order {$order->reference()} is now {$to}.");

        return $order;
    }

    private function notifyCustomer(FoodOrder $order, string $message): void
    {
        if ($order->user) {
            $this->notifyOnce($order->user, new FoodOrderUpdate($order, 'food_order_update', $message, "food_order:{$order->id}:{$order->status}"));
        }
    }

    /** Sends unless a notification with the same dedupe key already exists for this user. */
    public function notifyOnce(User $user, FoodOrderUpdate $notification): bool
    {
        $exists = $user->notifications()
            ->where('type', FoodOrderUpdate::class)
            ->where('data->dedupe_key', $notification->dedupeKey)
            ->exists();
        if ($exists) {
            return false;
        }
        $user->notify($notification);

        return true;
    }
}
