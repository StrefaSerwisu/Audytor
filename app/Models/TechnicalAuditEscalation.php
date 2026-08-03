<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalAuditEscalation extends Model
{
    public const STATUSES = ['open' => 'Otwarta', 'assigned' => 'Przypisana', 'answered' => 'Odpowiedziana', 'resolved' => 'Rozwiazana', 'cancelled' => 'Anulowana'];

    public const PRIORITIES = ['normal' => 'Normalny', 'high' => 'Wysoki', 'critical' => 'Krytyczny'];

    protected $fillable = ['technical_audit_id', 'technical_audit_control_id', 'created_by', 'assigned_to', 'reason', 'question', 'status', 'priority', 'response', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(TechnicalAudit::class, 'technical_audit_id');
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(TechnicalAuditControl::class, 'technical_audit_control_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
