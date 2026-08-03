<?php

namespace App\Policies;

use App\Models\TechnicalAuditControl;
use App\Models\User;

class TechnicalAuditControlPolicy
{
    public function view(User $u, TechnicalAuditControl $c): bool
    {
        return $u->can('view', $c->audit);
    }

    public function update(User $u, TechnicalAuditControl $c): bool
    {
        return $u->can('execute', $c->audit) && (! $c->assigned_to || $c->assigned_to === $u->id || $u->can('review', $c->audit));
    }
}
