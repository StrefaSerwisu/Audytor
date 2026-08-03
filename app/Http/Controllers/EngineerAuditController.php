<?php

namespace App\Http\Controllers;

use App\Contracts\EvidenceScanner;
use App\Enums\TechnicalAuditStatus;
use App\Http\Requests\UpdateTechnicalAuditAnswerRequest;
use App\Models\Client;
use App\Models\TechnicalAudit;
use App\Models\TechnicalAuditAnswer;
use App\Models\TechnicalAuditControl;
use App\Models\TechnicalAuditEvidence;
use App\Models\TechnicalAuditModule;
use App\Services\TechnicalAuditExecutionService;
use App\Services\TechnicalAuditProgressService;
use App\Services\TechnicalAuditWorkflowService;
use App\Support\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngineerAuditController extends Controller
{
    public function index(Request $r): View
    {
        $u = $r->user();
        abort_unless($u->can('viewAny', TechnicalAudit::class), 403);
        $f = $r->validate(['status' => ['nullable', Rule::enum(TechnicalAuditStatus::class)], 'client_id' => ['nullable', 'integer'], 'mine' => ['nullable', 'boolean'], 'blocked' => ['nullable', 'boolean'], 'escalated' => ['nullable', 'boolean'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date']]);
        $q = TechnicalAudit::query()->visibleTo($u)->with(['client', 'auditType', 'technicalLead', 'order.assignees']);
        $q->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($f['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))->when($r->boolean('mine'), fn ($q) => $q->whereHas('order.assignees', fn ($x) => $x->where('user_id', $u->id)))->when($r->boolean('blocked'), fn ($q) => $q->where('blocked_controls', '>', 0))->when($r->boolean('escalated'), fn ($q) => $q->where('escalated_controls', '>', 0))->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereHas('order', fn ($x) => $x->whereDate('planned_start_at', '>=', $v)))->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereHas('order', fn ($x) => $x->whereDate('planned_end_at', '<=', $v)));

        return view('engineer.audits.index', ['audits' => $q->latest()->get(), 'filters' => $f, 'statuses' => TechnicalAuditStatus::options(), 'clients' => Client::orderBy('name')->get()]);
    }

    public function show(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditProgressService $p): View
    {
        abort_unless($r->user()->can('view', $technicalAudit), 403);
        $p->refresh($technicalAudit);
        $technicalAudit->load(['order.assignees.user', 'client', 'location', 'auditType', 'technicalLead', 'modules.controls.answer', 'escalations']);

        return view('engineer.audits.show', ['audit' => $technicalAudit, 'canExecute' => $r->user()->can('execute', $technicalAudit)]);
    }

    public function module(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditModule $module): View
    {
        abort_unless($module->technical_audit_id === $technicalAudit->id && $r->user()->can('view', $module), 403);
        $module->load('controls.answer');

        return view('engineer.audits.module', ['audit' => $technicalAudit, 'module' => $module]);
    }

    public function control(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditControl $control): View
    {
        abort_unless($control->technical_audit_id === $technicalAudit->id && $r->user()->can('view', $control), 403);
        $control->load(['module', 'answer', 'evidence', 'escalations']);
        $next = $technicalAudit->controls()->where('active', true)->where(fn ($query) => $query->where('sort_order', '>', $control->sort_order)->orWhere('id', '>', $control->id))->orderBy('sort_order')->orderBy('id')->first();
        $nextMissing = $technicalAudit->controls()->where('active', true)->whereNotIn('status', ['completed', 'not_applicable'])->whereKeyNot($control->id)->orderBy('sort_order')->orderBy('id')->first();

        return view('engineer.audits.control', ['audit' => $technicalAudit, 'control' => $control, 'canEdit' => $r->user()->can('update', $control), 'results' => TechnicalAuditAnswer::RESULTS, 'confidence' => TechnicalAuditAnswer::CONFIDENCE, 'evidenceTypes' => TechnicalAuditEvidence::TYPES, 'next' => $next, 'nextMissing' => $nextMissing]);
    }

    public function answer(UpdateTechnicalAuditAnswerRequest $r, TechnicalAudit $technicalAudit, TechnicalAuditControl $control, TechnicalAuditExecutionService $s): RedirectResponse
    {
        abort_unless($control->technical_audit_id === $technicalAudit->id, 403);
        $s->save($control, $r->user(), $r->validated());

        return back()->with('status', 'Odpowiedz zostala zapisana.');
    }

    public function controlStatus(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditControl $control, TechnicalAuditExecutionService $s, string $status): RedirectResponse
    {
        abort_unless($control->technical_audit_id === $technicalAudit->id && $r->user()->can('update', $control), 403);
        abort_unless(in_array($status, ['blocked', 'requires_consultation', 'in_progress'], true), 404);
        $s->setStatus($control, $status, $r->user());

        return back()->with('status', 'Status kontroli zostal zmieniony.');
    }

    public function uploadEvidence(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditControl $control, EvidenceScanner $scanner, TechnicalAuditProgressService $p): RedirectResponse
    {
        abort_unless($control->technical_audit_id === $technicalAudit->id && $r->user()->can('create', [TechnicalAuditEvidence::class, $control]), 403);
        $d = $r->validate(['evidence' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg'], 'evidence_type' => ['required', Rule::in(array_keys(TechnicalAuditEvidence::TYPES))], 'caption' => ['nullable', 'string', 'max:2000']]);
        $f = $d['evidence'];
        $path = $f->store("technical-audits/{$technicalAudit->id}/{$control->id}", 'local');
        $e = $control->evidence()->create(['technical_audit_id' => $technicalAudit->id, 'technical_audit_answer_id' => $control->answer?->id, 'uploaded_by' => $r->user()->id, 'disk' => 'local', 'path' => $path, 'original_name' => $f->getClientOriginalName(), 'mime_type' => $f->getMimeType(), 'size_bytes' => $f->getSize(), 'evidence_type' => $d['evidence_type'], 'caption' => $d['caption'] ?? null, 'scan_status' => 'not_scanned']);
        $e->update(['scan_status' => $scanner->scan($e)]);
        AuditLogService::record('technical_audit.evidence_uploaded', $technicalAudit, metadata: $technicalAudit->logMetadata(['control_id' => $control->id, 'evidence_id' => $e->id, 'mime' => $e->mime_type, 'size' => $e->size_bytes]));
        $p->refresh($technicalAudit);

        return back()->with('status', 'Dowod zostal dodany.');
    }

    public function downloadEvidence(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->technical_audit_id === $technicalAudit->id && $r->user()->can('view', $evidence), 403);
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);
        AuditLogService::record('technical_audit.evidence_downloaded', $technicalAudit, metadata: $technicalAudit->logMetadata(['evidence_id' => $evidence->id]));

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }

    public function deleteEvidence(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditEvidence $evidence, TechnicalAuditProgressService $p): RedirectResponse
    {
        abort_unless($evidence->technical_audit_id === $technicalAudit->id && $r->user()->can('delete', $evidence), 403);
        Storage::disk($evidence->disk)->delete($evidence->path);
        $id = $evidence->id;
        $evidence->delete();
        AuditLogService::record('technical_audit.evidence_deleted', $technicalAudit, metadata: $technicalAudit->logMetadata(['evidence_id' => $id]));
        $p->refresh($technicalAudit);

        return back();
    }

    public function transition(Request $r, TechnicalAudit $technicalAudit, TechnicalAuditStatus $status, TechnicalAuditWorkflowService $s): RedirectResponse
    {
        $d = $r->validate(['comment' => ['nullable', 'string', 'max:5000'], 'reason' => ['nullable', 'string', 'max:5000']]);
        $s->transition($technicalAudit, $status, $r->user(), $d);

        return back()->with('status', 'Status audytu zostal zmieniony.');
    }
}
