<?php

namespace App\Models;

use App\Enums\CompetencyLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TechnicalAuditControl extends Model
{
    protected $fillable = ['technical_audit_id', 'technical_audit_module_id', 'source_control_id', 'code', 'name', 'objective', 'description', 'execution_instructions', 'where_to_check', 'required_access', 'required_tools', 'minimum_competency_level', 'estimated_minutes', 'field_type', 'options_json', 'required', 'allow_not_applicable', 'require_comment_when_na', 'require_evidence', 'evidence_types', 'positive_criteria', 'negative_criteria', 'escalation_criteria', 'default_risk_level', 'default_recommendation', 'standard_reference', 'sort_order', 'active', 'status', 'assigned_to'];

    protected function casts(): array
    {
        return ['minimum_competency_level' => CompetencyLevel::class, 'options_json' => 'array', 'required' => 'boolean', 'allow_not_applicable' => 'boolean', 'require_comment_when_na' => 'boolean', 'require_evidence' => 'boolean', 'evidence_types' => 'array', 'active' => 'boolean'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(TechnicalAudit::class, 'technical_audit_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TechnicalAuditModule::class, 'technical_audit_module_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function answer(): HasOne
    {
        return $this->hasOne(TechnicalAuditAnswer::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(TechnicalAuditEvidence::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TechnicalAuditEscalation::class);
    }
}
