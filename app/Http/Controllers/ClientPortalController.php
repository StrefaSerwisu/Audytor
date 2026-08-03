<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateClientReportFeedbackRequest;
use App\Http\Requests\UpdateClientReportStatusRequest;
use App\Models\AuditAnswer;
use App\Models\AuditPublication;
use App\Models\AuditQuestion;
use App\Models\User;
use App\Support\AuditLogService;
use App\Support\FollowUpTaskBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $publications = $this->visiblePublications($user)
            ->latest('published_at')
            ->get();

        return view('client-portal.index', [
            'publications' => $publications,
        ]);
    }

    public function show(Request $request, AuditPublication $publication): View
    {
        /** @var User $user */
        $user = $request->user();

        $publication->loadMissing('audit');

        if (
            $user->hasRole(UserRole::Client)
            && $publication->audit?->client_id === $user->client_id
            && ($publication->published_at === null || $publication->expires_at?->isPast())
        ) {
            abort(404);
        }

        abort_unless($user->can('view', $publication), 403);

        $publication->load([
            'publisher',
            'audit.client',
            'audit.location',
            'audit.template',
            'audit.leadReviewer',
            'audit.answers',
            'followUpTasks.owner',
            'audit.selectedModules' => fn ($query) => $query->orderBy('sort_order'),
            'audit.selectedModules.module.questions' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('sort_order')
                ->with('recommendations'),
        ]);

        $recommendations = $this->recommendationsFor($publication);

        return view('client-portal.show', [
            'publication' => $publication,
            'audit' => $publication->audit,
            'riskLevels' => AuditAnswer::RISK_LEVELS,
            'riskSummary' => $this->riskSummary($publication->audit),
            'clientStatuses' => AuditPublication::CLIENT_STATUSES,
            'recommendations' => $recommendations,
            'acceptedRecommendationKeys' => collect($publication->accepted_recommendations_json ?? []),
            'followUpTasks' => $publication->followUpTasks->where('client_visible', true),
        ]);
    }

    public function updateStatus(UpdateClientReportStatusRequest $request, AuditPublication $publication): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('updateClientDecision', $publication), 403);

        $validated = $request->validated();
        $oldStatus = $publication->client_status;

        $publication->forceFill([
            'client_status' => $validated['client_status'],
            'client_status_updated_at' => now(),
        ])->save();

        AuditLogService::record(
            'client_report.status_updated',
            $publication,
            oldValues: ['client_status' => $oldStatus],
            newValues: ['client_status' => $publication->client_status],
        );

        return back()->with('status', 'Status raportu zostal zapisany.');
    }

    public function updateFeedback(UpdateClientReportFeedbackRequest $request, AuditPublication $publication): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('updateClientDecision', $publication), 403);

        $validated = $request->validated();
        $oldValues = $publication->only(['client_comment', 'accepted_recommendations_json']);

        $publication->forceFill([
            'client_comment' => $validated['client_comment'] ?? null,
            'accepted_recommendations_json' => array_values(array_unique($validated['accepted_recommendations'] ?? [])),
            'client_feedback_at' => now(),
        ])->save();

        AuditLogService::record(
            'client_report.feedback_updated',
            $publication,
            oldValues: $oldValues,
            newValues: $publication->only(['client_comment', 'accepted_recommendations_json']),
        );

        FollowUpTaskBuilder::syncFromPublication($publication);

        return back()->with('status', 'Komentarz klienta zostal zapisany.');
    }

    private function visiblePublications(User $user)
    {
        return AuditPublication::query()
            ->whereNotNull('published_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->whereHas('audit', fn ($query) => $query->where('client_id', $user->client_id))
            ->with([
                'audit.client',
                'audit.location',
                'audit.template',
            ]);
    }

    /**
     * @return array<string, int>
     */
    private function riskSummary($audit): array
    {
        $summary = array_fill_keys(array_keys(AuditAnswer::RISK_LEVELS), 0);

        foreach ($audit->answers as $answer) {
            if ($answer->risk_level && array_key_exists($answer->risk_level, $summary)) {
                $summary[$answer->risk_level]++;
            }
        }

        return $summary;
    }

    private function recommendationsFor(AuditPublication $publication)
    {
        $audit = $publication->audit;
        $answers = $audit->answers->keyBy('audit_question_id');

        return $audit->selectedModules
            ->flatMap(fn ($selectedModule) => $selectedModule->module?->questions ?? collect())
            ->flatMap(function (AuditQuestion $question) use ($answers) {
                $answer = $answers->get($question->id);
                $items = collect();

                if ($answer?->recommendation_text) {
                    $items->push([
                        'key' => "answer:{$answer->id}",
                        'title' => 'Rekomendacja audytora',
                        'source' => $question->question,
                        'text' => $answer->recommendation_text,
                    ]);
                }

                foreach ($question->recommendations as $recommendation) {
                    $items->push([
                        'key' => "recommendation:{$recommendation->id}",
                        'title' => $recommendation->title,
                        'source' => $question->question,
                        'text' => $recommendation->recommendation_text,
                    ]);
                }

                return $items;
            })
            ->values();
    }
}
