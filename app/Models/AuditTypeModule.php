<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDraftAuditTypeVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditTypeModule extends Model
{
    use BelongsToDraftAuditTypeVersion;

    public const TYPE_SALES = 'sales';

    public const TYPE_TECHNICAL = 'technical';

    public const TYPES = [
        self::TYPE_SALES => 'Sales',
        self::TYPE_TECHNICAL => 'Techniczny',
    ];

    protected $fillable = [
        'audit_type_version_id',
        'name',
        'code',
        'description',
        'module_type',
        'sort_order',
        'active',
        'conditional_logic',
        'estimated_minutes',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'conditional_logic' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AuditTypeVersion::class, 'audit_type_version_id');
    }

    public function salesQuestions(): HasMany
    {
        return $this->hasMany(SalesQualificationQuestion::class)->orderBy('sort_order');
    }

    public function controlDefinitions(): HasMany
    {
        return $this->hasMany(AuditControlDefinition::class)->orderBy('sort_order');
    }

    protected function auditTypeVersionForMutation(): ?AuditTypeVersion
    {
        return $this->version()->first();
    }

    /** @return array<string, mixed> */
    public function snapshot(bool $includeSalesQuestions = false, bool $includeControlDefinitions = false): array
    {
        $snapshot = $this->only([
            'id', 'name', 'code', 'description', 'module_type', 'sort_order', 'active',
            'conditional_logic', 'estimated_minutes',
        ]);

        if ($includeSalesQuestions) {
            $snapshot['questions'] = $this->salesQuestions->map->snapshot()->all();
        }

        if ($includeControlDefinitions) {
            $snapshot['controls'] = $this->controlDefinitions->map->snapshot()->all();
        }

        return $snapshot;
    }
}
