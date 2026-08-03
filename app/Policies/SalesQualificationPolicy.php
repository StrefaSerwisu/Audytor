<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SalesQualification;
use App\Models\User;

class SalesQualificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->hasAnyRole(
            UserRole::Sales,
            UserRole::TechnicalLead,
            UserRole::GlobalAdmin,
            UserRole::SuperAdmin,
        );
    }

    public function view(User $user, SalesQualification $qualification): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return ! $user->hasRole(UserRole::Sales) || $qualification->sales_owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->active && $user->hasAnyRole(UserRole::Sales, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function update(User $user, SalesQualification $qualification): bool
    {
        if (! $qualification->isEditable() || ! $this->view($user, $qualification)) {
            return false;
        }

        return $user->hasAnyRole(UserRole::GlobalAdmin, UserRole::SuperAdmin)
            || ($user->hasRole(UserRole::Sales) && $qualification->sales_owner_id === $user->id);
    }

    public function createQuotation(User $user, SalesQualification $qualification): bool
    {
        return $user->active && ($user->hasAnyRole(UserRole::GlobalAdmin, UserRole::SuperAdmin)
            || ($user->hasRole(UserRole::Sales) && $qualification->sales_owner_id === $user->id));
    }
}
