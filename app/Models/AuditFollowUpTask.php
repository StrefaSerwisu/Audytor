<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFollowUpTask extends Model
{
    use HasFactory;

    public const STATUSES = [
        'new' => 'Nowe',
        'planned' => 'Zaplanowane',
        'in_progress' => 'W trakcie',
        'done' => 'Zakonczone',
        'rejected' => 'Odrzucone',
    ];

    public const PRIORITIES = [
        'low' => 'Niski',
        'medium' => 'Sredni',
        'high' => 'Wysoki',
        'critical' => 'Krytyczny',
    ];

    protected $fillable = [
        'audit_id',
        'audit_publication_id',
        'owner_id',
        'source_key',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'notes',
        'client_visible',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'client_visible' => 'boolean',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(AuditPublication::class, 'audit_publication_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
