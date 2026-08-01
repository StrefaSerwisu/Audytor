<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Audit;
use App\Models\AuditNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditNotificationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->auditNotifications()
            ->with(['audit.client', 'audit.location'])
            ->latest()
            ->limit(50)
            ->get();

        return view('notifications.index', [
            'notifications' => $notifications,
            'reminders' => $this->remindersFor($user),
        ]);
    }

    public function markRead(Request $request, AuditNotification $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($notification->user_id === $user->id, 403);

        $notification->forceFill([
            'read_at' => now(),
        ])->save();

        return back()->with('status', 'Powiadomienie oznaczone jako przeczytane.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->auditNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Wszystkie powiadomienia oznaczone jako przeczytane.');
    }

    /**
     * @return array<int, array{title:string, body:string, action_url:string}>
     */
    private function remindersFor(User $user): array
    {
        if ($user->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead)) {
            return Audit::query()
                ->with(['client', 'location'])
                ->where('status', 'submitted_for_review')
                ->when($user->hasRole(UserRole::TechnicalLead), fn ($query) => $query->where('lead_reviewer_id', $user->id))
                ->latest('submitted_at')
                ->limit(5)
                ->get()
                ->map(fn (Audit $audit) => [
                    'title' => 'Audyt czeka na weryfikacje',
                    'body' => "{$audit->title} / {$audit->client->name} / {$audit->location->name}",
                    'action_url' => route('reviewer.audits.show', $audit),
                ])
                ->all();
        }

        return Audit::query()
            ->with(['client', 'location'])
            ->whereHas('assignees', fn ($query) => $query->where('user_id', $user->id))
            ->whereIn('status', ['scheduled', 'in_progress', 'needs_completion', 'changes_requested'])
            ->latest('scheduled_at')
            ->limit(5)
            ->get()
            ->map(fn (Audit $audit) => [
                'title' => $audit->status === 'changes_requested' ? 'Audyt wymaga poprawek' : 'Audyt wymaga uzupelnienia',
                'body' => "{$audit->title} / {$audit->client->name} / {$audit->location->name}",
                'action_url' => route('auditor.audits.show', $audit),
            ])
            ->all();
    }
}
