<?php

namespace App\Models;

use App\Enums\TechnicalAuditStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TechnicalAudit extends Model
{
    protected $fillable = ['number', 'audit_order_id', 'client_id', 'client_location_id', 'audit_type_id', 'audit_type_version_id', 'title', 'status', 'technical_lead_id', 'delivery_owner_id', 'started_at', 'started_by', 'submitted_at', 'submitted_by', 'completed_at', 'configuration_snapshot', 'source_snapshot', 'progress_percent', 'total_controls', 'completed_controls', 'blocked_controls', 'escalated_controls'];

    protected function casts(): array
    {
        return ['status' => TechnicalAuditStatus::class, 'started_at' => 'datetime', 'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'configuration_snapshot' => 'array', 'source_snapshot' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AuditOrder::class, 'audit_order_id');
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

    public function technicalLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_lead_id');
    }

    public function deliveryOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_owner_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TechnicalAuditModule::class)->orderBy('sort_order');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(TechnicalAuditControl::class)->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TechnicalAuditAnswer::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(TechnicalAuditEvidence::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TechnicalAuditEscalation::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject')->latest();
    }

    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        if ($u->hasRole(UserRole::Auditor)) {
            return $q->whereHas('order.assignees', fn (Builder $q) => $q->where('user_id', $u->id)->whereIn('assignment_role', ['auditor', 'supporting_engineer']));
        }

return $q;
    }

    public function logMetadata(array $extra = []): array
    {
        return [...['technical_audit_id' => $this->id, 'number' => $this->number, 'audit_order_id' => $this->audit_order_id, 'client_id' => $this->client_id], ...$extra];
    }
}
