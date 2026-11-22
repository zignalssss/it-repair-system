<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class ResolveRepairTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:10000'],

            'parts' => ['sometimes', 'array', 'max:50'],

            'parts.*' => [
                'required',
                'array:name,quantity,equipment_request_item_id',
            ],

            'parts.*.name' => ['required', 'string', 'max:200'],

            'parts.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],

            'parts.*.equipment_request_item_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}