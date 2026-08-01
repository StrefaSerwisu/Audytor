<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'client_id',
        'mfa_enabled',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_enabled' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active && in_array($this->role, [
            'super_admin',
            'global_admin',
            'technical_lead',
            'sales',
        ], true);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function auditAssignments(): HasMany
    {
        return $this->hasMany(AuditAssignee::class);
    }

    public function assignedAudits(): BelongsToMany
    {
        return $this->belongsToMany(Audit::class, 'audit_assignees')
            ->withPivot('role_in_audit');
    }

    public function auditAnswers(): HasMany
    {
        return $this->hasMany(AuditAnswer::class, 'answered_by');
    }

    public function auditAnswerAttachments(): HasMany
    {
        return $this->hasMany(AuditAnswerAttachment::class, 'uploaded_by');
    }

    public function auditReviews(): HasMany
    {
        return $this->hasMany(AuditReview::class, 'reviewer_id');
    }

    public function auditPublications(): HasMany
    {
        return $this->hasMany(AuditPublication::class, 'published_by');
    }

    public function auditClosures(): HasMany
    {
        return $this->hasMany(AuditClosure::class, 'closed_by');
    }

    public function auditNotifications(): HasMany
    {
        return $this->hasMany(AuditNotification::class);
    }

    public function ownedFollowUpTasks(): HasMany
    {
        return $this->hasMany(AuditFollowUpTask::class, 'owner_id');
    }

    public function queuedReportExports(): HasMany
    {
        return $this->hasMany(AuditReportExport::class, 'queued_by');
    }
}
