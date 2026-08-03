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
        'ai_enabled',
        'ai_configuration',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_competency_level' => CompetencyLevel::class,
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
        $this->loadMissing(['modules.salesQuestions', 'modules.controlDefinitions']);

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
        ];
    }
}
