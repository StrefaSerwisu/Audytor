<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalAuditEvidence extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['photo' => 'Zdjecie', 'screenshot' => 'Screenshot', 'document' => 'Dokument', 'export' => 'Eksport', 'note' => 'Notatka', 'customer_confirmation' => 'Potwierdzenie klienta', 'test_result' => 'Wynik testu', 'other' => 'Inne'];

    protected $fillable = ['technical_audit_id', 'technical_audit_control_id', 'technical_audit_answer_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'evidence_type', 'caption', 'status', 'scan_status'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(TechnicalAudit::class, 'technical_audit_id');
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(TechnicalAuditControl::class, 'technical_audit_control_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(TechnicalAuditAnswer::class, 'technical_audit_answer_id');
    }
}
