<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualificationAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sales_qualification_id', 'qualification_answer_id', 'uploaded_by', 'disk', 'path',
        'original_name', 'mime_type', 'size_bytes',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'created_at' => 'datetime'];
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(SalesQualification::class, 'sales_qualification_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(QualificationAnswer::class, 'qualification_answer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
