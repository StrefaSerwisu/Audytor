<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditTypeVersion;
use App\Models\User;

class AuditTypeVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, AuditTypeVersion $version): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, AuditTypeVersion $version): bool
    {
        return $this->canManage($user) && $version->isDraft();
    }

    public function delete(User $user, AuditTypeVersion $version): bool
    {
        return $this->update($user, $version);
    }

    public function publish(User $user, AuditTypeVersion $version): bool
    {
        return $this->update($user, $version);
    }

    public function archive(User $user, AuditTypeVersion $version): bool
    {
        return $this->canManage($user) && $version->status === AuditTypeVersion::STATUS_PUBLISHED;
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
