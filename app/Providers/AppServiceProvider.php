<?php

namespace App\Providers;

use App\Models\Audit;
use App\Models\AuditAnswerAttachment;
use App\Models\AuditFollowUpTask;
use App\Models\AuditLog;
use App\Models\AuditPublication;
use App\Models\AuditReportExport;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\AuditAnswerAttachmentPolicy;
use App\Policies\AuditFollowUpTaskPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\AuditPolicy;
use App\Policies\AuditPublicationPolicy;
use App\Policies\AuditReportExportPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        User::observe(UserObserver::class);

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
    }
}
