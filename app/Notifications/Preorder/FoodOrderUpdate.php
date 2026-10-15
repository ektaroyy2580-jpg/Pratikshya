<?php

namespace App\Notifications\Preorder;

use App\Models\FoodOrder;
use Illuminate\Notifications\Notification;

/**
 * Database notification for preorder status changes (to the customer) and new
 * orders (to the café's merchants). `dedupe_key` lets the sender guarantee one
 * notification per order per status, even with retries or double-clicks.
 */
class FoodOrderUpdate extends Notification
{
    public function __construct(
        public FoodOrder $order,
        public string $kind,
        public string $message,
        public string $dedupeKey,
        public ?string $url = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,                 // food_order_update | food_order_new
            'message' => $this->message,
            'food_order_id' => $this->order->id,
            'status' => $this->order->status,
            'ticket_id' => $this->order->ticket_id,
            'token' => $this->order->reference(),
            'url' => $this->url ?? route('preorder.order', $this->order),
            'dedupe_key' => $this->dedupeKey,
        ];
    }
}
