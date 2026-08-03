<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditPreparationItem extends Model
{
    public const STATUSES = ['pending' => 'Oczekuje', 'waiting_for_client' => 'Oczekuje na klienta', 'completed' => 'Gotowe', 'not_applicable' => 'Nie dotyczy', 'blocked' => 'Zablokowane'];

    protected $fillable = ['audit_order_id', 'category', 'code', 'title', 'description', 'status', 'required', 'completed', 'notes', 'sort_order', 'source', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'completed' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AuditOrder::class, 'audit_order_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
