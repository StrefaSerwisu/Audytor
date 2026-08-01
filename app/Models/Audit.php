<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audit extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Roboczy',
        'scheduled' => 'Zaplanowany',
        'in_progress' => 'W trakcie',
        'syncing' => 'W trakcie synchronizacji',
        'needs_completion' => 'Wymaga uzupelnienia',
        'submitted_for_review' => 'Wyslany do weryfikacji',
        'changes_requested' => 'Do poprawy',
        'technically_approved' => 'Zatwierdzony technicznie',
        'reports_generated' => 'Raporty wygenerowane',
        'published_to_client' => 'Opublikowany dla klienta',
        'closed' => 'Zamkniety',
        'cancelled' => 'Anulowany',
    ];

    protected $fillable = [
        'client_id',
        'client_location_id',
        'audit_template_id',
        'title',
        'description',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'submitted_at',
        'approved_at',
        'created_by',
        'lead_reviewer_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(AuditTemplate::class, 'audit_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leadReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_reviewer_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(AuditAssignee::class);
    }

    public function selectedModules(): HasMany
    {
        return $this->hasMany(AuditSelectedModule::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AuditAnswer::class);
    }

    public function answerAttachments(): HasMany
    {
        return $this->hasMany(AuditAnswerAttachment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AuditReview::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(AuditPublication::class);
    }

    public function followUpTasks(): HasMany
    {
        return $this->hasMany(AuditFollowUpTask::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(AuditReportExport::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(AuditClosure::class);
    }

    public function auditors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'audit_assignees')
            ->withPivot('role_in_audit');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(AuditModule::class, 'audit_selected_modules')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
