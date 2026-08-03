<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Support\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationWorkflowService
{
    /** @param array<string, mixed> $data */
    public function transition(Quotation $quotation, QuotationStatus $to, User $actor, array $data = []): void
    {
        $from = $quotation->status;
        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw ValidationException::withMessages(['status' => "Niedozwolona zmiana statusu {$from->value} -> {$to->value}."]);
        }

        $ability = match ($to) {
            QuotationStatus::InternalReview => 'sendForReview',
            QuotationStatus::Calculated => 'returnForChanges',
            QuotationStatus::InternallyApproved => 'approveInternally',
            QuotationStatus::SentToClient => 'sendToClient',
            QuotationStatus::Accepted, QuotationStatus::Rejected => 'recordClientDecision',
            QuotationStatus::Expired => 'expire',
            QuotationStatus::Cancelled => 'cancel',
            default => throw ValidationException::withMessages(['status' => 'Status jest zarzadzany przez kalkulator.']),
        };

        if (! $actor->can($ability, $quotation)) {
            throw new AuthorizationException('Brak uprawnien do tej zmiany statusu wyceny.');
        }

        $this->validateData($from, $to, $quotation, $data);

        DB::transaction(function () use ($quotation, $from, $to, $actor, $data): void {
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['status' => 'Status wyceny zmienil sie w trakcie operacji.']);
            }

            $values = ['status' => $to];
            $event = match ($to) {
                QuotationStatus::InternalReview => 'quotation.sent_for_review',
                QuotationStatus::Calculated => 'quotation.returned_for_changes',
                QuotationStatus::InternallyApproved => 'quotation.internally_approved',
                QuotationStatus::SentToClient => 'quotation.sent_to_client',
                QuotationStatus::Accepted => 'quotation.accepted',
                QuotationStatus::Rejected => 'quotation.rejected',
                QuotationStatus::Expired => 'quotation.expired',
                QuotationStatus::Cancelled => 'quotation.cancelled',
                default => throw new \LogicException('Nieobslugiwany status wyceny.'),
            };

            if ($to === QuotationStatus::InternallyApproved) {
                $values += ['internally_approved_at' => now(), 'internally_approved_by' => $actor->id];
            } elseif ($to === QuotationStatus::SentToClient) {
                $values += ['sent_at' => now(), 'sent_by' => $actor->id];
            } elseif ($to === QuotationStatus::Accepted) {
                $values += [
                    'accepted_at' => $data['accepted_at'] ?? now(), 'accepted_by' => $data['accepted_by'],
                    'purchase_order_number' => $data['purchase_order_number'] ?? null,
                    'acceptance_comment' => $data['comment'] ?? null,
                ];
            } elseif ($to === QuotationStatus::Rejected) {
                $values += ['rejected_at' => now(), 'rejection_reason' => $data['reason']];
            } elseif ($to === QuotationStatus::Cancelled) {
                $values += ['cancellation_reason' => $data['reason']];
            }

            $locked->update($values);
            AuditLogService::record($event, $locked, oldValues: ['status' => $from], newValues: ['status' => $to], metadata: [
                'quotation_id' => $locked->id, 'qualification_id' => $locked->sales_qualification_id,
                'number' => $locked->number, 'status_from' => $from, 'status_to' => $to,
                'total_hours' => $locked->total_hours, 'net_price' => $locked->net_price,
                'comment' => isset($data['comment']) ? mb_substr((string) $data['comment'], 0, 250) : null,
                'reason' => isset($data['reason']) ? mb_substr((string) $data['reason'], 0, 250) : null,
            ]);
        });

        $quotation->refresh();
    }

    /** @param array<string, mixed> $data */
    private function validateData(QuotationStatus $from, QuotationStatus $to, Quotation $quotation, array $data): void
    {
        if ($from === QuotationStatus::InternalReview && $to === QuotationStatus::Calculated && blank($data['comment'] ?? null)) {
            throw ValidationException::withMessages(['comment' => 'Komentarz do cofniecia jest wymagany.']);
        }
        if ($to === QuotationStatus::Accepted && blank($data['accepted_by'] ?? null)) {
            throw ValidationException::withMessages(['accepted_by' => 'Podaj osobe akceptujaca po stronie klienta.']);
        }
        if (in_array($to, [QuotationStatus::Rejected, QuotationStatus::Cancelled], true) && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Powod jest wymagany.']);
        }
        if ($to === QuotationStatus::Expired && (! $quotation->valid_until || $quotation->valid_until->isFuture())) {
            throw ValidationException::withMessages(['status' => 'Wycena nie przekroczyla terminu waznosci.']);
        }
    }
}
