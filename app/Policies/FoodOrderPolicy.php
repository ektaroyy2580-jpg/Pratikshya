<?php

namespace App\Policies;

use App\Models\FoodOrder;
use App\Models\User;

class FoodOrderPolicy
{
    /** The customer, or a merchant/admin of the order's restaurant. */
    public function view(User $user, FoodOrder $order): bool
    {
        return (int) $order->user_id === (int) $user->id || $this->manage($user, $order);
    }

    /** Only the customer, and only before the café accepts. */
    public function cancel(User $user, FoodOrder $order): bool
    {
        return (int) $order->user_id === (int) $user->id && $order->userCanCancel();
    }

    public function manage(User $user, FoodOrder $order): bool
    {
        return (bool) $order->restaurant?->isManagedBy($user);
    }
}
