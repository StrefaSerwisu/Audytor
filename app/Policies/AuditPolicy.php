<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Audit;
use App\Models\User;

class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->hasAnyRole(
            UserRole::SuperAdmin,
            UserRole::GlobalAdmin,
            UserRole::TechnicalLead,
            UserRole::Auditor,
        );
    }

    public function viewAll(User $user): bool
    {
        return $user->canViewAllAudits();
    }

    public function view(User $user, Audit $audit): bool
    {
        if (! $user->active) {
            return false;
        }

        if ($this->viewAll($user)) {
            return true;
        }

        return $user->hasRole(UserRole::Auditor)
            && $audit->assignees()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Audit $audit): bool
    {
        return $this->view($user, $audit);
    }

    public function submitForReview(User $user, Audit $audit): bool
    {
        return $this->view($user, $audit);
    }

    public function review(User $user, Audit $audit): bool
    {
        if (! $user->active || ! $user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead)) {
            return false;
        }

        if ($user->canManageAllAudits()) {
            return true;
        }

        return $audit->lead_reviewer_id === $user->id;
    }

    public function generateTechnicalReport(User $user, Audit $audit): bool
    {
        return $this->review($user, $audit);
    }

    public function generateBusinessReport(User $user, Audit $audit): bool
    {
        return $this->review($user, $audit);
    }

    public function generateSalesReport(User $user, Audit $audit): bool
    {
        if (! $user->active) {
            return false;
        }

        if ($user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::Sales)) {
            return true;
        }

        return $user->hasRole(UserRole::TechnicalLead) && $audit->lead_reviewer_id === $user->id;
    }

    public function publish(User $user, Audit $audit): bool
    {
        return $this->review($user, $audit);
    }

    public function close(User $user, Audit $audit): bool
    {
        return $this->review($user, $audit);
    }
}
