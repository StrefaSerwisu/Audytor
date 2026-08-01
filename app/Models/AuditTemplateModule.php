<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditTemplateModule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audit_template_id',
        'audit_module_id',
        'sort_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AuditTemplate::class, 'audit_template_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AuditModule::class, 'audit_module_id');
    }
}
