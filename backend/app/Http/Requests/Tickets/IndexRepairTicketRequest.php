<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRepairTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('viewAny', RepairTicket::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}