<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\SupportLevel;
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

        return $visible ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && $user->role === UserRole::User
            && $user->department?->is_active === true;
    }

    public function cancel(User $user, RepairTicket $ticket): bool
    {
        return $user->is_active
            && $user->role === UserRole::User
            && (int) $ticket->requester_id === (int) $user->id
            && (int) $ticket->department_id === (int) $user->department_id;
    }

    public function claim(User $user, RepairTicket $ticket): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return match ($user->role) {
            UserRole::JuniorTechnician =>
                $ticket->support_level === SupportLevel::Junior,

            UserRole::SeniorTechnician =>
                $ticket->support_level === SupportLevel::Senior,

            default => false,
        };
    }

    public function addWorkLog(User $user, RepairTicket $ticket): bool
    {
        return $user->is_active
            && ($user->role === UserRole::JuniorTechnician || $user->role === UserRole::SeniorTechnician)
            && $ticket->assignee_id !== null
            && (int) $ticket->assignee_id === (int) $user->id;
    }
}