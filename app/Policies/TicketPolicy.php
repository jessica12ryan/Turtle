<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isTenant()) {
            return $ticket->tenant_id === $user->id;
        }
        if ($user->isMaintenance()) {
            return $ticket->assigned_to === $user->id || $user->companies->contains($ticket->property->company_id);
        }
        return $user->companies->contains($ticket->property->company_id);
    }

    public function create(User $user): bool
    {
        return $user->isTenant();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isLandlord() || $user->isPropertyManager()) {
            return $user->companies->contains($ticket->property->company_id);
        }
        return false;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return ($user->isLandlord() || $user->isPropertyManager()) && $user->companies->contains($ticket->property->company_id);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        if ($user->isTenant()) {
            return $ticket->tenant_id === $user->id;
        }
        return $user->companies->contains($ticket->property->company_id);
    }

    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $user->companies->contains($ticket->property->company_id);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $user->companies->contains($ticket->property->company_id);
    }
}
