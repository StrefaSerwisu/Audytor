<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientReportFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_comment' => ['nullable', 'string', 'max:5000'],
            'accepted_recommendations' => ['nullable', 'array'],
            'accepted_recommendations.*' => ['string', 'max:120'],
        ];
    }
}
