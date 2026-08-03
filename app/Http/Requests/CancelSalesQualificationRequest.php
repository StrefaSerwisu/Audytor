<?php

namespace App\Http\Requests;

use App\Models\SalesQualification;
use Illuminate\Foundation\Http\FormRequest;

class CancelSalesQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $qualification = $this->route('qualification');

        return $qualification instanceof SalesQualification
            && ($this->user()?->can('update', $qualification) ?? false);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
