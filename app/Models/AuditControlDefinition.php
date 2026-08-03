<?php

namespace App\Models;

use App\Enums\CompetencyLevel;
use App\Models\Concerns\BelongsToDraftAuditTypeVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AuditControlDefinition extends Model
{
    use BelongsToDraftAuditTypeVersion;

    public const FIELD_TYPES = [
        'text' => 'Tekst',
        'textarea' => 'Dlugi tekst',
        'number' => 'Liczba',
        'boolean' => 'Tak / Nie',
        'select' => 'Lista',
        'multiselect' => 'Lista wielokrotna',
        'date' => 'Data',
        'file' => 'Plik',
        'risk_level' => 'Poziom ryzyka',
    ];

    protected $fillable = [
        'audit_type_module_id', 'code', 'name', 'objective', 'description', 'execution_instructions',
        'where_to_check', 'required_access', 'required_tools', 'minimum_competency_level',
        'estimated_minutes', 'field_type', 'options_json', 'required', 'allow_not_applicable',
        'require_comment_when_na', 'require_evidence', 'evidence_types', 'positive_criteria',
        'negative_criteria', 'escalation_criteria', 'default_risk_level', 'default_recommendation',
        'standard_reference', 'conditional_logic', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_competency_level' => CompetencyLevel::class,
            'options_json' => 'array',
            'required' => 'boolean',
            'allow_not_applicable' => 'boolean',
            'require_comment_when_na' => 'boolean',
            'require_evidence' => 'boolean',
            'evidence_types' => 'array',
            'conditional_logic' => 'array',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $control): void {
            if ($control->module()->value('module_type') !== AuditTypeModule::TYPE_TECHNICAL) {
                throw ValidationException::withMessages(['audit_type_module_id' => 'Kontrola techniczna wymaga modulu technicznego.']);
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AuditTypeModule::class, 'audit_type_module_id');
    }

    protected function auditTypeVersionForMutation(): ?AuditTypeVersion
    {
        return $this->module()->with('version')->first()?->version;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $snapshot = $this->only([
            'id', 'code', 'name', 'objective', 'description', 'execution_instructions', 'where_to_check',
            'required_access', 'required_tools', 'minimum_competency_level', 'estimated_minutes',
            'field_type', 'options_json', 'required', 'allow_not_applicable', 'require_comment_when_na',
            'require_evidence', 'evidence_types', 'positive_criteria', 'negative_criteria',
            'escalation_criteria', 'default_risk_level', 'default_recommendation', 'standard_reference',
            'conditional_logic', 'sort_order', 'active',
        ]);

        $snapshot['minimum_competency_level'] = $this->minimum_competency_level?->value;

        return $snapshot;
    }
}
