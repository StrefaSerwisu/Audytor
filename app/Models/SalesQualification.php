<?php

namespace App\Models;

use App\Enums\SalesQualificationStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesQualification extends Model
{
    protected $fillable = [
        'client_id', 'client_location_id', 'audit_type_id', 'audit_type_version_id', 'title',
        'purpose', 'expected_date', 'contact_name', 'contact_email', 'contact_phone',
        'sales_owner_id', 'status', 'qualification_snapshot', 'scope_summary', 'internal_notes',
        'completed_at', 'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_date' => 'date',
            'status' => SalesQualificationStatus::class,
            'qualification_snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ClientLocation::class, 'client_location_id');
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(AuditType::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AuditTypeVersion::class, 'audit_type_version_id');
    }

    public function salesOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_owner_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QualificationAnswer::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QualificationAttachment::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function currentQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->where('is_current', true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Sales)) {
            return $query->where('sales_owner_id', $user->id);
        }

        return $query;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            SalesQualificationStatus::Draft,
            SalesQualificationStatus::InProgress,
            SalesQualificationStatus::WaitingForClient,
        ], true);
    }
}
