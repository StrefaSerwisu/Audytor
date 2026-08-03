<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function view(User $user, User $subject): bool
    {
        return $this->canManageUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $subject): bool
    {
        if (! $this->canManageUsers($user)) {
            return false;
        }

        return ! $subject->hasRole(UserRole::SuperAdmin) || $user->hasRole(UserRole::SuperAdmin);
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->hasRole(UserRole::SuperAdmin)
            && $user->active
            && ! $user->is($subject)
            && ! $subject->hasHistoricalRelations();
    }

    private function canManageUsers(User $user): bool
    {
        return $user->active
            && $user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin);
    }
}
