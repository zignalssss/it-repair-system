<?php

namespace App\Actions\RepairTickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateTicketPriority
{
    public function handle(
        User $actor,
        int $ticketId,
        TicketPriority $priority,
    ): RepairTicket {
        return DB::transaction(function () use ($actor, $ticketId, $priority) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('updatePriority', $ticket);

            if (in_array($ticket->status, [
                TicketStatus::Resolved,
                TicketStatus::Cancelled,
            ], true)) {
                abort(409, 'ไม่สามารถแก้ความเร่งด่วนของงานที่จบแล้ว');
            }

            if ($ticket->priority === $priority) {
                return $ticket->load([
                    'requester',
                    'department',
                    'assignee',
                ]);
            }

            $fromPriority = $ticket->priority;

            $ticket->priority = $priority;
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'priority_changed',
                'audience' => 'manager',
                'from_status' => $ticket->status,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $ticket->assignee_id,
                'to_assignee_id' => $ticket->assignee_id,
                'metadata' => [
                    'from_priority' => $fromPriority->value,
                    'to_priority' => $priority->value,
                ],
            ]);

            return $ticket->load([
                'requester',
                'department',
                'assignee',
            ]);
        });
    }
}