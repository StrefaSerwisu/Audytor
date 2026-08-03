<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicalAuditAnswer extends Model
{
    public const RESULTS = ['compliant' => 'Zgodne', 'partially_compliant' => 'Czesciowo zgodne', 'non_compliant' => 'Niezgodne', 'not_verified' => 'Nie zweryfikowano', 'not_applicable' => 'Nie dotyczy'];

    public const CONFIDENCE = ['low' => 'Niska', 'medium' => 'Srednia', 'high' => 'Wysoka'];

    protected $fillable = ['technical_audit_id', 'technical_audit_control_id', 'answered_by', 'value_json', 'comment', 'not_applicable', 'not_applicable_reason', 'result_status', 'proposed_risk_level', 'proposed_recommendation', 'customer_statement', 'customer_statement_source', 'confidence_level', 'started_at', 'answered_at'];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'not_applicable' => 'boolean', 'customer_statement' => 'boolean', 'started_at' => 'datetime', 'answered_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(TechnicalAudit::class, 'technical_audit_id');
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(TechnicalAuditControl::class, 'technical_audit_control_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(TechnicalAuditEvidence::class);
    }
}
