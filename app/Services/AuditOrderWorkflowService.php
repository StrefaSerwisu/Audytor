<?php

namespace App\Services;

use App\Enums\AuditOrderStatus;
use App\Models\AuditOrder;
use App\Models\User;
use App\Support\AuditLogService;
use App\Support\AuditOrderNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditOrderWorkflowService
{
    public function __construct(private readonly TechnicalAuditCreationService $technicalAuditCreation) {}

    /** @param array<string, mixed> $data */
    public function transition(AuditOrder $order, AuditOrderStatus $target, User $actor, array $data = []): AuditOrder
    {
        if (! $actor->can('transition', $order)) {
            throw new AuthorizationException('Brak uprawnien do zmiany statusu zlecenia.');
        }
        if (! $order->status->canTransitionTo($target)) {
            throw ValidationException::withMessages(['status' => 'Niedozwolona zmiana statusu.']);
        }
        if (in_array($target, [AuditOrderStatus::Ready, AuditOrderStatus::Scheduled], true)) {
            $blockers = $order->readinessBlockers();
            if ($blockers !== []) {
                AuditOrderNotifier::notifyLeads($order, 'audit_order.blocked', 'Zlecenie wymaga uzupelnienia', implode(' ', $blockers));
                throw ValidationException::withMessages(['readiness' => implode(' ', $blockers)]);
            }
        }
        if ($target === AuditOrderStatus::Scheduled && $order->planned_start_at?->isPast() && blank($data['justification'] ?? null)) {
            throw ValidationException::withMessages(['justification' => 'Termin z przeszlosci wymaga uzasadnienia.']);
        }
        if ($target === AuditOrderStatus::Cancelled && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Podaj powod anulowania.']);
        }
        if ($target === AuditOrderStatus::InProgress) {
            $this->technicalAuditCreation->create($order, $actor);

            return $order->refresh();
        }

        return DB::transaction(function () use ($order, $target, $actor, $data): AuditOrder {
            $old = $order->status;
            $changes = ['status' => $target];
            if ($target === AuditOrderStatus::Cancelled) {
                $changes['cancellation_reason'] = $data['reason'];
            }
            if (filled($data['justification'] ?? null)) {
                $changes['planning_notes'] = trim($order->planning_notes."\n".$data['justification']);
            }
            $order->update($changes);
            AuditLogService::record('audit_order.status_changed', $order, oldValues: ['status' => $old], newValues: ['status' => $target], metadata: $order->logMetadata(['actor_id' => $actor->id, 'status_from' => $old, 'status_to' => $target]));
            AuditLogService::record(match ($target) {
                AuditOrderStatus::Planning => $old === AuditOrderStatus::AwaitingAccess ? 'audit_order.planning_resumed' : 'audit_order.planning_started',
                AuditOrderStatus::AwaitingAccess => 'audit_order.awaiting_access', AuditOrderStatus::Ready => 'audit_order.ready',
                AuditOrderStatus::Scheduled => 'audit_order.scheduled', AuditOrderStatus::InProgress => 'audit_order.started',
                AuditOrderStatus::Cancelled => 'audit_order.cancelled', default => 'audit_order.status_changed',
            }, $order, metadata: $order->logMetadata(['status_from' => $old, 'status_to' => $target]));
            if ($target === AuditOrderStatus::Ready) {
                AuditOrderNotifier::notifySales($order, 'audit_order.ready', 'Zlecenie gotowe do zaplanowania', $order->number);
            }

            return $order->refresh();
        });
    }
}
