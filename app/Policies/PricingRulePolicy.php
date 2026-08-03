<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PricingRule;
use App\Models\User;

class PricingRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, PricingRule $rule): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, PricingRule $rule): bool
    {
        return $this->canManage($user) && $rule->version?->isDraft();
    }

    public function delete(User $user, PricingRule $rule): bool
    {
        return $this->update($user, $rule);
    }

    private function canManage(User $user): bool
    {
        return $user->active && $user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead);
    }
}
