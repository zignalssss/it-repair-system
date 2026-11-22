<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(['approved', 'rejected']),
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:status,rejected',
            ],
        ];
    }
}