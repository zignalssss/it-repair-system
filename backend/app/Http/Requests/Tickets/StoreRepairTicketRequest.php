<?php

namespace App\Http\Requests\Tickets;

use App\Models\User;
use App\Models\RepairTicket;
use Illuminate\Foundation\Http\FormRequest;

class StoreRepairTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('create', RepairTicket::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'asset_label' => ['nullable', 'string', 'max:100'],
        ];
    }
}