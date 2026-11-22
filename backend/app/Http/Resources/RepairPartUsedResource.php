<?php

namespace App\Http\Resources;

use App\Models\RepairPartUsed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RepairPartUsed */
class RepairPartUsedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'recorded_at' => $this->recorded_at->toISOString(),
        ];
    }
}