<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class RespondInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => [
                'nullable',
                'string',
                'max:10000',
                'required_without:files',
            ],

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