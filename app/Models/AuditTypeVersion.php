<?php

namespace App\Models;

use App\Enums\CompetencyLevel;
use App\Support\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditTypeVersion extends Model
{
    protected $attributes = [
        'minimum_hours' => '0.00',
        'minimum_price' => '0.00',
        'reserve_percent' => '0.00',
        'default_engineers_count' => 1,
        'default_tax_rate' => '23.00',
        'default_validity_days' => 14,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Robocza',
        self::STATUS_PUBLISHED => 'Opublikowana',
        self::STATUS_ARCHIVED => 'Archiwalna',
    ];

    protected $fillable = [
        'audit_type_id',
        'version',
        'status',
        'name_snapshot',
        'description_snapshot',
        'sales_instructions',
        'delivery_instructions',
        'minimum_competency_level',
        'estimated_preparation_minutes',
        'estimated_execution_minutes',
        'estimated_reporting_minutes',
        'estimated_review_minutes',
        'default_hourly_rate',
        'minimum_hours',
        'minimum_price',
        'reserve_percent',
        'default_engineers_count',
        'default_tax_rate',
        'default_validity_days',
        'ai_enabled',
        'ai_configuration',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_competency_level' => CompetencyLevel::class,
            'default_hourly_rate' => 'decimal:2',
            'minimum_hours' => 'decimal:2',
            'minimum_price' => 'decimal:2',
            'reserve_percent' => 'decimal:2',
            'default_tax_rate' => 'decimal:2',
            'ai_enabled' => 'boolean',
            'ai_configuration' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if (($version->status ?? self::STATUS_DRAFT) !== self::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Nowa wersja musi miec status roboczy.']);
            }
        });

        static::updating(function (self $version): void {
            if ($version->getRawOriginal('status') !== self::STATUS_DRAFT) {
                throw ValidationException::withMessages(['version' => 'Opublikowana wersja jest niemodyfikowalna.']);
            }
        });

        static::deleting(function (self $version): void {
            if (! $version->isDraft()) {
                throw ValidationException::withMessages(['version' => 'Mozna usuwac tylko wersje robocze.']);
            }
        });
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(AuditType::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(AuditTypeModule::class)->orderBy('sort_order');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class)->orderBy('sort_order');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function publish(User $publisher): void
    {
        if (! $publisher->can('publish', $this)) {
            throw new AuthorizationException('Brak uprawnien do publikacji wersji audytu.');
        }

        $activeModules = $this->modules()->where('active', true);

        if (! $activeModules->exists()) {
            throw ValidationException::withMessages(['modules' => 'Wersja musi zawierac przynajmniej jeden aktywny modul.']);
        }

        if (! (clone $activeModules)->where('module_type', AuditTypeModule::TYPE_TECHNICAL)->exists()) {
            throw ValidationException::withMessages(['modules' => 'Wersja musi zawierac przynajmniej jeden aktywny modul techniczny.']);
        }

        DB::transaction(function () use ($publisher): void {
            $this->forceFill([
                'status' => self::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $publisher->id,
            ])->saveQuietly();

            $this->auditType()->update(['current_version_id' => $this->id]);

            AuditLogService::record('audit_type_version.published', $this, metadata: [
                'audit_type_id' => $this->audit_type_id,
                'version' => $this->version,
            ]);
        });
    }

    public function archive(User $actor): void
    {
        if (! $actor->can('archive', $this)) {
            throw new AuthorizationException('Brak uprawnien do archiwizacji wersji audytu.');
        }

        $this->forceFill(['status' => self::STATUS_ARCHIVED])->saveQuietly();

        if ($this->auditType->current_version_id === $this->id) {
            $this->auditType->update(['current_version_id' => null]);
        }

        AuditLogService::record('audit_type_version.archived', $this, metadata: [
            'audit_type_id' => $this->audit_type_id,
            'version' => $this->version,
        ]);
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $this->loadMissing(['modules.salesQuestions', 'modules.controlDefinitions', 'pricingRules']);

        $version = $this->only([
            'id',
            'audit_type_id',
            'version',
            'status',
            'name_snapshot',
            'description_snapshot',
            'sales_instructions',
            'delivery_instructions',
            'minimum_competency_level',
            'estimated_preparation_minutes',
            'estimated_execution_minutes',
            'estimated_reporting_minutes',
            'estimated_review_minutes',
            'default_hourly_rate',
            'minimum_hours',
            'minimum_price',
            'reserve_percent',
            'default_engineers_count',
            'default_tax_rate',
            'default_validity_days',
            'ai_enabled',
            'ai_configuration',
            'published_at',
        ]);
        $version['minimum_competency_level'] = $this->minimum_competency_level?->value;
        $version['published_at'] = $this->published_at?->toISOString();

        return [
            'version' => $version,
            'sales_modules' => $this->modules
                ->where('module_type', AuditTypeModule::TYPE_SALES)
                ->values()
                ->map(fn (AuditTypeModule $module): array => $module->snapshot(includeSalesQuestions: true))
                ->all(),
            'technical_modules' => $this->modules
                ->where('module_type', AuditTypeModule::TYPE_TECHNICAL)
                ->values()
                ->map(fn (AuditTypeModule $module): array => $module->snapshot(includeControlDefinitions: true))
                ->all(),
            'pricing_rules' => $this->pricingRules->where('active', true)->values()->map->snapshot()->all(),
        ];
    }
}
