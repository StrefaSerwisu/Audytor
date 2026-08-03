<?php

namespace App\Enums;

enum AuditOrderStatus: string
{
    case Draft = 'draft';
    case AwaitingPlanning = 'awaiting_planning';
    case Planning = 'planning';
    case AwaitingAccess = 'awaiting_access';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Szkic', self::AwaitingPlanning => 'Oczekuje na planowanie',
            self::Planning => 'Planowanie', self::AwaitingAccess => 'Oczekuje na dostepy',
            self::Ready => 'Gotowe', self::Scheduled => 'Zaplanowane',
            self::InProgress => 'W realizacji', self::Cancelled => 'Anulowane',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready, self::Scheduled => 'ok',
            self::AwaitingPlanning, self::Planning, self::AwaitingAccess => 'warn',
            self::Cancelled => 'danger',
            default => 'gray',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::AwaitingPlanning, self::Cancelled],
            self::AwaitingPlanning => [self::Planning, self::Cancelled],
            self::Planning => [self::AwaitingAccess, self::Ready, self::Cancelled],
            self::AwaitingAccess => [self::Planning, self::Ready, self::Cancelled],
            self::Ready => [self::Planning, self::Scheduled, self::Cancelled],
            self::Scheduled => [self::Planning, self::InProgress, self::Cancelled],
            self::InProgress, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
