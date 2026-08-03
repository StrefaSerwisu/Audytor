<?php

namespace App\Http\Requests;

use App\Models\SalesQualification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQualificationAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $qualification = $this->route('qualification');

        return $qualification instanceof SalesQualification
            && ($this->user()?->can('update', $qualification) ?? false);
    }

    public function rules(): array
    {
        $question = $this->questionSnapshot();
        $type = $question['field_type'] ?? null;
        $options = $question['options_json'] ?? [];
        $allowedOptions = array_is_list($options) ? $options : array_keys($options);

        return match ($type) {
            'text', 'textarea' => ['value' => ['nullable', 'string', 'max:10000']],
            'number' => ['value' => ['nullable', 'numeric']],
            'boolean' => ['value' => ['nullable', Rule::in(['true', 'false', 'unknown'])]],
            'select' => ['value' => ['nullable', Rule::in($allowedOptions)]],
            'multiselect' => [
                'value' => ['nullable', 'array'],
                'value.*' => [Rule::in($allowedOptions)],
            ],
            'date' => ['value' => ['nullable', 'date_format:Y-m-d']],
            'file' => ['file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,docx,xlsx']],
            default => ['value' => ['prohibited']],
        };
    }

    /** @return array<string, mixed> */
    public function questionSnapshot(): array
    {
        /** @var SalesQualification $qualification */
        $qualification = $this->route('qualification');
        $code = (string) $this->route('questionCode');

        return collect($qualification->qualification_snapshot['sales_modules'] ?? [])
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->firstWhere('code', $code)
            ?? abort(404);
    }
}
