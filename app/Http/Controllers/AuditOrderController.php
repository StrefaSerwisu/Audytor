<?php

namespace App\Http\Controllers;

use App\Enums\AuditOrderStatus;
use App\Enums\UserRole;
use App\Models\AuditOrder;
use App\Models\AuditOrderAssignee;
use App\Models\AuditOrderDocument;
use App\Models\AuditPreparationItem;
use App\Models\AuditType;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditOrderCreationService;
use App\Services\AuditOrderWorkflowService;
use App\Support\AuditLogService;
use App\Support\AuditOrderNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditOrderController extends Controller
{
    public function __construct(private readonly AuditOrderCreationService $creation, private readonly AuditOrderWorkflowService $workflow) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('viewAny', AuditOrder::class), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AuditOrderStatus::class)], 'client_id' => ['nullable', 'integer'],
            'audit_type_id' => ['nullable', 'integer'], 'delivery_owner_id' => ['nullable', 'integer'],
            'technical_lead_id' => ['nullable', 'integer'], 'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'], 'readiness' => ['nullable', Rule::in(['ready', 'not_ready'])],
        ]);
        $orders = AuditOrder::query()->visibleTo($user)->with(['client', 'auditType', 'salesOwner', 'deliveryOwner', 'technicalLead'])
            ->withCount(['preparationItems as blockers_count' => fn ($query) => $query->where('required', true)->whereNotIn('status', ['completed', 'not_applicable'])])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($query, $client) => $query->where('client_id', $client))
            ->when($filters['audit_type_id'] ?? null, fn ($query, $type) => $query->where('audit_type_id', $type))
            ->when($filters['delivery_owner_id'] ?? null, fn ($query, $owner) => $query->where('delivery_owner_id', $owner))
            ->when($filters['technical_lead_id'] ?? null, fn ($query, $lead) => $query->where('technical_lead_id', $lead))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('planned_start_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('planned_end_at', '<=', $date))
            ->when(($filters['readiness'] ?? null) === 'ready', fn ($query) => $query->whereDoesntHave('preparationItems', fn ($items) => $items->where('required', true)->whereNotIn('status', ['completed', 'not_applicable'])))
            ->when(($filters['readiness'] ?? null) === 'not_ready', fn ($query) => $query->whereHas('preparationItems', fn ($items) => $items->where('required', true)->whereNotIn('status', ['completed', 'not_applicable'])))->latest()->get();

        return view('delivery.audit-orders.index', [
            'orders' => $orders, 'statuses' => AuditOrderStatus::options(), 'filters' => $filters,
            'clients' => Client::orderBy('name')->get(), 'auditTypes' => AuditType::orderBy('name')->get(),
            'deliveryUsers' => User::where('active', true)->whereIn('role', [UserRole::TechnicalLead, UserRole::Auditor])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Quotation $quotation): RedirectResponse
    {
        $order = $this->creation->create($quotation, $request->user());

        return redirect()->route('delivery.audit-orders.show', $order)->with('status', 'Zlecenie audytu zostalo utworzone.');
    }

    public function show(Request $request, AuditOrder $auditOrder): View
    {
        abort_unless($request->user()->can('view', $auditOrder), 403);
        $auditOrder->load(['client', 'location', 'auditType', 'versionDefinition', 'quotation', 'qualification', 'salesOwner', 'deliveryOwner', 'technicalLead', 'assignees.user', 'preparationItems', 'documents.uploader', 'auditLogs.actor']);

        return view('delivery.audit-orders.show', [
            'order' => $auditOrder, 'canPlan' => $request->user()->can('plan', $auditOrder),
            'users' => User::where('active', true)->whereIn('role', [UserRole::Auditor, UserRole::TechnicalLead])->orderBy('name')->get(),
            'roles' => AuditOrderAssignee::ROLES, 'itemStatuses' => AuditPreparationItem::STATUSES,
            'documentCategories' => AuditOrderDocument::CATEGORIES,
        ]);
    }

    public function updatePlan(Request $request, AuditOrder $auditOrder): RedirectResponse
    {
        abort_unless($request->user()->can('plan', $auditOrder), 403);
        $oldDates = $auditOrder->only(['planned_start_at', 'planned_end_at']);
        $data = $request->validate([
            'planned_start_at' => ['nullable', 'date'], 'planned_end_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
            'planned_hours' => ['required', 'numeric', 'min:0'], 'planning_notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $auditOrder->update($data);
        AuditLogService::record('audit_order.plan_updated', $auditOrder, oldValues: $oldDates, newValues: $data, metadata: $auditOrder->logMetadata());
        if ($oldDates != $auditOrder->only(['planned_start_at', 'planned_end_at'])) {
            AuditLogService::record('audit_order.rescheduled', $auditOrder, oldValues: $oldDates, newValues: $auditOrder->only(['planned_start_at', 'planned_end_at']), metadata: $auditOrder->logMetadata());
            AuditOrderNotifier::notifyAssignees($auditOrder, 'audit_order.dates_changed', 'Zmieniono termin zlecenia', $auditOrder->number);
        }

        return back()->with('status', 'Plan zlecenia zostal zapisany.');
    }

    public function assign(Request $request, AuditOrder $auditOrder): RedirectResponse
    {
        abort_unless($request->user()->can('plan', $auditOrder), 403);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'assignment_role' => ['required', Rule::in(array_keys(AuditOrderAssignee::ROLES))], 'planned_hours' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $user = User::findOrFail($data['user_id']);
        abort_unless($user->active && $user->hasAnyRole(UserRole::Auditor, UserRole::TechnicalLead), 422);
        $assignee = $auditOrder->assignees()->updateOrCreate(['user_id' => $user->id, 'assignment_role' => $data['assignment_role']], [...$data, 'competency_level' => $user->competency_level, 'assigned_by' => $request->user()->id, 'assigned_at' => now()]);
        if ($data['assignment_role'] === 'delivery_owner') {
            $auditOrder->update(['delivery_owner_id' => $user->id]);
        }
        if ($data['assignment_role'] === 'technical_lead') {
            $auditOrder->update(['technical_lead_id' => $user->id]);
        }
        AuditLogService::record('audit_order.assignee_added', $auditOrder, newValues: $assignee->only(['user_id', 'assignment_role', 'planned_hours', 'competency_level']), metadata: $auditOrder->logMetadata(['user_id' => $user->id]));
        AuditOrderNotifier::notify($user, $auditOrder, 'audit_order.assigned', 'Przypisano Cie do zlecenia', $auditOrder->number);
        $warning = $auditOrder->minimum_competency_level && (! $user->competency_level || ! $user->competency_level->meets($auditOrder->minimum_competency_level)) ? ' Uwaga: poziom kompetencji jest nizszy od wymaganego.' : '';

        return back()->with('status', 'Przypisanie zapisane.'.$warning);
    }

    public function unassign(Request $request, AuditOrder $auditOrder, AuditOrderAssignee $assignee): RedirectResponse
    {
        abort_unless($assignee->audit_order_id === $auditOrder->id && $request->user()->can('delete', $assignee), 403);
        if ($assignee->assignment_role === 'delivery_owner' && $auditOrder->delivery_owner_id === $assignee->user_id) {
            $auditOrder->update(['delivery_owner_id' => null]);
        }
        if ($assignee->assignment_role === 'technical_lead' && $auditOrder->technical_lead_id === $assignee->user_id) {
            $auditOrder->update(['technical_lead_id' => null]);
        }
        $snapshot = $assignee->only(['user_id', 'assignment_role']);
        $assignee->delete();
        AuditLogService::record('audit_order.assignee_removed', $auditOrder, oldValues: $snapshot, metadata: $auditOrder->logMetadata(['user_id' => $assignee->user_id]));

        return back()->with('status', 'Przypisanie usuniete.');
    }

    public function updatePreparation(Request $request, AuditOrder $auditOrder, AuditPreparationItem $item): RedirectResponse
    {
        abort_unless($item->audit_order_id === $auditOrder->id && $request->user()->can('update', $item), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(AuditPreparationItem::STATUSES))], 'notes' => ['nullable', 'string', 'max:2000']]);
        $old = $item->only(['status', 'notes']);
        $done = in_array($data['status'], ['completed', 'not_applicable'], true);
        $item->update([...$data, 'completed' => $done, 'completed_by' => $done ? $request->user()->id : null, 'completed_at' => $done ? now() : null]);
        AuditLogService::record('audit_order.preparation_updated', $auditOrder, oldValues: $old, newValues: $data, metadata: $auditOrder->logMetadata(['item_id' => $item->id, 'code' => $item->code]));

        return back()->with('status', 'Checklista zostala zaktualizowana.');
    }

    public function uploadDocument(Request $request, AuditOrder $auditOrder): RedirectResponse
    {
        abort_unless($request->user()->can('create', [AuditOrderDocument::class, $auditOrder]), 403);
        $data = $request->validate(['category' => ['required', Rule::in(array_keys(AuditOrderDocument::CATEGORIES))], 'document' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg']]);
        $file = $data['document'];
        $path = $file->store("audit-orders/{$auditOrder->id}", 'local');
        $document = $auditOrder->documents()->create(['category' => $data['category'], 'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize()]);
        AuditLogService::record('audit_order.document_uploaded', $auditOrder, metadata: $auditOrder->logMetadata(['document_id' => $document->id, 'name' => $document->original_name, 'mime' => $document->mime_type, 'size' => $document->size_bytes]));

        return back()->with('status', 'Dokument zostal dodany.');
    }

    public function downloadDocument(Request $request, AuditOrder $auditOrder, AuditOrderDocument $document): StreamedResponse
    {
        abort_unless($document->audit_order_id === $auditOrder->id && $request->user()->can('view', $document), 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        AuditLogService::record('audit_order.document_downloaded', $auditOrder, metadata: $auditOrder->logMetadata(['document_id' => $document->id, 'name' => $document->original_name]));

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function deleteDocument(Request $request, AuditOrder $auditOrder, AuditOrderDocument $document): RedirectResponse
    {
        abort_unless($document->audit_order_id === $auditOrder->id && $request->user()->can('delete', $document), 403);
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
        AuditLogService::record('audit_order.document_deleted', $auditOrder, metadata: $auditOrder->logMetadata(['document_id' => $document->id, 'name' => $document->original_name]));

        return back()->with('status', 'Dokument zostal usuniety.');
    }

    public function transition(Request $request, AuditOrder $auditOrder, AuditOrderStatus $status): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000'], 'justification' => ['nullable', 'string', 'max:2000']]);
        $this->workflow->transition($auditOrder, $status, $request->user(), $data);

        return back()->with('status', 'Status zlecenia zostal zmieniony.');
    }
}
