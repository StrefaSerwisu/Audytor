<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicalAuditModule extends Model
{
    protected $fillable = ['technical_audit_id', 'source_module_id', 'code', 'name', 'description', 'instructions', 'sort_order', 'estimated_minutes', 'status', 'progress_percent', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(TechnicalAudit::class, 'technical_audit_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(TechnicalAuditControl::class)->orderBy('sort_order');
    }
}
