<?php

use App\Http\Controllers\AuditArchiveController;
use App\Http\Controllers\AuditDashboardController;
use App\Http\Controllers\AuditNotificationController;
use App\Http\Controllers\AuditorAuditController;
use App\Http\Controllers\AuditorAuthController;
use App\Http\Controllers\AuditReportController;
use App\Http\Controllers\ClientPortalAuthController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\ClientReportController;
use App\Http\Controllers\FollowUpTaskController;
use App\Http\Controllers\QualificationAttachmentController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SalesQualificationController;
use App\Http\Controllers\TechnicalReviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/auditor');

Route::middleware('guest')->group(function () {
    Route::get('/auditor/login', [AuditorAuthController::class, 'create'])->name('login');
    Route::post('/auditor/login', [AuditorAuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('auditor.login.store');
    Route::get('/client/login', [ClientPortalAuthController::class, 'create'])->name('client.login');
    Route::post('/client/login', [ClientPortalAuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('client.login.store');
});

Route::post('/auditor/logout', [AuditorAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('auditor.logout');

Route::post('/client/logout', [ClientPortalAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('client.logout');

Route::middleware('auth')->prefix('auditor')->name('auditor.')
    ->middleware('role:auditor,technical_lead,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [AuditorAuditController::class, 'index'])->name('index');
        Route::get('/audits/{audit}', [AuditorAuditController::class, 'show'])->name('audits.show');
        Route::post('/audits/{audit}/submit', [AuditorAuditController::class, 'submitForReview'])
            ->name('audits.submit');
        Route::post('/audits/{audit}/questions/{question}', [AuditorAuditController::class, 'updateAnswer'])
            ->name('answers.update');
        Route::get('/audits/{audit}/attachments/{attachment}', [AuditorAuditController::class, 'downloadAttachment'])
            ->name('attachments.download');
        Route::delete('/audits/{audit}/attachments/{attachment}', [AuditorAuditController::class, 'deleteAttachment'])
            ->name('attachments.destroy');
    });

Route::middleware('auth')->prefix('reviewer')->name('reviewer.')
    ->middleware('role:technical_lead,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [TechnicalReviewController::class, 'index'])->name('index');
        Route::get('/audits/{audit}', [TechnicalReviewController::class, 'show'])->name('audits.show');
        Route::post('/audits/{audit}/approve', [TechnicalReviewController::class, 'approve'])->name('audits.approve');
        Route::post('/audits/{audit}/request-changes', [TechnicalReviewController::class, 'requestChanges'])->name('audits.request-changes');
    });

Route::middleware('auth')->prefix('dashboard')->name('dashboard.')
    ->middleware('role:technical_lead,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [AuditDashboardController::class, 'index'])->name('index');
        Route::get('/export', [AuditDashboardController::class, 'export'])->name('export');
    });

Route::middleware('auth')->prefix('notifications')->name('notifications.')
    ->middleware('role:auditor,technical_lead,sales,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [AuditNotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [AuditNotificationController::class, 'markAllRead'])->name('read-all');
        Route::post('/{notification}/read', [AuditNotificationController::class, 'markRead'])->name('read');
    });

Route::middleware('auth')->prefix('follow-ups')->name('follow-ups.')
    ->middleware('role:technical_lead,sales,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [FollowUpTaskController::class, 'index'])->name('index');
        Route::get('/export', [FollowUpTaskController::class, 'export'])->name('export');
        Route::post('/{task}', [FollowUpTaskController::class, 'update'])->name('update');
    });

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::middleware('role:technical_lead,global_admin,super_admin')->group(function () {
        Route::get('/exports', [ReportExportController::class, 'index'])->name('exports.index');
        Route::get('/exports/{export}/download', [ReportExportController::class, 'download'])->name('exports.download');
        Route::post('/exports/{export}/retry', [ReportExportController::class, 'retry'])->name('exports.retry');
        Route::get('/audits/{audit}/technical', [AuditReportController::class, 'technical'])->name('technical');
        Route::get('/audits/{audit}/business', [AuditReportController::class, 'business'])->name('business');
        Route::post('/audits/{audit}/{type}/queue-export', [AuditReportController::class, 'queueExport'])->name('queue-export');
        Route::post('/audits/{audit}/publish', [AuditReportController::class, 'publish'])->name('publish');
        Route::post('/audits/{audit}/close', [AuditArchiveController::class, 'close'])->name('close');
    });

    Route::middleware('role:sales,technical_lead,global_admin,super_admin')->group(function () {
        Route::get('/audits/{audit}/sales', [AuditReportController::class, 'sales'])->name('sales');
        Route::get('/audits/{audit}/{type}/pdf', [AuditReportController::class, 'pdf'])->name('download.pdf');
        Route::get('/audits/{audit}/{type}/docx', [AuditReportController::class, 'docx'])->name('download.docx');
    });
});

Route::middleware('auth')->prefix('archive')->name('archive.')
    ->middleware('role:technical_lead,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [AuditArchiveController::class, 'index'])->name('index');
        Route::get('/export', [AuditArchiveController::class, 'export'])->name('export');
        Route::get('/audits/{audit}', [AuditArchiveController::class, 'show'])->name('show');
    });

Route::middleware('auth')->prefix('sales/qualifications')->name('sales.qualifications.')
    ->middleware('role:sales,technical_lead,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [SalesQualificationController::class, 'index'])->name('index');
        Route::get('/create', [SalesQualificationController::class, 'create'])->name('create');
        Route::post('/', [SalesQualificationController::class, 'store'])->name('store');
        Route::get('/{qualification}', [SalesQualificationController::class, 'show'])->name('show');
        Route::post('/{qualification}/answers/{questionCode}', [SalesQualificationController::class, 'updateAnswer'])->name('answers.update');
        Route::post('/{qualification}/start', [SalesQualificationController::class, 'start'])->name('start');
        Route::post('/{qualification}/wait', [SalesQualificationController::class, 'waitForClient'])->name('wait');
        Route::post('/{qualification}/resume', [SalesQualificationController::class, 'resume'])->name('resume');
        Route::post('/{qualification}/complete', [SalesQualificationController::class, 'complete'])->name('complete');
        Route::post('/{qualification}/cancel', [SalesQualificationController::class, 'cancel'])->name('cancel');
        Route::post('/{qualification}/quotation', [QuotationController::class, 'store'])->name('quotation.store');
        Route::get('/attachments/{attachment}/download', [QualificationAttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('/attachments/{attachment}', [QualificationAttachmentController::class, 'destroy'])->name('attachments.destroy');
    });

Route::middleware('auth')->prefix('sales/quotations')->name('sales.quotations.')
    ->middleware('role:sales,technical_lead,global_admin,super_admin')
    ->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->name('index');
        Route::get('/{quotation}', [QuotationController::class, 'show'])->name('show');
        Route::patch('/{quotation}/override', [QuotationController::class, 'override'])->name('override');
        Route::post('/{quotation}/review', [QuotationController::class, 'review'])->name('review');
        Route::post('/{quotation}/approve', [QuotationController::class, 'approve'])->name('approve');
        Route::post('/{quotation}/return', [QuotationController::class, 'returnForChanges'])->name('return');
        Route::post('/{quotation}/send', [QuotationController::class, 'send'])->name('send');
        Route::post('/{quotation}/accept', [QuotationController::class, 'accept'])->name('accept');
        Route::post('/{quotation}/reject', [QuotationController::class, 'reject'])->name('reject');
        Route::post('/{quotation}/expire', [QuotationController::class, 'expire'])->name('expire');
        Route::post('/{quotation}/cancel', [QuotationController::class, 'cancel'])->name('cancel');
    });

Route::middleware('auth')->prefix('client/portal')->name('client.portal.')
    ->middleware('role:client')
    ->group(function () {
        Route::get('/', [ClientPortalController::class, 'index'])->name('index');
        Route::get('/reports/{publication}', [ClientPortalController::class, 'show'])->name('reports.show');
        Route::post('/reports/{publication}/status', [ClientPortalController::class, 'updateStatus'])->name('reports.status');
        Route::post('/reports/{publication}/feedback', [ClientPortalController::class, 'updateFeedback'])->name('reports.feedback');
    });

Route::get('/client/reports/{token}', [ClientReportController::class, 'show'])->name('client.reports.show');
