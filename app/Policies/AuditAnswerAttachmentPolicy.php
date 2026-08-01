<?php

namespace App\Policies;

use App\Models\AuditAnswerAttachment;
use App\Models\User;

class AuditAnswerAttachmentPolicy
{
    public function download(User $user, AuditAnswerAttachment $attachment): bool
    {
        $attachment->loadMissing('audit');

        return $attachment->audit !== null && $user->can('view', $attachment->audit);
    }

    public function delete(User $user, AuditAnswerAttachment $attachment): bool
    {
        return $this->download($user, $attachment);
    }
}
