<?php

namespace App\Http\Requests\Tickets;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreRepairTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! ($user instanceof User)) {
            return false;
        }

        return $user->is_active
            && $user->role === UserRole::User
            && $user->department?->is_active === true;
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