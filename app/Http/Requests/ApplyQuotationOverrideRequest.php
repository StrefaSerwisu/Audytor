<?php

namespace App\Http\Requests;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyQuotationOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation && ($this->user()?->can('override', $quotation) ?? false);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'hourly_rate' => ['nullable', 'decimal:0,2', 'min:0'],
            'engineers_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'additional_hours' => ['nullable', 'decimal:0,2', 'min:0'],
            'additional_costs' => ['nullable', 'decimal:0,2', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['nullable', 'decimal:0,2', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'assumptions' => ['nullable', 'string', 'max:10000'],
            'exclusions' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
