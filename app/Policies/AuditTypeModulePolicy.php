<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditTypeModule;
use App\Models\User;

class AuditTypeModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, AuditTypeModule $module): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, AuditTypeModule $module): bool
    {
        return $this->canManage($user) && $module->version?->isDraft();
    }

    public function delete(User $user, AuditTypeModule $module): bool
    {
        return $this->update($user, $module);
    }

    private function canManage(User $user): bool
    {
        return $user->active && $user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead);
    }
}
