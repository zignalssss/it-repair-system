<?php

namespace App\Actions\RepairTickets;

use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CancelRepairTicket
{
    public function handle(
        User $actor,
        int $ticketId,
        string $reason,
    ): RepairTicket {
        return DB::transaction(function () use ($actor, $ticketId, $reason) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('cancel', $ticket);

            if ($ticket->status !== TicketStatus::New || $ticket->first_started_at !== null) {
                abort(409, 'ยกเลิกได้เฉพาะใบงานที่ยังไม่เริ่มดำเนินการ');
            }

            $fromStatus = $ticket->status;
            $assigneeId = $ticket->assignee_id;

            $ticket->status = TicketStatus::Cancelled;
            $ticket->cancelled_at = now();
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'cancelled',
                'audience' => 'requester',
                'from_status' => $fromStatus,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $assigneeId,
                'to_assignee_id' => $assigneeId,
                'metadata' => [
                    'reason' => $reason,
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