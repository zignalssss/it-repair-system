<?php

namespace App\Http\Resources;

use App\Models\RepairTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RepairTicket */
class RepairTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_no' => $this->ticket_no,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'asset_label' => $this->asset_label,
            'status' => $this->status->value,
            'support_level' => $this->support_level->value,
            'priority' => $this->priority->value,

            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),

            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'code' => $this->department->code,
                'name' => $this->department->name,
            ]),

            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),

            'first_started_at' => $this->first_started_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}