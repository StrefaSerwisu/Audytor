<?php

namespace App\Support;

use App\Models\AuditOrder;
use App\Models\User;

class AuditOrderNotifier
{
    public static function notify(User $user, AuditOrder $order, string $type, string $title, ?string $body = null): void
    {
        if (! $user->active) {
            return;
        }
        $user->auditNotifications()->create(['audit_order_id' => $order->id, 'type' => $type, 'title' => $title, 'body' => $body, 'action_url' => route('delivery.audit-orders.show', $order)]);
    }

    public static function notifySales(AuditOrder $order, string $type, string $title, ?string $body = null): void
    {
        self::notify($order->salesOwner, $order, $type, $title, $body);
    }

    public static function notifyLeads(AuditOrder $order, string $type, string $title, ?string $body = null): void
    {
        foreach (collect([$order->deliveryOwner, $order->technicalLead])->filter()->unique('id') as $user) {
            self::notify($user, $order, $type, $title, $body);
        }
    }

    public static function notifyAssignees(AuditOrder $order, string $type, string $title, ?string $body = null): void
    {
        foreach ($order->assignees()->with('user')->get()->pluck('user')->filter()->unique('id') as $user) {
            self::notify($user, $order, $type, $title, $body);
        }
    }
}
