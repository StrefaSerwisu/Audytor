<?php

namespace App\Models;

use App\Enums\AuditOrderStatus;
use App\Enums\CompetencyLevel;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AuditOrder extends Model
{
    protected $fillable = [
        'number', 'quotation_id', 'sales_qualification_id', 'client_id', 'client_location_id',
        'audit_type_id', 'audit_type_version_id', 'title', 'status', 'sales_owner_id',
        'delivery_owner_id', 'technical_lead_id', 'planned_start_at', 'planned_end_at',
        'expected_hours', 'planned_hours', 'engineers_count', 'minimum_competency_level',
        'purpose', 'scope_summary', 'assumptions', 'exclusions', 'delivery_instructions',
        'client_contact_name', 'client_contact_email', 'client_contact_phone', 'configuration_snapshot',
        'source_snapshot', 'planning_notes', 'cancellation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AuditOrderStatus::class,
            'minimum_competency_level' => CompetencyLevel::class,
            'planned_start_at' => 'datetime', 'planned_end_at' => 'datetime',
            'expected_hours' => 'decimal:2', 'planned_hours' => 'decimal:2',
            'configuration_snapshot' => 'array', 'source_snapshot' => 'array',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(SalesQualification::class, 'sales_qualification_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ClientLocation::class, 'client_location_id');
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(AuditType::class);
    }

    public function versionDefinition(): BelongsTo
    {
        return $this->belongsTo(AuditTypeVersion::class, 'audit_type_version_id');
    }

    public function salesOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_owner_id');
    }

    public function deliveryOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_owner_id');
    }

    public function technicalLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_lead_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(AuditOrderAssignee::class);
    }

    public function preparationItems(): HasMany
    {
        return $this->hasMany(AuditPreparationItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AuditOrderDocument::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AuditNotification::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject')->latest();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Sales)) {
            return $query->where('sales_owner_id', $user->id);
        }
        if ($user->hasRole(UserRole::Auditor)) {
            return $query->whereHas('assignees', fn (Builder $query) => $query->where('user_id', $user->id));
        }

        return $query;
    }

    /** @return list<string> */
    public function readinessBlockers(): array
    {
        $blockers = [];
        if (! $this->delivery_owner_id) {
            $blockers[] = 'Brak wlasciciela Delivery.';
        }
        if (! $this->technical_lead_id) {
            $blockers[] = 'Brak lidera technicznego.';
        }
        if (! $this->assignees()->whereIn('assignment_role', ['auditor', 'supporting_engineer'])->exists()) {
            $blockers[] = 'Brak audytora lub inzyniera wspierajacego.';
        }
        if (! $this->planned_start_at || ! $this->planned_end_at) {
            $blockers[] = 'Brak terminu realizacji.';
        }
        if ((float) $this->planned_hours <= 0) {
            $blockers[] = 'Planowane godziny musza byc wieksze od zera.';
        }
        if ($this->preparationItems()->where('required', true)->whereNotIn('status', ['completed', 'not_applicable'])->exists()) {
            $blockers[] = 'Wymagana checklista przygotowania nie jest zakonczona.';
        }
        if ($this->minimum_competency_level && ! $this->assignees()->whereNotNull('competency_level')->get()->contains(
            fn (AuditOrderAssignee $assignee) => $assignee->competency_level->meets($this->minimum_competency_level)
        )) {
            $blockers[] = 'Brak przypisanej osoby o wymaganym poziomie kompetencji.';
        }

        return $blockers;
    }

    /** @param array<string, mixed> $extra @return array<string, mixed> */
    public function logMetadata(array $extra = []): array
    {
        return [...[
            'audit_order_id' => $this->id, 'number' => $this->number,
            'quotation_id' => $this->quotation_id, 'client_id' => $this->client_id,
        ], ...$extra];
    }
}
