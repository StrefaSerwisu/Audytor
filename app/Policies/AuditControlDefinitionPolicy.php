<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditControlDefinition;
use App\Models\User;

class AuditControlDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, AuditControlDefinition $control): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, AuditControlDefinition $control): bool
    {
        return $this->canManage($user) && $control->module?->version?->isDraft();
    }

    public function delete(User $user, AuditControlDefinition $control): bool
    {
        return $this->update($user, $control);
    }

    private function canManage(User $user): bool
    {
        return $user->active && $user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead);
    }
}
