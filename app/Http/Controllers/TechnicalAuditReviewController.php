<?php

namespace App\Http\Controllers;

use App\Enums\TechnicalAuditStatus;
use App\Models\TechnicalAudit;
use App\Models\TechnicalAuditControl;
use App\Services\TechnicalAuditWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicalAuditReviewController extends Controller
{
    public function index(Request $r): View
    {
        abort_unless($r->user()->can('viewAny', TechnicalAudit::class), 403);

        return view('technical-review.audits.index', ['audits' => TechnicalAudit::with('client')->whereIn('status', [TechnicalAuditStatus::SubmittedForReview, TechnicalAuditStatus::ChangesRequested])->latest()->get()]);
    }

    public function show(Request $r, TechnicalAudit $technicalAudit): View
    {
        abort_unless($r->user()->can('review', $technicalAudit), 403);
        $technicalAudit->load(['client', 'modules.controls.answer.evidence', 'escalations']);

        return view('technical-review.audits.show', ['audit' => $technicalAudit]);
    }

    public function control(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditControl $control): RedirectResponse
    {
        abort_unless($control->technical_audit_id === $technicalAudit->id && $r->user()->can('review', $technicalAudit), 403);
        $d = $r->validate(['comment' => ['nullable', 'string', 'max:5000'], 'proposed_risk_level' => ['nullable', 'string'], 'proposed_recommendation' => ['nullable', 'string', 'max:20000'], 'changes_requested' => ['nullable', 'boolean']]);
        $control->answer?->update(collect($d)->except('changes_requested')->all());
        if ($r->boolean('changes_requested')) {
            $control->update(['status' => 'in_progress']);
        }

return back()->with('status', 'Weryfikacja kontroli zapisana.');
    }

    public function transition(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditStatus $status, TechnicalAuditWorkflowService $s): RedirectResponse
    {
        $d = $r->validate(['comment' => ['nullable', 'string', 'max:5000']]);
        $s->transition($technicalAudit, $status, $r->user(), $d);

        return back();
    }
}
