<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuditAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string', 'max:5000'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'not_applicable' => ['nullable', 'boolean'],
            'not_applicable_reason' => ['nullable', 'string', 'max:5000'],
            'risk_level' => ['nullable', 'in:low,medium,high,critical'],
            'recommendation_text' => ['nullable', 'string', 'max:5000'],
            'attachment_caption' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,txt,csv,doc,docx,xls,xlsx,zip'],
        ];
    }
}
