<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditPublication extends Model
{
    use HasFactory;

    public const CLIENT_STATUSES = [
        'received' => 'Odebrane',
        'to_discuss' => 'Do omowienia',
        'accepted' => 'Zaakceptowane',
    ];

    protected $fillable = [
        'audit_id',
        'published_by',
        'token',
        'notes',
        'published_at',
        'expires_at',
        'client_status',
        'client_status_updated_at',
        'client_comment',
        'accepted_recommendations_json',
        'client_feedback_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'client_status_updated_at' => 'datetime',
            'accepted_recommendations_json' => 'array',
            'client_feedback_at' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function followUpTasks(): HasMany
    {
        return $this->hasMany(AuditFollowUpTask::class);
    }
}
