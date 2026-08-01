<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditFollowUpTask;
use App\Models\User;

class AuditFollowUpTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->hasAnyRole(
            UserRole::SuperAdmin,
            UserRole::GlobalAdmin,
            UserRole::TechnicalLead,
            UserRole::Sales,
        );
    }

    public function view(User $user, AuditFollowUpTask $task): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AuditFollowUpTask $task): bool
    {
        return $this->view($user, $task);
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
