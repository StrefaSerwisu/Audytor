<?php

namespace App\Policies;

use App\Models\TechnicalAuditModule;
use App\Models\User;

class TechnicalAuditModulePolicy
{
    public function view(User $u, TechnicalAuditModule $m): bool
    {
        return $u->can('view', $m->audit);
    }
}
