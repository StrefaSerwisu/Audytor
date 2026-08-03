<?php

namespace App\Support;

use App\Models\TechnicalAudit;
use App\Models\User;

class TechnicalAuditNotifier
{
    public static function notify(User $user, TechnicalAudit $audit, string $type, string $title, ?string $body = null): void
    {
        if (! $user->active) {
            return;
        }$user->auditNotifications()->create(['audit_order_id' => $audit->audit_order_id, 'technical_audit_id' => $audit->id, 'type' => $type, 'title' => $title, 'body' => $body, 'action_url' => route('engineer.audits.show', $audit)]);
    }

    public static function assignees(TechnicalAudit $audit, string $type, string $title): void
    {
        foreach ($audit->order->assignees()->with('user')->get()->pluck('user')->filter()->unique('id') as $u) {
            self::notify($u, $audit, $type, $title, $audit->number);
        }
    }
}
