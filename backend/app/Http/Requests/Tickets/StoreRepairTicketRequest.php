<?php

namespace App\Http\Requests\Tickets;

use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

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

            'files' => [
                'sometimes',
                'array',
                'min:1',
                'max:3',
            ],

            'files.*' => [
                'required',
                File::types([
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'pdf',
                    'mp4',
                    'webm',
                ])->max(2 * 1024),
            ],
        ];
    }
}