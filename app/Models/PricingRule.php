<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDraftAuditTypeVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use BelongsToDraftAuditTypeVersion;

    public const RULE_TYPES = [
        'always' => 'Zawsze',
        'answer_exists' => 'Odpowiedz istnieje',
        'answer_equals' => 'Odpowiedz rowna',
        'answer_not_equals' => 'Odpowiedz rozna',
        'answer_greater_than' => 'Odpowiedz wieksza niz',
        'answer_less_than' => 'Odpowiedz mniejsza niz',
        'answer_contains' => 'Odpowiedz zawiera',
        'module_active' => 'Modul aktywny',
    ];

    public const CALCULATION_TYPES = [
        'fixed_hours' => 'Stale godziny',
        'hours_per_quantity' => 'Godziny na jednostke',
        'fixed_price' => 'Stala cena',
        'price_per_quantity' => 'Cena na jednostke',
        'hours_and_price' => 'Godziny i cena',
        'percentage_of_hours' => 'Procent godzin',
        'percentage_of_price' => 'Procent ceny',
    ];

    public const CATEGORIES = [
        'base' => 'Baza', 'users' => 'Uzytkownicy', 'computers' => 'Komputery',
        'servers' => 'Serwery', 'locations' => 'Lokalizacje', 'network_devices' => 'Urzadzenia sieciowe',
        'm365' => 'Microsoft 365', 'documentation' => 'Dokumentacja', 'reporting' => 'Raportowanie',
        'technical_review' => 'Weryfikacja techniczna', 'travel' => 'Dojazd',
        'after_hours' => 'Po godzinach', 'express' => 'Ekspres', 'reserve' => 'Rezerwa', 'custom' => 'Inne',
    ];

    protected $fillable = [
        'audit_type_version_id', 'code', 'name', 'description', 'active', 'rule_type',
        'source_question_code', 'operator', 'comparison_value', 'calculation_type',
        'quantity_source', 'fixed_quantity', 'hours_per_unit', 'fixed_hours', 'unit_price',
        'fixed_price', 'minimum_value', 'maximum_value', 'category', 'sort_order', 'configuration',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'comparison_value' => 'array',
            'configuration' => 'array',
            'fixed_quantity' => 'decimal:2',
            'hours_per_unit' => 'decimal:2',
            'fixed_hours' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'minimum_value' => 'decimal:2',
            'maximum_value' => 'decimal:2',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AuditTypeVersion::class, 'audit_type_version_id');
    }

    protected function auditTypeVersionForMutation(): ?AuditTypeVersion
    {
        return $this->version()->first();
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return $this->only(['id', ...$this->fillable]);
    }
}
