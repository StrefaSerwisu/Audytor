<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditReportExport extends Model
{
    public const FORMATS = [
        'pdf' => 'PDF',
        'docx' => 'DOCX',
    ];

    public const REPORT_TYPES = [
        'technical' => 'Techniczny',
        'business' => 'Biznesowy',
        'sales' => 'Sprzedazowy',
    ];

    public const STATUSES = [
        'queued' => 'W kolejce',
        'processing' => 'Generowanie',
        'completed' => 'Gotowy',
        'failed' => 'Blad',
    ];

    protected $fillable = [
        'audit_id',
        'queued_by',
        'report_type',
        'format',
        'status',
        'path',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function queuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }
}
