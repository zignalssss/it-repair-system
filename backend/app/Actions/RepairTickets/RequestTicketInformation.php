<?php

namespace App\Actions\RepairTickets;

use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequestTicketInformation
{
    public function handle(
        User $actor,
        int $ticketId,
        string $body,
    ): RepairTicket {
        return DB::transaction(function () use ($actor, $ticketId, $body) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('requestInformation', $ticket);

            if ($ticket->status !== TicketStatus::InProgress) {
                abort(409, 'ขอข้อมูลได้เฉพาะงานที่กำลังดำเนินการ');
            }

            $hasPendingRequest = $ticket->informationRequests()
                ->whereNull('responded_at')
                ->exists();

            if ($hasPendingRequest) {
                abort(409, 'ใบงานนี้มีคำขอข้อมูลที่ยังรอตอบอยู่แล้ว');
            }

            $note = $ticket->notes()->create([
                'author_id' => $actor->id,
                'author_role' => $actor->role,
                'kind' => 'info_question',
                'audience' => 'requester',
                'body' => $body,
            ]);

            $informationRequest = $ticket->informationRequests()->create([
                'requested_by_id' => $actor->id,
                'question_note_id' => $note->id,
                'requested_at' => now(),
            ]);

            $fromStatus = $ticket->status;

            $ticket->status = TicketStatus::WaitingInfo;
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'info_requested',
                'audience' => 'requester',
                'from_status' => $fromStatus,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $ticket->assignee_id,
                'to_assignee_id' => $ticket->assignee_id,
                'note_id' => $note->id,
                'information_request_id' => $informationRequest->id,
            ]);

            return $ticket->load([
                'requester',
                'department',
                'assignee',
                'pendingInformationRequest.questionNote.author',
            ]);
        });
    }
}