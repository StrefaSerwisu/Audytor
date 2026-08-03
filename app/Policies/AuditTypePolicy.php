<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditType;
use App\Models\AuditTypeVersion;
use App\Models\User;

class AuditTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, AuditType $auditType): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, AuditType $auditType): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, AuditType $auditType): bool
    {
        return $this->canManage($user)
            && ! $auditType->versions()->whereIn('status', [AuditTypeVersion::STATUS_PUBLISHED, AuditTypeVersion::STATUS_ARCHIVED])->exists();
    }

    private function canManage(User $user): bool
    {
        return $user->active && $user->hasAnyRole(
            UserRole::SuperAdmin,
            UserRole::GlobalAdmin,
            UserRole::TechnicalLead,
        );
    }
}
