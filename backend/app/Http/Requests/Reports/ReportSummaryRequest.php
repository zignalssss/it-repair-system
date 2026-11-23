<?php

namespace App\Http\Requests\Reports;

use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReportSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('viewReports', RepairTicket::class);
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required','date_format:Y-m-d','after_or_equal:from'],
        ];
    }
}