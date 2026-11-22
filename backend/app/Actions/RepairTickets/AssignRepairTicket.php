<?php

namespace App\Actions\RepairTickets;

use App\Enums\SupportLevel;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignRepairTicket
{
    public function handle(
        User $actor,
        int $ticketId,
        int $assigneeId,
        ?string $reason = null,
    ): RepairTicket {
        return DB::transaction(function () use (
            $actor,
            $ticketId,
            $assigneeId,
            $reason,
        ) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('assign', $ticket);

            if (in_array($ticket->status, [
                TicketStatus::Resolved,
                TicketStatus::Cancelled,
            ], true)) {
                abort(409, 'ไม่สามารถมอบหมายงานที่ปิดหรือยกเลิกแล้ว');
            }

            $technician = User::query()
                ->lockForUpdate()
                ->findOrFail($assigneeId);

            if (! $technician->is_active || ! in_array($technician->role, [UserRole::JuniorTechnician,UserRole::SeniorTechnician], true) ) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'ต้องเลือกบัญชีช่างที่เปิดใช้งาน',
                ]);
            }

            $targetLevel = match ($technician->role) {
                UserRole::JuniorTechnician => SupportLevel::Junior,
                UserRole::SeniorTechnician => SupportLevel::Senior,
            };

            if ( (int) $ticket->assignee_id === (int) $technician->id && $ticket->support_level === $targetLevel ) {
                return $ticket->load([
                    'requester',
                    'department',
                    'assignee',
                ]);
            }

            $fromAssigneeId = $ticket->assignee_id;
            $fromLevel = $ticket->support_level;

            $ticket->assignee()->associate($technician);
            $ticket->support_level = $targetLevel;
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'assigned',
                'audience' => 'manager',
                'from_status' => $ticket->status,
                'to_status' => $ticket->status,
                'from_level' => $fromLevel,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $fromAssigneeId,
                'to_assignee_id' => $ticket->assignee_id,
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