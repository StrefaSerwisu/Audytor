<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditPublication;
use App\Models\User;

class AuditPublicationPolicy
{
    public function view(User $user, AuditPublication $publication): bool
    {
        $publication->loadMissing('audit');

        if (! $user->active || $publication->audit === null) {
            return false;
        }

        if ($user->hasRole(UserRole::Client)) {
            return $user->client_id !== null
                && $publication->audit->client_id === $user->client_id
                && $publication->published_at !== null
                && ($publication->expires_at === null || $publication->expires_at->isFuture());
        }

        return $user->can('generateBusinessReport', $publication->audit);
    }

    public function updateClientDecision(User $user, AuditPublication $publication): bool
    {
        return $this->view($user, $publication) && $user->hasRole(UserRole::Client);
    }
}
