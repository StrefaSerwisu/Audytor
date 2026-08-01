<?php

namespace App\Jobs;

use App\Models\AuditReportExport;
use App\Support\AuditReportData;
use App\Support\SimpleDocx;
use App\Support\SimplePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAuditReportExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = AuditReportExport::with('audit')->findOrFail($this->exportId);

        try {
            $export->update(['status' => 'processing']);

            $lines = AuditReportData::lines($export->audit, $export->report_type);
            $title = AuditReportData::label($export->report_type).' - '.$export->audit->title;
            $content = $export->format === 'docx'
                ? SimpleDocx::make($title, $lines)
                : SimplePdf::make($title, $lines);
            $path = "report-exports/audit-{$export->audit_id}/{$export->report_type}-{$export->id}.{$export->format}";

            Storage::disk('local')->put($path, $content);

            $export->update([
                'status' => 'completed',
                'path' => $path,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
