<?php

namespace App\Notifications\Bills;

use App\Models\GroupBill;
use Illuminate\Notifications\Notification;

/**
 * Database-only notification for bill events: invited, payment_claimed, payment_confirmed, payment_disputed, member_joined.
 * Contains no amounts-owed of other people and no tokens.
 */
class BillActivity extends Notification
{
    public function __construct(public string $kind, public GroupBill $bill, public string $message, public array $extra = []) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'bill_'.$this->kind,
            'message' => $this->message,
            'group_bill_id' => $this->bill->id,
            'url' => route('bills.index'),
        ] + $this->extra;
    }
}
