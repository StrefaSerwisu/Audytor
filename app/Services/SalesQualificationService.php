<?php

namespace App\Services;

use App\Enums\SalesQualificationStatus;
use App\Enums\UserRole;
use App\Models\AuditType;
use App\Models\AuditTypeVersion;
use App\Models\SalesQualification;
use App\Models\User;
use App\Support\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesQualificationService
{
    public function __construct(
        private readonly QualificationCompletionService $completion,
        private readonly QualificationScopeSummaryService $scopeSummary,
    ) {}

    public function create(array $data, User $actor): SalesQualification
    {
        if (! $actor->can('create', SalesQualification::class)) {
            throw new AuthorizationException('Brak uprawnien do utworzenia kwalifikacji Sales.');
        }

        $type = AuditType::query()->with('currentVersion')->findOrFail($data['audit_type_id']);
        $version = $type->currentVersion;

        if (! $version || $version->status !== AuditTypeVersion::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'audit_type_id' => 'Wybrany typ audytu nie ma opublikowanej aktualnej wersji.',
            ]);
        }

        $versionSnapshot = $version->snapshot();
        $snapshot = [
            'version' => $versionSnapshot['version'],
            'sales_instructions' => $version->sales_instructions,
            'sales_modules' => $versionSnapshot['sales_modules'],
        ];
        $salesOwnerId = $actor->hasRole(UserRole::Sales) ? $actor->id : ($data['sales_owner_id'] ?? null);

        if (! $salesOwnerId || ! User::whereKey($salesOwnerId)->where('role', UserRole::Sales)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['sales_owner_id' => 'Wybierz aktywnego opiekuna Sales.']);
        }

        return DB::transaction(function () use ($data, $type, $version, $snapshot, $salesOwnerId): SalesQualification {
            $qualification = SalesQualification::create([
                ...$data,
                'audit_type_id' => $type->id,
                'audit_type_version_id' => $version->id,
                'sales_owner_id' => $salesOwnerId,
                'status' => SalesQualificationStatus::Draft,
                'qualification_snapshot' => $snapshot,
            ]);

            AuditLogService::record('sales_qualification.created', $qualification, metadata: [
                'client_id' => $qualification->client_id,
                'audit_type_id' => $type->id,
                'audit_type_version_id' => $version->id,
                'sales_owner_id' => $qualification->sales_owner_id,
            ]);

            return $qualification;
        });
    }

    public function transition(SalesQualification $qualification, SalesQualificationStatus $to, User $actor): void
    {
        $this->authorizeUpdate($qualification, $actor);
        $from = $qualification->status;
        $event = match (true) {
            $from === SalesQualificationStatus::Draft && $to === SalesQualificationStatus::InProgress => 'sales_qualification.started',
            $from === SalesQualificationStatus::InProgress && $to === SalesQualificationStatus::WaitingForClient => 'sales_qualification.waiting_for_client',
            $from === SalesQualificationStatus::WaitingForClient && $to === SalesQualificationStatus::InProgress => 'sales_qualification.resumed',
            default => throw ValidationException::withMessages(['status' => 'Niedozwolona zmiana statusu kwalifikacji.']),
        };

        $qualification->update(['status' => $to]);
        AuditLogService::record($event, $qualification, oldValues: ['status' => $from->value], newValues: ['status' => $to->value]);
    }

    public function complete(SalesQualification $qualification, User $actor): void
    {
        $this->authorizeUpdate($qualification, $actor);

        if ($qualification->status !== SalesQualificationStatus::InProgress) {
            throw ValidationException::withMessages(['status' => 'Zakonczyc mozna tylko kwalifikacje w toku.']);
        }

        if (collect($qualification->qualification_snapshot['sales_modules'] ?? [])->where('active', true)->isEmpty()) {
            throw ValidationException::withMessages(['modules' => 'Kwalifikacja nie zawiera aktywnego modulu Sales.']);
        }

        $progress = $this->completion->calculate($qualification);

        if ($progress['missing'] > 0) {
            throw ValidationException::withMessages([
                'answers' => 'Uzupelnij wymagane pytania: '.collect($progress['missing_questions'])->pluck('question')->implode(', '),
            ]);
        }

        DB::transaction(function () use ($qualification, $actor): void {
            $qualification->update([
                'status' => SalesQualificationStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ]);
            AuditLogService::record('sales_qualification.completed', $qualification);

            $qualification->update(['scope_summary' => $this->scopeSummary->generate($qualification)]);
            $qualification->update(['status' => SalesQualificationStatus::ReadyForPricing]);
            AuditLogService::record('sales_qualification.ready_for_pricing', $qualification);
        });
    }

    public function cancel(SalesQualification $qualification, User $actor, string $reason): void
    {
        $this->authorizeUpdate($qualification, $actor);

        if (! in_array($qualification->status, [SalesQualificationStatus::Draft, SalesQualificationStatus::InProgress, SalesQualificationStatus::WaitingForClient], true)) {
            throw ValidationException::withMessages(['status' => 'Tej kwalifikacji nie mozna anulowac.']);
        }

        $qualification->update([
            'status' => SalesQualificationStatus::Cancelled,
            'internal_notes' => trim(($qualification->internal_notes ? $qualification->internal_notes."\n\n" : '')."Powod anulowania: {$reason}"),
        ]);
        AuditLogService::record('sales_qualification.cancelled', $qualification, metadata: ['reason' => mb_substr($reason, 0, 250)]);
    }

    private function authorizeUpdate(SalesQualification $qualification, User $actor): void
    {
        if (! $actor->can('update', $qualification)) {
            throw new AuthorizationException('Brak uprawnien do zmiany kwalifikacji Sales.');
        }
    }
}
