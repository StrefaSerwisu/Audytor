<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SalesQualificationQuestion;
use App\Models\User;

class SalesQualificationQuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, SalesQualificationQuestion $question): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, SalesQualificationQuestion $question): bool
    {
        return $this->canManage($user) && $question->module?->version?->isDraft();
    }

    public function delete(User $user, SalesQualificationQuestion $question): bool
    {
        return $this->update($user, $question);
    }

    private function canManage(User $user): bool
    {
        return $user->active && $user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead);
    }
}
