<?php

namespace App\Actions\RepairTickets;

use App\Enums\SupportLevel;
use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransferRepairTicket
{
    public function handle(
        User $actor,
        int $ticketId,
        SupportLevel $targetLevel,
        string $body,
    ): RepairTicket {
        return DB::transaction(function () use (
            $actor,
            $ticketId,
            $targetLevel,
            $body,
        ) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            $isEscalation = $targetLevel === SupportLevel::Senior;

            $ability = $isEscalation
                ? 'escalate'
                : 'returnToJunior';

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize($ability, $ticket);

            if ($ticket->status !== TicketStatus::InProgress) {
                abort(409, 'ส่งต่องานได้เฉพาะงานที่กำลังดำเนินการ');
            }

            $fromStatus = $ticket->status;
            $fromLevel = $ticket->support_level;
            $fromAssigneeId = $ticket->assignee_id;

            $note = $ticket->notes()->create([
                'author_id' => $actor->id,
                'author_role' => $actor->role,
                'kind' => 'handoff',
                'audience' => $targetLevel->value,
                'body' => $body,
            ]);

            $ticket->status = $isEscalation
                ? TicketStatus::Escalated
                : TicketStatus::Returned;

            $ticket->support_level = $targetLevel;
            $ticket->assignee()->dissociate();

            if ($isEscalation) {
                $ticket->first_escalated_at ??= now();
            }

            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => $ticket->status->value,
                'audience' => $targetLevel->value,
                'from_status' => $fromStatus,
                'to_status' => $ticket->status,
                'from_level' => $fromLevel,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $fromAssigneeId,
                'to_assignee_id' => null,
                'note_id' => $note->id,
            ]);

            return $ticket->load([
                'requester',
                'department',
                'assignee',
            ]);
        });
    }
}