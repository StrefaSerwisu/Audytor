<?php

namespace App\Enums;

enum TechnicalAuditStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case WaitingForClient = 'waiting_for_client';
    case Blocked = 'blocked';
    case ReadyForSubmission = 'ready_for_submission';
    case SubmittedForReview = 'submitted_for_review';
    case ChangesRequested = 'changes_requested';
    case TechnicallyApproved = 'technically_approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Szkic', self::InProgress => 'W realizacji', self::WaitingForClient => 'Oczekuje na klienta',
            self::Blocked => 'Zablokowany', self::ReadyForSubmission => 'Gotowy do wyslania',
            self::SubmittedForReview => 'W weryfikacji', self::ChangesRequested => 'Do poprawy',
            self::TechnicallyApproved => 'Zatwierdzony technicznie', self::Cancelled => 'Anulowany',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TechnicallyApproved, self::ReadyForSubmission => 'ok', self::WaitingForClient, self::ChangesRequested => 'warn', self::Blocked, self::Cancelled => 'danger', default => 'gray'
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InProgress, self::Cancelled],
            self::InProgress => [self::WaitingForClient, self::Blocked, self::ReadyForSubmission, self::Cancelled],
            self::WaitingForClient, self::Blocked, self::ChangesRequested => [self::InProgress, self::Cancelled],
            self::ReadyForSubmission => [self::SubmittedForReview, self::InProgress, self::Cancelled],
            self::SubmittedForReview => [self::ChangesRequested, self::TechnicallyApproved, self::Cancelled],
            self::TechnicallyApproved, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
