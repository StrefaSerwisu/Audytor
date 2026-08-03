<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\TechnicalAudit;
use App\Models\TechnicalAuditControl;
use App\Models\TechnicalAuditEscalation;
use App\Services\TechnicalAuditExecutionService;
use App\Services\TechnicalAuditProgressService;
use App\Support\AuditLogService;
use App\Support\TechnicalAuditNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TechnicalAuditEscalationController extends Controller
{
    public function index(Request $r): View
    {
        $u = $r->user();
        $q = TechnicalAuditEscalation::with(['audit.client', 'control', 'creator', 'assignee'])->latest();
        if ($u->hasRole(UserRole::Auditor)) {
            $q->where('created_by', $u->id)->whereHas('audit', fn ($q) => $q->visibleTo($u));
        }

return view('engineer.escalations.index', ['escalations' => $q->get()]);
    }

    public function store(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditControl $control, TechnicalAuditExecutionService $execution): RedirectResponse
    {
        abort_unless($control->technical_audit_id === $technicalAudit->id && $r->user()->can('create', [TechnicalAuditEscalation::class, $technicalAudit]), 403);
        $d = $r->validate(['reason' => ['required', 'string', 'max:5000'], 'question' => ['nullable', 'string', 'max:5000'], 'priority' => ['required', Rule::in(array_keys(TechnicalAuditEscalation::PRIORITIES))]]);
        $e = $control->escalations()->create([...$d, 'technical_audit_id' => $technicalAudit->id, 'created_by' => $r->user()->id]);
        $execution->setStatus($control, 'requires_consultation', $r->user());
        AuditLogService::record('technical_audit.escalation_created', $technicalAudit, metadata: $technicalAudit->logMetadata(['control_id' => $control->id, 'escalation_id' => $e->id, 'priority' => $e->priority]));
        TechnicalAuditNotifier::notify($technicalAudit->technicalLead, $technicalAudit, 'technical_audit.escalation_created', 'Nowa eskalacja', $technicalAudit->number);

        return back()->with('status', 'Eskalacja zostala utworzona.');
    }

    public function respond(Request $r, TechnicalAuditEscalation $escalation, TechnicalAuditProgressService $p): RedirectResponse
    {
        abort_unless($r->user()->can('respond', $escalation), 403);
        $d = $r->validate(['response' => ['required', 'string', 'max:10000'], 'assigned_to' => ['nullable', 'exists:users,id'], 'resolve' => ['nullable', 'boolean']]);
        $resolved = $r->boolean('resolve');
        $escalation->update(['response' => $d['response'], 'assigned_to' => $d['assigned_to'] ?? $escalation->assigned_to, 'status' => $resolved ? 'resolved' : 'answered', 'resolved_by' => $resolved ? $r->user()->id : null, 'resolved_at' => $resolved ? now() : null]);
        if ($resolved) {
            $escalation->control->update(['status' => 'in_progress']);
        }AuditLogService::record($resolved ? 'technical_audit.escalation_resolved' : 'technical_audit.escalation_answered', $escalation->audit, metadata: $escalation->audit->logMetadata(['escalation_id' => $escalation->id]));
        TechnicalAuditNotifier::notify($escalation->creator, $escalation->audit, 'technical_audit.escalation_answered', 'Lider odpowiedzial na eskalacje');
        $p->refresh($escalation->audit);

        return back();
    }
}
