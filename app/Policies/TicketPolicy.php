<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /** Owner, or staff/admin operating the ticket's location. */
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id || $this->manage($user, $ticket);
    }

    public function leave(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id && $ticket->status === 'waiting';
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return in_array($user->role, ['staff', 'admin'], true)
            && $user->canOperateLocation($ticket->service->location_id);
    }
}
