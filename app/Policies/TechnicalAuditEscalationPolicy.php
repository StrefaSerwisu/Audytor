<?php

namespace App\Policies;

use App\Models\TechnicalAudit;
use App\Models\TechnicalAuditEscalation;
use App\Models\User;

class TechnicalAuditEscalationPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->active;
    }

    public function view(User $u, TechnicalAuditEscalation $e): bool
    {
        return $u->can('review', $e->audit) || ($u->can('view', $e->audit) && $e->created_by === $u->id);
    }

    public function create(User $u, TechnicalAudit $a): bool
    {
        return $u->can('execute', $a);
    }

    public function respond(User $u, TechnicalAuditEscalation $e): bool
    {
        return $u->can('review', $e->audit);
    }
}
