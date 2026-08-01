<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\User;
use App\Support\AuditNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicalReviewController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canReviewAudits($user), 403);

        $audits = Audit::query()
            ->with(['client', 'location', 'leadReviewer', 'answers'])
            ->whereIn('status', ['submitted_for_review', 'changes_requested', 'technically_approved', 'published_to_client'])
            ->when(! $this->canReviewAllAudits($user), fn ($query) => $query->where('lead_reviewer_id', $user->id))
            ->latest('submitted_at')
            ->get();

        return view('reviewer.index', [
            'audits' => $audits,
        ]);
    }

    public function show(Request $request, Audit $audit): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canReviewAudit($user, $audit), 403);

        $audit->load([
            'client',
            'location',
            'template',
            'leadReviewer',
            'publications.publisher',
            'reviews.reviewer',
            'answers.attachments',
            'selectedModules' => fn ($query) => $query->orderBy('sort_order'),
            'selectedModules.module.questions' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('sort_order')
                ->with('recommendations'),
        ]);

        return view('reviewer.show', [
            'audit' => $audit,
            'answersByQuestion' => $audit->answers->keyBy('audit_question_id'),
            'riskLevels' => AuditAnswer::RISK_LEVELS,
        ]);
    }

    public function approve(Request $request, Audit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canReviewAudit($user, $audit), 403);
        $this->ensureSubmittedForReview($audit);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $audit->reviews()->create([
            'reviewer_id' => $user->id,
            'decision' => 'approved',
            'notes' => $validated['notes'] ?? null,
        ]);

        $audit->forceFill([
            'status' => 'technically_approved',
            'approved_at' => now(),
        ])->save();

        AuditNotifier::notifyAssignees(
            $audit,
            'audit_approved',
            'Audyt zatwierdzony technicznie',
            "{$audit->title} jest gotowy do raportowania.",
            route('auditor.audits.show', $audit),
        );

        return redirect()
            ->route('reviewer.audits.show', $audit)
            ->with('status', 'Audyt zatwierdzony technicznie.');
    }

    public function requestChanges(Request $request, Audit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canReviewAudit($user, $audit), 403);
        $this->ensureSubmittedForReview($audit);

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $audit->reviews()->create([
            'reviewer_id' => $user->id,
            'decision' => 'changes_requested',
            'notes' => $validated['notes'],
        ]);

        $audit->forceFill([
            'status' => 'changes_requested',
            'approved_at' => null,
        ])->save();

        AuditNotifier::notifyAssignees(
            $audit,
            'changes_requested',
            'Audyt zwrocony do poprawek',
            $validated['notes'],
            route('auditor.audits.show', $audit),
        );

        return redirect()
            ->route('reviewer.audits.show', $audit)
            ->with('status', 'Audyt zwrocony do poprawek.');
    }

    private function ensureSubmittedForReview(Audit $audit): void
    {
        abort_unless($audit->status === 'submitted_for_review', 409);
    }

    private function canReviewAudit(User $user, Audit $audit): bool
    {
        if (! $this->canReviewAudits($user)) {
            return false;
        }

        if ($this->canReviewAllAudits($user)) {
            return true;
        }

        return $audit->lead_reviewer_id === $user->id;
    }

    private function canReviewAudits(User $user): bool
    {
        return $user->active && in_array($user->role, [
            'super_admin',
            'global_admin',
            'technical_lead',
        ], true);
    }

    private function canReviewAllAudits(User $user): bool
    {
        return $user->active && in_array($user->role, [
            'super_admin',
            'global_admin',
        ], true);
    }
}
