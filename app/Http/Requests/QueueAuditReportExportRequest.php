<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueueAuditReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:pdf,docx'],
        ];
    }
}
