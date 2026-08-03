<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
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
            'role' => UserRole::class,
            'mfa_enabled' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active && $this->role->canAccessAdminPanel();
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canViewAllAudits(): bool
    {
        return $this->active && $this->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead);
    }

    public function canManageAllAudits(): bool
    {
        return $this->active && $this->hasAnyRole(UserRole::SuperAdmin, UserRole::GlobalAdmin);
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

    public function createdAudits(): HasMany
    {
        return $this->hasMany(Audit::class, 'created_by');
    }

    public function leadAudits(): HasMany
    {
        return $this->hasMany(Audit::class, 'lead_reviewer_id');
    }

    public function managedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'account_manager_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function salesQualifications(): HasMany
    {
        return $this->hasMany(SalesQualification::class, 'sales_owner_id');
    }

    public function completedQualifications(): HasMany
    {
        return $this->hasMany(SalesQualification::class, 'completed_by');
    }

    public function qualificationAnswers(): HasMany
    {
        return $this->hasMany(QualificationAnswer::class, 'answered_by');
    }

    public function qualificationAttachments(): HasMany
    {
        return $this->hasMany(QualificationAttachment::class, 'uploaded_by');
    }

    public function hasHistoricalRelations(): bool
    {
        return collect([
            'managedClients',
            'createdAudits',
            'leadAudits',
            'auditAssignments',
            'auditAnswers',
            'auditAnswerAttachments',
            'auditReviews',
            'auditPublications',
            'auditClosures',
            'auditNotifications',
            'ownedFollowUpTasks',
            'queuedReportExports',
            'auditLogs',
            'salesQualifications',
            'completedQualifications',
            'qualificationAnswers',
            'qualificationAttachments',
        ])->contains(fn (string $relation): bool => $this->{$relation}()->exists());
    }
}
