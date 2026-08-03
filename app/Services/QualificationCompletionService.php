<?php

namespace App\Services;

use App\Models\QualificationAnswer;
use App\Models\SalesQualification;
use Illuminate\Support\Collection;

class QualificationCompletionService
{
    public function __construct(private readonly QualificationConditionService $conditions) {}

    /** @return array{active:int, required:int, completed:int, missing:int, percent:int, missing_questions:array<int, array<string, mixed>>, questions:array<int, array<string, mixed>>} */
    public function calculate(SalesQualification $qualification): array
    {
        $qualification->loadMissing('answers.attachments');
        $answers = $qualification->answers->keyBy('question_code');
        $values = $answers->mapWithKeys(fn (QualificationAnswer $answer): array => [
            $answer->question_code => $answer->value_json['value'] ?? null,
        ])->all();
        $questions = $this->activeQuestions($qualification->qualification_snapshot, $values);
        $required = $questions->where('required', true);
        $completed = $required->filter(fn (array $question): bool => $this->isComplete(
            $question,
            $answers->get($question['code']),
        ));
        $missing = $required->reject(fn (array $question): bool => $this->isComplete(
            $question,
            $answers->get($question['code']),
        ))->values();

        return [
            'active' => $questions->count(),
            'required' => $required->count(),
            'completed' => $completed->count(),
            'missing' => $missing->count(),
            'percent' => $required->isEmpty() ? 100 : (int) round(($completed->count() / $required->count()) * 100),
            'missing_questions' => $missing->all(),
            'questions' => $questions->all(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function activeQuestions(array $snapshot, array $values): Collection
    {
        return collect($snapshot['sales_modules'] ?? [])
            ->filter(fn (array $module): bool => ($module['active'] ?? false)
                && $this->conditions->matches($module['conditional_logic'] ?? null, $values))
            ->flatMap(fn (array $module): array => collect($module['questions'] ?? [])
                ->filter(fn (array $question): bool => ($question['active'] ?? false)
                    && ($question['field_type'] ?? null) !== 'info'
                    && $this->conditions->matches($question['conditional_logic'] ?? null, $values))
                ->map(fn (array $question): array => [...$question, 'module_name' => $module['name']])
                ->all())
            ->values();
    }

    private function isComplete(array $question, ?QualificationAnswer $answer): bool
    {
        if (! $answer) {
            return false;
        }

        $hasValue = is_array($answer->value_json) && array_key_exists('value', $answer->value_json);
        $value = $answer->value_json['value'] ?? null;

        return match ($question['field_type'] ?? null) {
            'text', 'textarea', 'select', 'date' => is_string($value) && trim($value) !== '',
            'number' => is_numeric($value),
            'boolean' => $hasValue && in_array($value, [true, false, null], true),
            'multiselect' => is_array($value) && $value !== [],
            'file' => $answer->attachments->isNotEmpty(),
            default => $value !== null && $value !== '',
        };
    }
}
