<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveAuditRequest;
use App\Http\Requests\RequestAuditChangesRequest;
use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\User;
use App\Support\AuditLogService;
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

        $audits = Audit::query()
            ->with(['client', 'location', 'leadReviewer', 'answers'])
            ->whereIn('status', ['submitted_for_review', 'changes_requested', 'technically_approved', 'published_to_client'])
            ->when(! $user->canManageAllAudits(), fn ($query) => $query->where('lead_reviewer_id', $user->id))
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

        abort_unless($user->can('review', $audit), 403);

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

    public function approve(ApproveAuditRequest $request, Audit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('review', $audit), 403);
        $this->ensureSubmittedForReview($audit);

        $validated = $request->validated();

        $audit->reviews()->create([
            'reviewer_id' => $user->id,
            'decision' => 'approved',
            'notes' => $validated['notes'] ?? null,
        ]);

        $oldStatus = $audit->status;

        $audit->forceFill([
            'status' => 'technically_approved',
            'approved_at' => now(),
        ])->save();

        AuditLogService::record(
            'audit.technically_approved',
            $audit,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $audit->status, 'notes' => $validated['notes'] ?? null],
        );

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

    public function requestChanges(RequestAuditChangesRequest $request, Audit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('review', $audit), 403);
        $this->ensureSubmittedForReview($audit);

        $validated = $request->validated();

        $audit->reviews()->create([
            'reviewer_id' => $user->id,
            'decision' => 'changes_requested',
            'notes' => $validated['notes'],
        ]);

        $oldStatus = $audit->status;

        $audit->forceFill([
            'status' => 'changes_requested',
            'approved_at' => null,
        ])->save();

        AuditLogService::record(
            'audit.changes_requested',
            $audit,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $audit->status, 'notes' => $validated['notes']],
        );

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
}
