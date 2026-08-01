<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function templateModules(): HasMany
    {
        return $this->hasMany(AuditTemplateModule::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(AuditModule::class, 'audit_template_modules')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
