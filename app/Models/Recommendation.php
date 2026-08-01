<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recommendation extends Model
{
    use HasFactory;

    public const RISK_LEVELS = [
        'low' => 'Niskie',
        'medium' => 'Srednie',
        'high' => 'Wysokie',
        'critical' => 'Krytyczne',
    ];

    public const PRIORITIES = [
        'low' => 'Niski',
        'medium' => 'Sredni',
        'high' => 'Wysoki',
        'critical' => 'Krytyczny',
    ];

    protected $fillable = [
        'title',
        'technical_description',
        'business_description',
        'recommendation_text',
        'risk_level',
        'priority',
        'suggested_deadline',
        'estimated_hours_min',
        'estimated_hours_max',
        'global_it_can_do',
        'sales_category',
        'tags_json',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'estimated_hours_min' => 'integer',
            'estimated_hours_max' => 'integer',
            'global_it_can_do' => 'boolean',
            'tags_json' => 'array',
            'active' => 'boolean',
        ];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(AuditQuestion::class, 'audit_question_recommendation');
    }
}
