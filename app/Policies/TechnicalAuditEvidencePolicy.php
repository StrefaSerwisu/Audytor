<?php

namespace App\Policies;

use App\Models\TechnicalAuditControl;
use App\Models\TechnicalAuditEvidence;
use App\Models\User;

class TechnicalAuditEvidencePolicy
{
    public function view(User $u, TechnicalAuditEvidence $e): bool
    {
        return $u->can('view', $e->audit);
    }

    public function create(User $u, TechnicalAuditControl $c): bool
    {
        return $u->can('update', $c);
    }

    public function delete(User $u, TechnicalAuditEvidence $e): bool
    {
        return $u->can('update', $e->control) && ($e->uploaded_by === $u->id || $u->can('review', $e->audit));
    }
}
