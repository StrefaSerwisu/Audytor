<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function templateModules(): HasMany
    {
        return $this->hasMany(AuditTemplateModule::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AuditQuestion::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AuditAnswer::class);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(AuditTemplate::class, 'audit_template_modules')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
