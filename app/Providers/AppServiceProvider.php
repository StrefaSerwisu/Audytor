<?php

namespace App\Providers;

use App\Models\Audit;
use App\Models\AuditAnswerAttachment;
use App\Models\AuditFollowUpTask;
use App\Models\AuditPublication;
use App\Models\AuditReportExport;
use App\Policies\AuditAnswerAttachmentPolicy;
use App\Policies\AuditFollowUpTaskPolicy;
use App\Policies\AuditPolicy;
use App\Policies\AuditPublicationPolicy;
use App\Policies\AuditReportExportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Audit::class, AuditPolicy::class);
        Gate::policy(AuditAnswerAttachment::class, AuditAnswerAttachmentPolicy::class);
        Gate::policy(AuditReportExport::class, AuditReportExportPolicy::class);
        Gate::policy(AuditPublication::class, AuditPublicationPolicy::class);
        Gate::policy(AuditFollowUpTask::class, AuditFollowUpTaskPolicy::class);
    }
}
