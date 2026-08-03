<?php

namespace App\Services;

class QualificationConditionService
{
    /** @return array<int, array<string, mixed>> */
    public function visibleModules(array $snapshot, array $values): array
    {
        return collect($snapshot['sales_modules'] ?? [])
            ->filter(fn (array $module): bool => ($module['active'] ?? false)
                && $this->matches($module['conditional_logic'] ?? null, $values))
            ->map(function (array $module) use ($values): array {
                $module['questions'] = collect($module['questions'] ?? [])
                    ->filter(fn (array $question): bool => ($question['active'] ?? false)
                        && $this->matches($question['conditional_logic'] ?? null, $values))
                    ->values()
                    ->all();

                return $module;
            })
            ->values()
            ->all();
    }

    /** @param array<string, mixed>|null $condition */
    public function matches(?array $condition, array $values): bool
    {
        if (! $condition || empty($condition['question_code']) || empty($condition['operator'])) {
            return true;
        }

        $actual = $values[$condition['question_code']] ?? null;
        $expected = $condition['value'] ?? null;

        return match ($condition['operator']) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'contains' => $this->contains($actual, $expected),
            'is_empty' => $this->isEmpty($actual),
            'is_not_empty' => ! $this->isEmpty($actual),
            default => false,
        };
    }

    private function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array($expected, $actual, true);
        }

        return is_string($actual) && str_contains($actual, (string) $expected);
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
