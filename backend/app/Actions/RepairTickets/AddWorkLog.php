<?php

namespace App\Actions\RepairTickets;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\RepairTicket;
use App\Models\TicketNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddWorkLog
{
    public function handle(
        User $actor,
        int $ticketId,
        string $body,
    ): TicketNote {
        return DB::transaction(function () use ($actor, $ticketId, $body) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('addWorkLog', $ticket);

            if ($ticket->status !== TicketStatus::InProgress) {
                abort(409, 'บันทึกการซ่อมได้เฉพาะงานที่กำลังดำเนินการ');
            }

            $audience = match ($actor->role) {
                UserRole::JuniorTechnician => 'junior',
                UserRole::SeniorTechnician => 'senior',
            };

            $note = $ticket->notes()->create([
                'author_id' => $actor->id,
                'author_role' => $actor->role,
                'kind' => 'work_log',
                'audience' => $audience,
                'body' => $body,
            ]);

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'note_added',
                'audience' => $audience,
                'from_status' => $ticket->status,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $ticket->assignee_id,
                'to_assignee_id' => $ticket->assignee_id,
                'note_id' => $note->id,
            ]);

            return $note->load('author');
        });
    }
}