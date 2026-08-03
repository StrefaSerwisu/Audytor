<?php

namespace App\Services;

use App\Models\QualificationAnswer;
use App\Models\SalesQualification;

class QualificationScopeSummaryService
{
    public function __construct(
        private readonly QualificationCompletionService $completion,
        private readonly QualificationConditionService $conditions,
    ) {}

    public function generate(SalesQualification $qualification): string
    {
        $qualification->loadMissing(['client', 'location', 'auditType', 'version', 'answers']);
        $answers = $qualification->answers->keyBy('question_code');
        $values = $answers->mapWithKeys(fn (QualificationAnswer $answer): array => [
            $answer->question_code => $answer->value_json['value'] ?? null,
        ])->all();
        $activeQuestions = $this->completion->activeQuestions($qualification->qualification_snapshot, $values);
        $scopeValues = $activeQuestions
            ->where('affects_scope', true)
            ->map(function (array $question) use ($answers): ?string {
                $answer = $answers->get($question['code']);

                if (! $answer || ! array_key_exists('value', $answer->value_json ?? [])) {
                    return null;
                }

                return ($question['question'] ?? $question['code']).': '.$this->formatValue($answer->value_json['value']);
            })
            ->filter()
            ->values();
        $modules = collect($this->conditions->visibleModules($qualification->qualification_snapshot, $values))
            ->pluck('name')
            ->implode(', ');

        $parts = [
            "Kwalifikacja {$qualification->auditType->name} dla klienta {$qualification->client->name}",
            $qualification->location ? "lokalizacja: {$qualification->location->name}" : null,
            "wersja {$qualification->version->version}",
            $modules !== '' ? "moduly Sales: {$modules}" : null,
            $scopeValues->isNotEmpty() ? 'zakres: '.$scopeValues->implode('; ') : null,
            'zakonczono: '.($qualification->completed_at?->format('Y-m-d') ?? now()->format('Y-m-d')),
        ];

        return implode('. ', array_filter($parts)).'.';
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            $value === true => 'Tak',
            $value === false => 'Nie',
            $value === null => 'Nie wiem',
            is_array($value) => implode(', ', $value),
            default => (string) $value,
        };
    }
}
