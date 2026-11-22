<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\EquipmentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EquipmentRequest */
class EquipmentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $full = $user instanceof User
            && in_array($user->role, [
                UserRole::SeniorTechnician,
                UserRole::ITManager,
            ], true);

        $manager = $user instanceof User
            && $user->role === UserRole::ITManager;

        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'status' => $this->status->value,

            'ticket_status' => $this->whenLoaded(
                'ticket',
                fn () => $this->ticket->status->value,
            ),

            'items' => $this->whenLoaded(
                'items',
                fn () => $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    ...($full ? [
                        'estimated_unit_price' => $item->estimated_unit_price,
                    ] : []),
                ]),
            ),

            'reason' => $this->when($full, fn () => $this->reason),

            'estimated_total' => $this->when(
                $full,
                fn () => $this->estimated_total,
            ),

            'requested_by_id' => $this->when(
                $full,
                fn () => $this->requested_by_id,
            ),

            'requested_at' => $this->when(
                $full,
                fn () => $this->requested_at->toISOString(),
            ),

            'decision_reason' => $this->when(
                $full,
                fn () => $this->decision_reason,
            ),

            'decided_at' => $this->when(
                $full,
                fn () => $this->decided_at?->toISOString(),
            ),

            'decided_by_id' => $this->when(
                $manager,
                fn () => $this->decided_by_id,
            ),
        ];
    }
}