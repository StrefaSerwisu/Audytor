<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditSelectedModule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audit_id',
        'audit_module_id',
        'sort_order',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AuditModule::class, 'audit_module_id');
    }
}
