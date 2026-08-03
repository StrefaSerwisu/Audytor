<?php

namespace App\Enums;

enum SalesQualificationStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case WaitingForClient = 'waiting_for_client';
    case Completed = 'completed';
    case ReadyForPricing = 'ready_for_pricing';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Robocza',
            self::InProgress => 'W trakcie',
            self::WaitingForClient => 'Oczekiwanie na klienta',
            self::Completed => 'Zakonczona',
            self::ReadyForPricing => 'Gotowa do wyceny',
            self::Cancelled => 'Anulowana',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [
            $status->value => $status->label(),
        ])->all();
    }
}
