<?php

namespace App\Policies;

use App\Models\TechnicalAuditAnswer;
use App\Models\TechnicalAuditControl;
use App\Models\User;

class TechnicalAuditAnswerPolicy
{
    public function create(User $u, TechnicalAuditControl $c): bool
    {
        return $u->can('update', $c);
    }

    public function update(User $u, TechnicalAuditAnswer $a): bool
    {
        return $u->can('update', $a->control);
    }
}
