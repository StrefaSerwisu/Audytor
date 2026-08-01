<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditReview extends Model
{
    use HasFactory;

    public const DECISIONS = [
        'approved' => 'Zatwierdzony technicznie',
        'changes_requested' => 'Zwrocony do poprawek',
    ];

    protected $fillable = [
        'audit_id',
        'reviewer_id',
        'decision',
        'notes',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
