<?php

namespace App\Support;

use App\Models\Audit;
use App\Models\User;

class AuditNotifier
{
    public static function notify(User $user, Audit $audit, string $type, string $title, ?string $body, ?string $actionUrl): void
    {
        if (! $user->active) {
            return;
        }

        $user->auditNotifications()->create([
            'audit_id' => $audit->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);
    }

    public static function notifyAssignees(Audit $audit, string $type, string $title, ?string $body, ?string $actionUrl): void
    {
        $audit->loadMissing('assignees');

        foreach ($audit->assignees as $assignee) {
            if ($assignee->user) {
                self::notify($assignee->user, $audit, $type, $title, $body, $actionUrl);
            }
        }
    }
}
