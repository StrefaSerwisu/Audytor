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
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\TechnicalReviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/auditor');

Route::middleware('guest')->group(function () {
    Route::get('/auditor/login', [AuditorAuthController::class, 'create'])->name('login');
    Route::post('/auditor/login', [AuditorAuthController::class, 'store'])->name('auditor.login.store');
    Route::get('/client/login', [ClientPortalAuthController::class, 'create'])->name('client.login');
    Route::post('/client/login', [ClientPortalAuthController::class, 'store'])->name('client.login.store');
});

Route::post('/auditor/logout', [AuditorAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('auditor.logout');

Route::post('/client/logout', [ClientPortalAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('client.logout');

Route::middleware('auth')->prefix('auditor')->name('auditor.')->group(function () {
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

Route::middleware('auth')->prefix('reviewer')->name('reviewer.')->group(function () {
    Route::get('/', [TechnicalReviewController::class, 'index'])->name('index');
    Route::get('/audits/{audit}', [TechnicalReviewController::class, 'show'])->name('audits.show');
    Route::post('/audits/{audit}/approve', [TechnicalReviewController::class, 'approve'])->name('audits.approve');
    Route::post('/audits/{audit}/request-changes', [TechnicalReviewController::class, 'requestChanges'])->name('audits.request-changes');
});

Route::middleware('auth')->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [AuditDashboardController::class, 'index'])->name('index');
    Route::get('/export', [AuditDashboardController::class, 'export'])->name('export');
});

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [AuditNotificationController::class, 'index'])->name('index');
    Route::post('/read-all', [AuditNotificationController::class, 'markAllRead'])->name('read-all');
    Route::post('/{notification}/read', [AuditNotificationController::class, 'markRead'])->name('read');
});

Route::middleware('auth')->prefix('follow-ups')->name('follow-ups.')->group(function () {
    Route::get('/', [FollowUpTaskController::class, 'index'])->name('index');
    Route::get('/export', [FollowUpTaskController::class, 'export'])->name('export');
    Route::post('/{task}', [FollowUpTaskController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/exports', [ReportExportController::class, 'index'])->name('exports.index');
    Route::get('/exports/{export}/download', [ReportExportController::class, 'download'])->name('exports.download');
    Route::post('/exports/{export}/retry', [ReportExportController::class, 'retry'])->name('exports.retry');
    Route::get('/audits/{audit}/technical', [AuditReportController::class, 'technical'])->name('technical');
    Route::get('/audits/{audit}/business', [AuditReportController::class, 'business'])->name('business');
    Route::get('/audits/{audit}/sales', [AuditReportController::class, 'sales'])->name('sales');
    Route::get('/audits/{audit}/{type}/pdf', [AuditReportController::class, 'pdf'])->name('download.pdf');
    Route::get('/audits/{audit}/{type}/docx', [AuditReportController::class, 'docx'])->name('download.docx');
    Route::post('/audits/{audit}/{type}/queue-export', [AuditReportController::class, 'queueExport'])->name('queue-export');
    Route::post('/audits/{audit}/publish', [AuditReportController::class, 'publish'])->name('publish');
    Route::post('/audits/{audit}/close', [AuditArchiveController::class, 'close'])->name('close');
});

Route::middleware('auth')->prefix('archive')->name('archive.')->group(function () {
    Route::get('/', [AuditArchiveController::class, 'index'])->name('index');
    Route::get('/export', [AuditArchiveController::class, 'export'])->name('export');
    Route::get('/audits/{audit}', [AuditArchiveController::class, 'show'])->name('show');
});

Route::middleware('auth')->prefix('client/portal')->name('client.portal.')->group(function () {
    Route::get('/', [ClientPortalController::class, 'index'])->name('index');
    Route::get('/reports/{publication}', [ClientPortalController::class, 'show'])->name('reports.show');
    Route::post('/reports/{publication}/status', [ClientPortalController::class, 'updateStatus'])->name('reports.status');
    Route::post('/reports/{publication}/feedback', [ClientPortalController::class, 'updateFeedback'])->name('reports.feedback');
});

Route::get('/client/reports/{token}', [ClientReportController::class, 'show'])->name('client.reports.show');
