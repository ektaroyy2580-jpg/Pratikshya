<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /** Linked merchants (restaurant_staff) and admins only. */
    public function manage(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->isManagedBy($user);
    }
}
