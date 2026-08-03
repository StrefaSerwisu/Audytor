<?php

namespace App\Providers;

use App\Models\Audit;
use App\Models\AuditAnswerAttachment;
use App\Models\AuditControlDefinition;
use App\Models\AuditFollowUpTask;
use App\Models\AuditLog;
use App\Models\AuditOrder;
use App\Models\AuditOrderAssignee;
use App\Models\AuditOrderDocument;
use App\Models\AuditPreparationItem;
use App\Models\AuditPublication;
use App\Models\AuditReportExport;
use App\Models\AuditType;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
use App\Models\PricingRule;
use App\Models\QualificationAttachment;
use App\Models\Quotation;
use App\Models\SalesQualification;
use App\Models\SalesQualificationQuestion;
use App\Models\User;
use App\Observers\AuditLibraryObserver;
use App\Observers\UserObserver;
use App\Policies\AuditAnswerAttachmentPolicy;
use App\Policies\AuditControlDefinitionPolicy;
use App\Policies\AuditFollowUpTaskPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\AuditOrderAssigneePolicy;
use App\Policies\AuditOrderDocumentPolicy;
use App\Policies\AuditOrderPolicy;
use App\Policies\AuditPolicy;
use App\Policies\AuditPreparationItemPolicy;
use App\Policies\AuditPublicationPolicy;
use App\Policies\AuditReportExportPolicy;
use App\Policies\AuditTypeModulePolicy;
use App\Policies\AuditTypePolicy;
use App\Policies\AuditTypeVersionPolicy;
use App\Policies\PricingRulePolicy;
use App\Policies\QualificationAttachmentPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\SalesQualificationPolicy;
use App\Policies\SalesQualificationQuestionPolicy;
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
        Gate::policy(AuditOrder::class, AuditOrderPolicy::class);
        Gate::policy(AuditOrderAssignee::class, AuditOrderAssigneePolicy::class);
        Gate::policy(AuditPreparationItem::class, AuditPreparationItemPolicy::class);
        Gate::policy(AuditOrderDocument::class, AuditOrderDocumentPolicy::class);
        Gate::policy(AuditAnswerAttachment::class, AuditAnswerAttachmentPolicy::class);
        Gate::policy(AuditReportExport::class, AuditReportExportPolicy::class);
        Gate::policy(AuditPublication::class, AuditPublicationPolicy::class);
        Gate::policy(AuditFollowUpTask::class, AuditFollowUpTaskPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AuditType::class, AuditTypePolicy::class);
        Gate::policy(AuditTypeVersion::class, AuditTypeVersionPolicy::class);
        Gate::policy(AuditTypeModule::class, AuditTypeModulePolicy::class);
        Gate::policy(SalesQualificationQuestion::class, SalesQualificationQuestionPolicy::class);
        Gate::policy(AuditControlDefinition::class, AuditControlDefinitionPolicy::class);
        Gate::policy(SalesQualification::class, SalesQualificationPolicy::class);
        Gate::policy(QualificationAttachment::class, QualificationAttachmentPolicy::class);
        Gate::policy(PricingRule::class, PricingRulePolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);

        User::observe(UserObserver::class);
        AuditType::observe(AuditLibraryObserver::class);
        AuditTypeVersion::observe(AuditLibraryObserver::class);
        AuditTypeModule::observe(AuditLibraryObserver::class);
        SalesQualificationQuestion::observe(AuditLibraryObserver::class);
        AuditControlDefinition::observe(AuditLibraryObserver::class);

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
    }
}
