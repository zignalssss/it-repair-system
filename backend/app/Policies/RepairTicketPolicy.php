<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RepairTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, RepairTicket $ticket): Response
    {
        $visible = RepairTicket::query()
            ->visibleTo($user)
            ->whereKey($ticket->id)
            ->exists();

        return $visible
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role === UserRole::User
            && $user->department?->is_active === true;
    }
}