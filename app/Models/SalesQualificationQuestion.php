<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDraftAuditTypeVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SalesQualificationQuestion extends Model
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
        'info' => 'Informacja',
    ];

    protected $fillable = [
        'audit_type_module_id', 'code', 'question', 'description', 'field_type', 'options_json',
        'required', 'sort_order', 'conditional_logic', 'affects_scope', 'affects_pricing',
        'pricing_variable', 'helper_text', 'active',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'required' => 'boolean',
            'conditional_logic' => 'array',
            'affects_scope' => 'boolean',
            'affects_pricing' => 'boolean',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $question): void {
            if ($question->module()->value('module_type') !== AuditTypeModule::TYPE_SALES) {
                throw ValidationException::withMessages(['audit_type_module_id' => 'Pytanie Sales wymaga modulu typu Sales.']);
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
        return $this->only([
            'id', 'code', 'question', 'description', 'field_type', 'options_json', 'required',
            'sort_order', 'conditional_logic', 'affects_scope', 'affects_pricing', 'pricing_variable',
            'helper_text', 'active',
        ]);
    }
}
