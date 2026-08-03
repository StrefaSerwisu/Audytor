<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'audit_id',
        'audit_order_id',
        'type',
        'title',
        'body',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function auditOrder(): BelongsTo
    {
        return $this->belongsTo(AuditOrder::class);
    }
}
