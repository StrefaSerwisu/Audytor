<?php

namespace App\Http\Requests;

use App\Models\TechnicalAuditAnswer;
use App\Models\TechnicalAuditControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTechnicalAuditAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $c = $this->route('control');

        return $c instanceof TechnicalAuditControl && $this->user()?->can('update', $c);
    }

    public function rules(): array
    {
        return ['value' => ['nullable'], 'result_status' => ['nullable', Rule::in(array_keys(TechnicalAuditAnswer::RESULTS))], 'comment' => ['nullable', 'string', 'max:20000'], 'not_applicable' => ['nullable', 'boolean'], 'not_applicable_reason' => ['nullable', 'string', 'max:5000'], 'proposed_risk_level' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])], 'proposed_recommendation' => ['nullable', 'string', 'max:20000'], 'customer_statement' => ['nullable', 'boolean'], 'customer_statement_source' => ['nullable', 'string', 'max:5000'], 'confidence_level' => ['nullable', Rule::in(array_keys(TechnicalAuditAnswer::CONFIDENCE))], 'complete' => ['nullable', 'boolean']];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            if (! $this->boolean('complete')) {
                return;
            }/** @var TechnicalAuditControl $c */ $c = $this->route('control');
            $na = $this->boolean('not_applicable');
            if ($na) {
                if (! $c->allow_not_applicable) {
                    $v->errors()->add('not_applicable', 'Ta kontrola nie dopuszcza N/D.');
                }if ($c->require_comment_when_na && blank($this->input('not_applicable_reason'))) {
                    $v->errors()->add('not_applicable_reason', 'N/D wymaga uzasadnienia.');
                }

                return;
            }
            if (blank($this->input('result_status'))) {
                $v->errors()->add('result_status', 'Wynik kontroli jest wymagany.');
            }
            $value = $this->input('value');
            if ($c->field_type === 'select') {
                $allowed = collect($c->options_json ?? [])->map(fn ($option) => is_array($option) ? ($option['value'] ?? $option['label'] ?? null) : $option);
                if (filled($value) && ! $allowed->contains($value)) {
                    $v->errors()->add('value', 'Wybrana wartosc nie wystepuje w snapshocie kontroli.');
                }
            }
            if ($c->field_type === 'date' && filled($value) && strtotime((string) $value) === false) {
                $v->errors()->add('value', 'Podaj poprawna date.');
            }
            if ($c->required && $this->missingValue($c)) {
                $v->errors()->add('value', 'Odpowiedz jest wymagana.');
            }if ($c->require_evidence && ! $c->evidence()->exists()) {
                $v->errors()->add('evidence', 'Ta kontrola wymaga dowodu.');
            }if (in_array($this->input('result_status'), ['non_compliant', 'not_verified'], true) && blank($this->input('comment'))) {
                $v->errors()->add('comment', 'Ten wynik wymaga komentarza.');
            }if (in_array($this->input('proposed_risk_level'), ['high', 'critical'], true) && blank($this->input('proposed_recommendation'))) {
                $v->errors()->add('proposed_recommendation', 'Wysokie lub krytyczne ryzyko wymaga rekomendacji.');
            }if ($this->boolean('customer_statement') && blank($this->input('customer_statement_source'))) {
                $v->errors()->add('customer_statement_source', 'Deklaracja klienta wymaga zrodla.');
            }
        });
    }

    private function missingValue(TechnicalAuditControl $c): bool
    {
        $value = $this->input('value');

        return match ($c->field_type) {
            'boolean' => ! in_array($value, ['0', '1', 0, 1, true, false], true),
            'multiselect' => ! is_array($value) || $value === [],
            'number' => ! is_numeric($value),
            'file' => ! $c->evidence()->exists(),
            default => blank($value)
        };
    }
}
