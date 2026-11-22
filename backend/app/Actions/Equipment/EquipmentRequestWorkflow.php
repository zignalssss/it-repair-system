<?php

namespace App\Actions\Equipment;

use App\Enums\EquipmentRequestStatus;
use App\Enums\TicketStatus;
use App\Models\EquipmentRequest;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EquipmentRequestWorkflow
{
    public function request(
        User $actor,
        int $ticketId,
        string $reason,
        array $items,
    ): EquipmentRequest {
        return DB::transaction(function () use (
            $actor,
            $ticketId,
            $reason,
            $items,
        ) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('requestEquipment', $ticket);

            if ($ticket->status !== TicketStatus::InProgress) {
                abort(409, 'ขออุปกรณ์ได้เฉพาะงานที่กำลังดำเนินการ');
            }

            $pendingEquipment = $ticket->equipmentRequests()
                ->where('status', EquipmentRequestStatus::Pending->value)
                ->exists();

            $pendingInformation = $ticket->informationRequests()
                ->whereNull('responded_at')
                ->exists();

            if ($pendingEquipment || $pendingInformation) {
                abort(409, 'ใบงานนี้มีคำขอที่ยังรออยู่แล้ว');
            }

            $equipmentRequest = $ticket->equipmentRequests()->create([
                'requested_by_id' => $actor->id,
                'reason' => $reason,
                'status' => EquipmentRequestStatus::Pending,
                'requested_at' => now(),
            ]);

            $equipmentRequest->items()->createMany($items);

            $fromStatus = $ticket->status;

            $ticket->status = TicketStatus::WaitingEquipmentApproval;
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'equipment_requested',
                'audience' => 'senior',
                'from_status' => $fromStatus,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $ticket->assignee_id,
                'to_assignee_id' => $ticket->assignee_id,
                'equipment_request_id' => $equipmentRequest->id,
            ]);

            return $this->loadDetails($equipmentRequest);
        });
    }

    public function decide(
        User $actor,
        int $ticketId,
        int $equipmentRequestId,
        EquipmentRequestStatus $decision,
        ?string $reason,
    ): EquipmentRequest {
        return DB::transaction(function () use (
            $actor,
            $ticketId,
            $equipmentRequestId,
            $decision,
            $reason,
        ) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('decideEquipment', $ticket);

            $equipmentRequest = $ticket->equipmentRequests()
                ->lockForUpdate()
                ->findOrFail($equipmentRequestId);

            if (
                $ticket->status !== TicketStatus::WaitingEquipmentApproval
                || $equipmentRequest->status !== EquipmentRequestStatus::Pending
            ) {
                abort(409, 'คำขอนี้ไม่ได้อยู่ระหว่างรอพิจารณา');
            }

            if ($ticket->assignee_id === null) {
                abort(409, 'ใบงานนี้ไม่มีช่างผู้รับผิดชอบ');
            }

            if ($decision === EquipmentRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'ต้องเลือก approved หรือ rejected',
                ]);
            }

            if (
                $decision === EquipmentRequestStatus::Rejected
                && blank($reason)
            ) {
                throw ValidationException::withMessages([
                    'reason' => 'กรุณาระบุเหตุผลที่ปฏิเสธ',
                ]);
            }

            $equipmentRequest->status = $decision;
            $equipmentRequest->decided_by_id = $actor->id;
            $equipmentRequest->decision_reason = $reason;
            $equipmentRequest->decided_at = now();
            $equipmentRequest->save();

            $fromStatus = $ticket->status;

            $ticket->status = TicketStatus::InProgress;
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => $decision === EquipmentRequestStatus::Approved
                    ? 'equipment_approved'
                    : 'equipment_rejected',
                'audience' => 'senior',
                'from_status' => $fromStatus,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $ticket->assignee_id,
                'to_assignee_id' => $ticket->assignee_id,
                'equipment_request_id' => $equipmentRequest->id,
            ]);

            return $this->loadDetails($equipmentRequest);
        });
    }

    private function loadDetails(
        EquipmentRequest $equipmentRequest,
    ): EquipmentRequest {
        return $equipmentRequest
            ->load(['items', 'ticket'])
            ->loadSum(
                'items as estimated_total',
                DB::raw('quantity * estimated_unit_price'),
            );
    }
}