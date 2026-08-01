<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditAnswer extends Model
{
    use HasFactory;

    public const RISK_LEVELS = [
        'low' => 'Niskie',
        'medium' => 'Srednie',
        'high' => 'Wysokie',
        'critical' => 'Krytyczne',
    ];

    public const RISK_LEVELS_REQUIRING_RECOMMENDATION = [
        'high',
        'critical',
    ];

    protected $fillable = [
        'audit_id',
        'audit_question_id',
        'audit_module_id',
        'answered_by',
        'value_json',
        'comment',
        'not_applicable',
        'not_applicable_reason',
        'risk_level',
        'recommendation_text',
        'status',
        'sync_status',
        'local_uuid',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'not_applicable' => 'boolean',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AuditQuestion::class, 'audit_question_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AuditModule::class, 'audit_module_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AuditAnswerAttachment::class);
    }
}
