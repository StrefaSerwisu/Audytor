<?php

namespace App\Policies;

use App\Enums\TechnicalAuditStatus;
use App\Enums\UserRole;
use App\Models\TechnicalAudit;
use App\Models\User;

class TechnicalAuditPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->active && $u->hasAnyRole(UserRole::Auditor, UserRole::TechnicalLead, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function view(User $u, TechnicalAudit $a): bool
    {
        return $this->viewAny($u) && (! $u->hasRole(UserRole::Auditor) || $a->order->assignees()->where('user_id', $u->id)->whereIn('assignment_role', ['auditor', 'supporting_engineer'])->exists());
    }

    public function execute(User $u, TechnicalAudit $a): bool
    {
        return $this->view($u, $a) && $u->hasAnyRole(UserRole::Auditor, UserRole::TechnicalLead, UserRole::GlobalAdmin, UserRole::SuperAdmin) && in_array($a->status, [TechnicalAuditStatus::InProgress, TechnicalAuditStatus::ChangesRequested], true);
    }

    public function review(User $u, TechnicalAudit $a): bool
    {
        return $this->view($u, $a) && $u->hasAnyRole(UserRole::TechnicalLead, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function transition(User $u, TechnicalAudit $a): bool
    {
        return $u->hasRole(UserRole::Auditor)
            ? $this->view($u, $a) && in_array($a->status, [TechnicalAuditStatus::InProgress, TechnicalAuditStatus::WaitingForClient, TechnicalAuditStatus::Blocked, TechnicalAuditStatus::ReadyForSubmission, TechnicalAuditStatus::ChangesRequested], true)
            : $this->review($u, $a);
    }
}
