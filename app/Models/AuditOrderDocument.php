<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditOrderDocument extends Model
{
    public const UPDATED_AT = null;

    public const CATEGORIES = ['qualification' => 'Kwalifikacja', 'quotation' => 'Wycena', 'client_document' => 'Dokument klienta', 'access_instruction' => 'Instrukcja dostepu', 'technical_document' => 'Dokument techniczny', 'other' => 'Inne'];

    protected $fillable = ['audit_order_id', 'category', 'source_type', 'source_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'description'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AuditOrder::class, 'audit_order_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isSourceReference(): bool
    {
        return filled($this->source_type) && filled($this->source_id);
    }
}
