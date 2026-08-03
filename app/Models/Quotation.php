<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quotation extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'PLN',
        'base_hours' => '0.00',
        'additional_hours' => '0.00',
        'total_hours' => '0.00',
        'engineers_count' => 1,
        'hourly_rate' => '0.00',
        'base_price' => '0.00',
        'additional_costs' => '0.00',
        'discount_value' => '0.00',
        'discount_amount' => '0.00',
        'net_price' => '0.00',
        'tax_rate' => '23.00',
        'tax_amount' => '0.00',
        'gross_price' => '0.00',
    ];

    protected $fillable = [
        'number', 'sales_qualification_id', 'client_id', 'audit_type_id', 'audit_type_version_id',
        'sales_owner_id', 'version', 'is_current', 'status', 'currency', 'base_hours',
        'additional_hours', 'total_hours', 'engineers_count', 'hourly_rate', 'base_price',
        'additional_costs', 'discount_type', 'discount_value', 'discount_amount', 'net_price',
        'tax_rate', 'tax_amount', 'gross_price', 'valid_until', 'assumptions', 'exclusions',
        'internal_notes', 'client_notes', 'calculation_snapshot', 'final_calculation_snapshot',
        'calculated_at', 'calculated_by', 'internally_approved_at', 'internally_approved_by',
        'sent_at', 'sent_by', 'accepted_at', 'accepted_by', 'purchase_order_number',
        'acceptance_comment', 'rejected_at', 'rejection_reason', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'is_current' => 'boolean',
            'base_hours' => 'decimal:2', 'additional_hours' => 'decimal:2', 'total_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2', 'base_price' => 'decimal:2', 'additional_costs' => 'decimal:2',
            'discount_value' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_price' => 'decimal:2',
            'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2', 'gross_price' => 'decimal:2',
            'valid_until' => 'date', 'calculation_snapshot' => 'array', 'final_calculation_snapshot' => 'array',
            'calculated_at' => 'datetime', 'internally_approved_at' => 'datetime',
            'sent_at' => 'datetime', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime',
        ];
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(SalesQualification::class, 'sales_qualification_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(AuditType::class);
    }

    public function versionDefinition(): BelongsTo
    {
        return $this->belongsTo(AuditTypeVersion::class, 'audit_type_version_id');
    }

    public function salesOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_owner_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort_order');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(QuotationOverride::class)->latest('created_at');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject')->latest();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasRole(UserRole::Sales) ? $query->where('sales_owner_id', $user->id) : $query;
    }

    public function canBeOverridden(): bool
    {
        return in_array($this->status, [QuotationStatus::Calculated, QuotationStatus::InternalReview], true);
    }
}
