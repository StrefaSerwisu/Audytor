<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    protected $fillable = [
        'quotation_id', 'code', 'name', 'description', 'category', 'source_type', 'source_id',
        'quantity', 'unit', 'unit_hours', 'total_hours', 'unit_price', 'total_price',
        'sort_order', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'unit_hours' => 'decimal:2', 'total_hours' => 'decimal:2',
            'unit_price' => 'decimal:2', 'total_price' => 'decimal:2', 'metadata' => 'array',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
