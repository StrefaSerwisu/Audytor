<?php

namespace App\Http\Requests;

use App\Models\SalesQualification;
use Illuminate\Foundation\Http\FormRequest;

class CreateSalesQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesQualification::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'client_location_id' => ['nullable', 'integer', 'exists:client_locations,id'],
            'audit_type_id' => ['required', 'integer', 'exists:audit_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'expected_date' => ['nullable', 'date_format:Y-m-d'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'sales_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
