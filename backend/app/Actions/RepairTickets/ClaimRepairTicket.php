<?php

namespace App\Actions\RepairTickets;

use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClaimRepairTicket
{
    public function handle(User $actor, int $ticketId): RepairTicket
    {
        return DB::transaction(function () use ($actor, $ticketId) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('claim', $ticket);

            $claimableStatuses = [
                TicketStatus::New,
                TicketStatus::Escalated,
                TicketStatus::Returned,
            ];

            if (! in_array($ticket->status, $claimableStatuses, true)) {
                abort(409, 'สถานะปัจจุบันไม่สามารถรับงานได้');
            }

            if ($ticket->assignee_id !== null && (int) $ticket->assignee_id !== (int) $actor->id){
                abort(409, 'ใบงานนี้มีผู้รับผิดชอบคนอื่นแล้ว');
            }

            $fromStatus = $ticket->status;
            $fromAssigneeId = $ticket->assignee_id;

            $ticket->assignee()->associate($actor);
            $ticket->status = TicketStatus::InProgress;
            $ticket->first_started_at ??= now();
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'claimed',
                'audience' => $ticket->support_level->value,
                'from_status' => $fromStatus,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $fromAssigneeId,
                'to_assignee_id' => $actor->id,
            ]);

            return $ticket->load([
                'requester',
                'department',
                'assignee',
            ]);
        });
    }
}