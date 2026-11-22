<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:5000'],

            'items' => ['required', 'array', 'min:1', 'max:20'],

            'items.*' => [
                'required',
                'array:name,quantity,estimated_unit_price',
            ],

            'items.*.name' => ['required', 'string', 'max:200'],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],

            'items.*.estimated_unit_price' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
        ];
    }
}