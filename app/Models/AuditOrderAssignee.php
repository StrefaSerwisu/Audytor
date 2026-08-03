<?php

namespace App\Models;

use App\Enums\CompetencyLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditOrderAssignee extends Model
{
    public const ROLES = [
        'delivery_owner' => 'Wlasciciel Delivery', 'technical_lead' => 'Lider techniczny',
        'auditor' => 'Audytor', 'supporting_engineer' => 'Inzynier wspierajacy', 'observer' => 'Obserwator',
    ];

    protected $fillable = ['audit_order_id', 'user_id', 'assignment_role', 'planned_hours', 'competency_level', 'notes', 'assigned_by', 'assigned_at'];

    protected function casts(): array
    {
        return ['planned_hours' => 'decimal:2', 'competency_level' => CompetencyLevel::class, 'assigned_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AuditOrder::class, 'audit_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
