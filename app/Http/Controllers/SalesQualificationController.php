<?php

namespace App\Http\Controllers;

use App\Enums\SalesQualificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\CancelSalesQualificationRequest;
use App\Http\Requests\CreateSalesQualificationRequest;
use App\Http\Requests\UpdateQualificationAnswerRequest;
use App\Models\AuditType;
use App\Models\Client;
use App\Models\QualificationAnswer;
use App\Models\SalesQualification;
use App\Models\User;
use App\Services\QualificationCompletionService;
use App\Services\QualificationConditionService;
use App\Services\SalesQualificationService;
use App\Support\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesQualificationController extends Controller
{
    public function __construct(
        private readonly SalesQualificationService $workflow,
        private readonly QualificationCompletionService $completion,
        private readonly QualificationConditionService $conditions,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('viewAny', SalesQualification::class), 403);
        $filters = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'audit_type_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'sales_owner_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $qualifications = SalesQualification::query()
            ->visibleTo($user)
            ->with(['client', 'auditType', 'version', 'salesOwner', 'answers.attachments'])
            ->when($filters['client_id'] ?? null, fn ($query, $value) => $query->where('client_id', $value))
            ->when($filters['audit_type_id'] ?? null, fn ($query, $value) => $query->where('audit_type_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['sales_owner_id'] ?? null, fn ($query, $value) => $query->where('sales_owner_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest()
            ->get()
            ->map(fn (SalesQualification $qualification): array => [
                'qualification' => $qualification,
                'progress' => $this->completion->calculate($qualification),
            ]);

        return view('sales.qualifications.index', [
            'qualifications' => $qualifications,
            'filters' => $filters,
            'clients' => Client::orderBy('name')->get(),
            'auditTypes' => AuditType::orderBy('name')->get(),
            'salesOwners' => User::where('role', UserRole::Sales)->where('active', true)->orderBy('name')->get(),
            'statuses' => SalesQualificationStatus::options(),
            'canCreate' => $user->can('create', SalesQualification::class),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', SalesQualification::class), 403);

        return view('sales.qualifications.create', [
            'clients' => Client::with('locations')->orderBy('name')->get(),
            'auditTypes' => AuditType::where('active', true)->with('currentVersion')->orderBy('name')->get(),
            'salesOwners' => User::where('role', UserRole::Sales)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(CreateSalesQualificationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->validateLocation($data);
        $qualification = $this->workflow->create($data, $request->user());

        return redirect()->route('sales.qualifications.show', $qualification)->with('status', 'Kwalifikacja zostala utworzona.');
    }

    public function show(Request $request, SalesQualification $qualification): View
    {
        abort_unless($request->user()?->can('view', $qualification), 403);
        $qualification->load(['client', 'location', 'auditType', 'version', 'salesOwner', 'answers.attachments', 'currentQuotation']);
        $answers = $qualification->answers->keyBy('question_code');
        $values = $answers->mapWithKeys(fn (QualificationAnswer $answer): array => [
            $answer->question_code => $answer->value_json['value'] ?? null,
        ])->all();

        return view('sales.qualifications.show', [
            'qualification' => $qualification,
            'answersByCode' => $answers,
            'visibleModules' => $this->conditions->visibleModules($qualification->qualification_snapshot, $values),
            'progress' => $this->completion->calculate($qualification),
            'canEdit' => $request->user()?->can('update', $qualification) ?? false,
            'canCreateQuotation' => $request->user()?->can('createQuotation', $qualification) ?? false,
        ]);
    }

    public function updateAnswer(UpdateQualificationAnswerRequest $request, SalesQualification $qualification, string $questionCode): RedirectResponse
    {
        $question = $request->questionSnapshot();
        $oldAnswer = $qualification->answers()->where('question_code', $questionCode)->first();
        $value = $this->typedValue($question['field_type'], $request->validated('value'));
        $answer = $qualification->answers()->updateOrCreate(
            ['question_code' => $questionCode],
            [
                'sales_qualification_question_id' => $question['id'] ?? null,
                'question_snapshot' => $question,
                'value_json' => $question['field_type'] === 'file' ? ($oldAnswer?->value_json ?? null) : ['value' => $value],
                'answered_by' => $request->user()->id,
                'answered_at' => now(),
            ],
        );

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store("sales-qualifications/{$qualification->id}/{$questionCode}", 'local');
            $attachment = $answer->attachments()->create([
                'sales_qualification_id' => $qualification->id,
                'uploaded_by' => $request->user()->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
            $answer->update(['value_json' => ['value' => ['attachment_id' => $attachment->id]]]);
            AuditLogService::record('sales_qualification.file_uploaded', $attachment, metadata: [
                'qualification_id' => $qualification->id,
                'question_code' => $questionCode,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ]);
        }

        AuditLogService::record('sales_qualification.answer_updated', $qualification, metadata: [
            'question_code' => $questionCode,
            'changed' => $oldAnswer?->value_json !== $answer->value_json,
            'old' => Str::limit(json_encode($oldAnswer?->value_json), 200),
            'new' => Str::limit(json_encode($answer->value_json), 200),
        ]);

        return back()->with('status', 'Odpowiedz zostala zapisana.');
    }

    public function start(Request $request, SalesQualification $qualification): RedirectResponse
    {
        $this->authorizeUpdate($request, $qualification);
        $this->workflow->transition($qualification, SalesQualificationStatus::InProgress, $request->user());

        return back()->with('status', 'Kwalifikacja zostala rozpoczeta.');
    }

    public function waitForClient(Request $request, SalesQualification $qualification): RedirectResponse
    {
        $this->authorizeUpdate($request, $qualification);
        $this->workflow->transition($qualification, SalesQualificationStatus::WaitingForClient, $request->user());

        return back()->with('status', 'Kwalifikacja oczekuje na klienta.');
    }

    public function resume(Request $request, SalesQualification $qualification): RedirectResponse
    {
        $this->authorizeUpdate($request, $qualification);
        $this->workflow->transition($qualification, SalesQualificationStatus::InProgress, $request->user());

        return back()->with('status', 'Kwalifikacja zostala wznowiona.');
    }

    public function complete(Request $request, SalesQualification $qualification): RedirectResponse
    {
        $this->authorizeUpdate($request, $qualification);
        $this->workflow->complete($qualification, $request->user());

        return back()->with('status', 'Kwalifikacja jest gotowa do wyceny.');
    }

    public function cancel(CancelSalesQualificationRequest $request, SalesQualification $qualification): RedirectResponse
    {
        $this->workflow->cancel($qualification, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Kwalifikacja zostala anulowana.');
    }

    private function authorizeUpdate(Request $request, SalesQualification $qualification): void
    {
        abort_unless($request->user()?->can('update', $qualification), 403);
    }

    private function typedValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'number' => $value === null || $value === '' ? null : (float) $value,
            'boolean' => match ($value) {
                'true' => true,
                'false' => false,
                default => null,
            },
            'multiselect' => array_values($value ?? []),
            default => $value,
        };
    }

    private function validateLocation(array $data): void
    {
        if (! empty($data['client_location_id']) && ! Client::findOrFail($data['client_id'])->locations()->whereKey($data['client_location_id'])->exists()) {
            throw ValidationException::withMessages([
                'client_location_id' => 'Wybrana lokalizacja nie nalezy do klienta.',
            ]);
        }
    }
}
