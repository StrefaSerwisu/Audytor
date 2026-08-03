<?php

namespace App\Services;

use App\Enums\TechnicalAuditStatus;
use App\Models\TechnicalAudit;
use App\Models\User;
use App\Support\AuditLogService;
use App\Support\TechnicalAuditNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicalAuditWorkflowService
{
    public function __construct(private readonly TechnicalAuditProgressService $progress) {}

    public function transition(TechnicalAudit $a, TechnicalAuditStatus $target, User $u, array $data = []): TechnicalAudit
    {
        if (! $u->can('transition', $a)) {
            throw new AuthorizationException('Brak uprawnien.');
        }if (! $a->status->canTransitionTo($target)) {
            throw ValidationException::withMessages(['status' => 'Niedozwolona zmiana statusu.']);
        }if (in_array($target, [TechnicalAuditStatus::WaitingForClient, TechnicalAuditStatus::Blocked, TechnicalAuditStatus::ChangesRequested, TechnicalAuditStatus::Cancelled], true) && blank($data['comment'] ?? $data['reason'] ?? null)) {
            throw ValidationException::withMessages(['comment' => 'Wymagany komentarz lub powod.']);
        }if (in_array($target, [TechnicalAuditStatus::ReadyForSubmission, TechnicalAuditStatus::TechnicallyApproved], true)) {
            if ($b = $this->progress->blockers($a)) {
                throw ValidationException::withMessages(['readiness' => implode(' ', $b)]);
            }
        }if (in_array($target, [TechnicalAuditStatus::ChangesRequested, TechnicalAuditStatus::TechnicallyApproved], true) && ! $u->can('review', $a)) {
            throw new AuthorizationException('Tylko lider moze wykonac weryfikacje.');
        }

return DB::transaction(function () use ($a, $target, $u) {
            $old = $a->status;
            $changes = ['status' => $target];
            if ($target === TechnicalAuditStatus::SubmittedForReview) {
                $changes += ['submitted_at' => now(), 'submitted_by' => $u->id];
            }if ($target === TechnicalAuditStatus::TechnicallyApproved) {
                $changes['completed_at'] = now();
            }$a->update($changes);
            $event = match ($target) {
                TechnicalAuditStatus::WaitingForClient => 'technical_audit.waiting_for_client',TechnicalAuditStatus::Blocked => 'technical_audit.blocked',TechnicalAuditStatus::ReadyForSubmission => 'technical_audit.ready_for_submission',TechnicalAuditStatus::SubmittedForReview => 'technical_audit.submitted_for_review',TechnicalAuditStatus::ChangesRequested => 'technical_audit.changes_requested',TechnicalAuditStatus::TechnicallyApproved => 'technical_audit.technically_approved',default => 'technical_audit.status_changed'
            };
            AuditLogService::record($event, $a, metadata: $a->logMetadata(['status_from' => $old, 'status_to' => $target]));
            if ($target === TechnicalAuditStatus::SubmittedForReview) {
                TechnicalAuditNotifier::notify($a->technicalLead, $a, $event, 'Audyt czeka na weryfikacje');
            }if ($target === TechnicalAuditStatus::ChangesRequested) {
                TechnicalAuditNotifier::assignees($a, $event, 'Audyt zostal zwrocony do poprawy');
            }if ($target === TechnicalAuditStatus::TechnicallyApproved) {
                TechnicalAuditNotifier::notify($a->order->salesOwner, $a, $event, 'Audyt zatwierdzony technicznie');
                if ($a->deliveryOwner) {
                    TechnicalAuditNotifier::notify($a->deliveryOwner, $a, $event, 'Audyt zatwierdzony technicznie');
                }
            }

return $a->refresh();
        });
    }
}
