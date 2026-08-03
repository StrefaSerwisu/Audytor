<?php

namespace App\Policies;

use App\Models\AuditOrder;
use App\Models\AuditOrderAssignee;
use App\Models\User;

class AuditOrderAssigneePolicy
{
    public function create(User $user, AuditOrder $order): bool
    {
        return $user->can('plan', $order);
    }

    public function delete(User $user, AuditOrderAssignee $assignee): bool
    {
        return $user->can('plan', $assignee->order);
    }
}
