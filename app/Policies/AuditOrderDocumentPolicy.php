<?php

namespace App\Policies;

use App\Models\AuditOrder;
use App\Models\AuditOrderDocument;
use App\Models\User;

class AuditOrderDocumentPolicy
{
    public function view(User $user, AuditOrderDocument $document): bool
    {
        return $user->can('view', $document->order);
    }

    public function create(User $user, AuditOrder $order): bool
    {
        return $user->can('plan', $order);
    }

    public function delete(User $user, AuditOrderDocument $document): bool
    {
        return ! $document->isSourceReference() && $user->can('plan', $document->order);
    }
}
