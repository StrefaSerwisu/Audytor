<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditAnswerAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_answer_id',
        'audit_id',
        'audit_question_id',
        'audit_module_id',
        'uploaded_by',
        'evidence_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'caption',
        'local_uuid',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(AuditAnswer::class, 'audit_answer_id');
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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
