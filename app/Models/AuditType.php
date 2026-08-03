<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class AuditType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
        'sales_instructions',
        'delivery_instructions',
        'active',
        'current_version_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $auditType): void {
            $auditType->created_by ??= auth()->id();
            $auditType->updated_by ??= auth()->id();
        });

        static::updating(function (self $auditType): void {
            $auditType->updated_by = auth()->id();
        });

        static::deleting(function (self $auditType): void {
            if ($auditType->versions()->whereIn('status', [AuditTypeVersion::STATUS_PUBLISHED, AuditTypeVersion::STATUS_ARCHIVED])->exists()) {
                throw ValidationException::withMessages([
                    'audit_type' => 'Nie mozna usunac typu audytu posiadajacego opublikowane wersje.',
                ]);
            }
        });
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AuditTypeVersion::class)->orderBy('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(AuditTypeVersion::class, 'current_version_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function createDraftVersion(array $attributes = []): AuditTypeVersion
    {
        $nextVersion = ((int) $this->versions()->max('version')) + 1;

        return $this->versions()->create(array_merge([
            'version' => $nextVersion,
            'status' => AuditTypeVersion::STATUS_DRAFT,
            'name_snapshot' => $this->name,
            'description_snapshot' => $this->description,
            'sales_instructions' => $this->sales_instructions,
            'delivery_instructions' => $this->delivery_instructions,
        ], $attributes));
    }
}
