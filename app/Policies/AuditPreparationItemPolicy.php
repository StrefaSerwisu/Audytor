<?php

namespace App\Policies;

use App\Models\AuditPreparationItem;
use App\Models\User;

class AuditPreparationItemPolicy
{
    public function update(User $user, AuditPreparationItem $item): bool
    {
        return $user->can('plan', $item->order);
    }
}
