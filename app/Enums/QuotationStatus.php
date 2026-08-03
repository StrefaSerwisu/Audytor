<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case InternalReview = 'internal_review';
    case InternallyApproved = 'internally_approved';
    case SentToClient = 'sent_to_client';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Robocza',
            self::Calculated => 'Obliczona',
            self::InternalReview => 'Weryfikacja wewnetrzna',
            self::InternallyApproved => 'Zatwierdzona wewnetrznie',
            self::SentToClient => 'Wyslana klientowi',
            self::Accepted => 'Zaakceptowana',
            self::Rejected => 'Odrzucona',
            self::Expired => 'Wygasla',
            self::Cancelled => 'Anulowana',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Calculated => 'info',
            self::InternalReview => 'warning',
            self::InternallyApproved => 'success',
            self::SentToClient => 'primary',
            self::Accepted => 'success',
            self::Rejected, self::Cancelled => 'danger',
            self::Expired => 'gray',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Calculated, self::Cancelled],
            self::Calculated => [self::InternalReview, self::Cancelled],
            self::InternalReview => [self::Calculated, self::InternallyApproved, self::Cancelled],
            self::InternallyApproved => [self::SentToClient, self::Cancelled],
            self::SentToClient => [self::Accepted, self::Rejected, self::Expired],
            self::Accepted, self::Rejected, self::Expired, self::Cancelled => [],
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
