<?php

namespace App\Actions\RepairTickets;

use App\Enums\EquipmentRequestStatus;
use App\Enums\TicketStatus;
use App\Models\EquipmentRequestItem;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResolveRepairTicket
{
    public function handle(
        User $actor,
        int $ticketId,
        string $summary,
        array $parts = [],
    ): RepairTicket {
        return DB::transaction(function () use ($actor,$ticketId,$summary,$parts) {
            $ticket = RepairTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            Gate::forUser($actor)->authorize('view', $ticket);
            Gate::forUser($actor)->authorize('resolve', $ticket);

            if ($ticket->status !== TicketStatus::InProgress) {
                abort(409, 'ปิดได้เฉพาะงานที่กำลังดำเนินการ');
            }

            $pendingInformation = $ticket->informationRequests()
                ->whereNull('responded_at')
                ->exists();

            $pendingEquipment = $ticket->equipmentRequests()
                ->where('status', EquipmentRequestStatus::Pending->value)
                ->exists();

            if ($pendingInformation || $pendingEquipment) {
                abort(409, 'ยังมีคำขอข้อมูลหรืออุปกรณ์ที่รออยู่');
            }

            $itemIds = collect($parts)
                ->pluck('equipment_request_item_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $approvedItems = EquipmentRequestItem::query()
                ->whereIn('id', $itemIds)
                ->whereHas(
                    'equipmentRequest',
                    function (Builder $requests) use ($ticket) {
                        $requests->where('ticket_id', $ticket->id)
                            ->where(
                                'status',
                                EquipmentRequestStatus::Approved->value,
                            );
                    },
                )
                ->get()
                ->keyBy('id');

            $alreadyUsed = $ticket->partsUsed()
                ->whereIn('equipment_request_item_id', $itemIds)
                ->select('equipment_request_item_id')
                ->selectRaw('SUM(quantity) AS total')
                ->groupBy('equipment_request_item_id')
                ->pluck('total', 'equipment_request_item_id');

            $requestedQuantities = [];
            $rows = [];

            foreach ($parts as $index => $part) {
                $itemId = isset($part['equipment_request_item_id'])
                    ? (int) $part['equipment_request_item_id']
                    : null;

                $quantity = (int) $part['quantity'];
                $item = $itemId !== null
                    ? $approvedItems->get($itemId)
                    : null;

                if ($itemId !== null && $item === null) {
                    throw ValidationException::withMessages([
                        "parts.{$index}.equipment_request_item_id" =>
                            'ต้องเป็นรายการที่อนุมัติแล้วของใบงานนี้',
                    ]);
                }

                if ($item !== null) {
                    $requestedQuantities[$itemId] =
                        ($requestedQuantities[$itemId] ?? 0) + $quantity;

                    $totalUsed = (int) $alreadyUsed->get($itemId, 0)
                        + $requestedQuantities[$itemId];

                    if ($totalUsed > $item->quantity) {
                        throw ValidationException::withMessages([
                            "parts.{$index}.quantity" =>
                                'จำนวนใช้จริงเกินจำนวนที่อนุมัติ',
                        ]);
                    }
                }

                $rows[] = [
                    'equipment_request_item_id' => $itemId,
                    'name' => $item?->name ?? $part['name'],
                    'quantity' => $quantity,
                    'recorded_by_id' => $actor->id,
                    'recorded_at' => now(),
                ];
            }

            $ticket->partsUsed()->createMany($rows);

            $note = $ticket->notes()->create([
                'author_id' => $actor->id,
                'author_role' => $actor->role,
                'kind' => 'resolution',
                'audience' => 'requester',
                'body' => $summary,
            ]);

            $fromStatus = $ticket->status;

            $ticket->status = TicketStatus::Resolved;
            $ticket->resolved_at = now();
            $ticket->resolvedBy()->associate($actor);
            $ticket->save();

            $ticket->events()->create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'type' => 'resolved',
                'audience' => 'requester',
                'from_status' => $fromStatus,
                'to_status' => $ticket->status,
                'from_level' => $ticket->support_level,
                'to_level' => $ticket->support_level,
                'from_assignee_id' => $ticket->assignee_id,
                'to_assignee_id' => $ticket->assignee_id,
                'note_id' => $note->id,
                'metadata' => [
                    'parts_count' => count($rows),
                ],
            ]);

            return $ticket->load([
                'requester',
                'department',
                'assignee',
                'resolvedBy',
                'partsUsed',
            ]);
        });
    }
}