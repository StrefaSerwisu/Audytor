<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualificationAnswer extends Model
{
    protected $fillable = [
        'sales_qualification_id', 'sales_qualification_question_id', 'question_code',
        'question_snapshot', 'value_json', 'answered_by', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'question_snapshot' => 'array',
            'value_json' => 'array',
            'answered_at' => 'datetime',
        ];
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(SalesQualification::class, 'sales_qualification_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SalesQualificationQuestion::class, 'sales_qualification_question_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QualificationAttachment::class);
    }
}
