<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'department' => $this->department
                ? [
                    'id' => $this->department->id,
                    'code' => $this->department->code,
                    'name' => $this->department->name,
                ]
                : null,
        ];
    }
}