<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditQuestion extends Model
{
    use HasFactory;

    public const FIELD_TYPES = [
        'short_text' => 'Tekst krotki',
        'long_text' => 'Tekst dlugi',
        'number' => 'Liczba',
        'date' => 'Data',
        'yes_no' => 'Tak/Nie',
        'single_choice' => 'Wybor z listy',
        'multiple_choice' => 'Wielokrotny wybor',
        'rating' => 'Skala oceny',
        'risk_level' => 'Poziom ryzyka',
        'photo' => 'Zdjecie',
        'screenshot' => 'Screenshot',
        'file' => 'Plik',
        'table' => 'Tabela',
        'signature' => 'Podpis',
        'gps_location' => 'Lokalizacja GPS',
    ];

    protected $fillable = [
        'audit_module_id',
        'question',
        'instruction',
        'field_type',
        'is_required',
        'allow_not_applicable',
        'require_comment_when_na',
        'require_photo',
        'require_screenshot',
        'risk_enabled',
        'sort_order',
        'config_json',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'allow_not_applicable' => 'boolean',
            'require_comment_when_na' => 'boolean',
            'require_photo' => 'boolean',
            'require_screenshot' => 'boolean',
            'risk_enabled' => 'boolean',
            'sort_order' => 'integer',
            'config_json' => 'array',
            'active' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AuditModule::class, 'audit_module_id');
    }

    public function recommendations(): BelongsToMany
    {
        return $this->belongsToMany(Recommendation::class, 'audit_question_recommendation');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AuditAnswer::class);
    }

    public function answerAttachments(): HasMany
    {
        return $this->hasMany(AuditAnswerAttachment::class);
    }
}
