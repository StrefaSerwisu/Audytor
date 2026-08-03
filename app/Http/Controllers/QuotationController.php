<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Http\Requests\ApplyQuotationOverrideRequest;
use App\Http\Requests\QuotationTransitionRequest;
use App\Models\AuditType;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\SalesQualification;
use App\Models\User;
use App\Services\QuotationCalculationService;
use App\Services\QuotationOverrideService;
use App\Services\QuotationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationCalculationService $calculator,
        private readonly QuotationOverrideService $overrides,
        private readonly QuotationWorkflowService $workflow,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('viewAny', Quotation::class), 403);
        $filters = $request->validate([
            'client_id' => ['nullable', 'integer'], 'audit_type_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'], 'sales_owner_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
            'price_from' => ['nullable', 'numeric', 'min:0'], 'price_to' => ['nullable', 'numeric', 'min:0'],
        ]);

        $quotations = Quotation::query()->visibleTo($user)
            ->with(['client', 'auditType', 'versionDefinition', 'salesOwner'])
            ->when($filters['client_id'] ?? null, fn ($query, $value) => $query->where('client_id', $value))
            ->when($filters['audit_type_id'] ?? null, fn ($query, $value) => $query->where('audit_type_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['sales_owner_id'] ?? null, fn ($query, $value) => $query->where('sales_owner_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->when($filters['price_from'] ?? null, fn ($query, $value) => $query->where('net_price', '>=', $value))
            ->when($filters['price_to'] ?? null, fn ($query, $value) => $query->where('net_price', '<=', $value))
            ->latest()->get();

        return view('sales.quotations.index', [
            'quotations' => $quotations, 'filters' => $filters,
            'clients' => Client::orderBy('name')->get(), 'auditTypes' => AuditType::orderBy('name')->get(),
            'salesOwners' => User::where('role', UserRole::Sales)->where('active', true)->orderBy('name')->get(),
            'statuses' => QuotationStatus::options(),
        ]);
    }

    public function store(Request $request, SalesQualification $qualification): RedirectResponse
    {
        $quotation = $this->calculator->createForQualification($qualification, $request->user());

        return redirect()->route('sales.quotations.show', $quotation)->with('status', 'Wycena zostala obliczona.');
    }

    public function show(Request $request, Quotation $quotation): View
    {
        abort_unless($request->user()?->can('view', $quotation), 403);
        $quotation->load(['client', 'auditType', 'versionDefinition', 'salesOwner', 'qualification', 'lines', 'overrides.user', 'auditLogs.actor', 'auditOrder']);

        return view('sales.quotations.show', [
            'quotation' => $quotation,
            'canOverride' => $request->user()?->can('override', $quotation) ?? false,
        ]);
    }

    public function override(ApplyQuotationOverrideRequest $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validated();
        $reason = $data['reason'];
        unset($data['reason']);
        $this->overrides->apply($quotation, $request->user(), $data, $reason);

        return back()->with('status', 'Korekta zostala zapisana i wycena przeliczona.');
    }

    public function review(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::InternalReview, 'Wycena trafila do weryfikacji.');
    }

    public function approve(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::InternallyApproved, 'Wycena zostala zatwierdzona wewnetrznie.');
    }

    public function returnForChanges(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::Calculated, 'Wycena zostala cofnieta do poprawy.');
    }

    public function send(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::SentToClient, 'Wycena zostala oznaczona jako wyslana.');
    }

    public function accept(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::Accepted, 'Akceptacja klienta zostala zapisana.');
    }

    public function reject(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::Rejected, 'Odrzucenie klienta zostalo zapisane.');
    }

    public function expire(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::Expired, 'Wycena zostala oznaczona jako wygasla.');
    }

    public function cancel(QuotationTransitionRequest $request, Quotation $quotation): RedirectResponse
    {
        return $this->transition($request, $quotation, QuotationStatus::Cancelled, 'Wycena zostala anulowana.');
    }

    private function transition(QuotationTransitionRequest $request, Quotation $quotation, QuotationStatus $status, string $message): RedirectResponse
    {
        $this->workflow->transition($quotation, $status, $request->user(), $request->validated());

        return back()->with('status', $message);
    }
}
