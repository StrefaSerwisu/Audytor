<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditOrder;
use App\Models\User;

class AuditOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->hasAnyRole(UserRole::Sales, UserRole::Auditor, UserRole::TechnicalLead, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function view(User $user, AuditOrder $order): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }
        if ($user->hasRole(UserRole::Sales)) {
            return $order->sales_owner_id === $user->id;
        }
        if ($user->hasRole(UserRole::Auditor)) {
            return $order->assignees()->where('user_id', $user->id)->exists();
        }

        return true;
    }

    public function plan(User $user, AuditOrder $order): bool
    {
        return $this->view($user, $order) && $user->hasAnyRole(UserRole::TechnicalLead, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function transition(User $user, AuditOrder $order): bool
    {
        return $this->plan($user, $order);
    }
}
