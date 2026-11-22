<?php

namespace App\Http\Resources;

use App\Models\TicketNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketNote */
class TicketNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'kind' => $this->kind,
            'audience' => $this->audience,
            'body' => $this->body,
            'author_role' => $this->author_role->value,

            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),

            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => AttachmentResource::collection($this->attachments),
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}