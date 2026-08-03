<?php

namespace App\Policies;

use App\Models\QualificationAttachment;
use App\Models\User;

class QualificationAttachmentPolicy
{
    public function download(User $user, QualificationAttachment $attachment): bool
    {
        return $user->can('view', $attachment->qualification);
    }

    public function delete(User $user, QualificationAttachment $attachment): bool
    {
        return $user->can('update', $attachment->qualification);
    }
}
