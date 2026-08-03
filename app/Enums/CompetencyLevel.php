<?php

namespace App\Enums;

enum CompetencyLevel: string
{
    case Junior = 'junior';
    case Regular = 'regular';
    case Senior = 'senior';
    case Specialist = 'specialist';
    case TechnicalLead = 'technical_lead';

    public function label(): string
    {
        return match ($this) {
            self::Junior => 'Junior',
            self::Regular => 'Regular',
            self::Senior => 'Senior',
            self::Specialist => 'Specjalista',
            self::TechnicalLead => 'Lider techniczny',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $level): array => [$level->value => $level->label()])
            ->all();
    }

    public function meets(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Junior => 1, self::Regular => 2, self::Senior => 3,
            self::Specialist => 4, self::TechnicalLead => 5,
        };
    }
}
