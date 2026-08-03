<?php

namespace App\Http\Requests;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class QuotationTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation && ($this->user()?->can('view', $quotation) ?? false);
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:4000'],
            'reason' => ['nullable', 'string', 'max:4000'],
            'accepted_at' => ['nullable', 'date'],
            'accepted_by' => ['nullable', 'string', 'max:255'],
            'purchase_order_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
