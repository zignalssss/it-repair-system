<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin TicketEvent */
class TicketEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $canSeeEquipmentReference = $user instanceof User
            && in_array($user->role, [
                UserRole::SeniorTechnician,
                UserRole::ITManager,
            ], true);

        $metadataKeys = match ($this->type) {
            'created' => ['ticket_no'],
            'cancelled' => ['reason'],
            'resolved' => ['parts_count'],
            'assigned' => ['reason'],
            'priority_changed' => ['from_priority', 'to_priority'],
            default => [],
        };

        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label(),
            'actor_role' => $this->actor_role->value,

            'actor' => $this->whenLoaded('actor', fn () => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ]),

            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'from_level' => $this->from_level?->value,
            'to_level' => $this->to_level?->value,

            'from_assignee_id' => $this->from_assignee_id,
            'to_assignee_id' => $this->to_assignee_id,

            'information_request_id' => $this->information_request_id,

            'equipment_request_id' => $this->when(
                $canSeeEquipmentReference,
                fn () => $this->equipment_request_id,
            ),

            'note' => $this->whenLoaded(
                'note',
                fn () => new TicketNoteResource($this->note),
            ),

            'metadata' => (object) Arr::only(
                $this->metadata ?? [],
                $metadataKeys,
            ),

            'created_at' => $this->created_at->toISOString(),
        ];
    }

    private function label(): string
    {
        return $this->resource->label();
    }
}