<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAuditReportExport;
use App\Models\AuditReportExport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('viewAny', AuditReportExport::class), 403);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(AuditReportExport::STATUSES))],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_keys(AuditReportExport::REPORT_TYPES))],
            'format' => ['nullable', 'string', 'in:'.implode(',', array_keys(AuditReportExport::FORMATS))],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $baseQuery = $this->visibleExportsQuery($user);

        $exports = (clone $baseQuery)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('report_type', $type))
            ->when($filters['format'] ?? null, fn ($query, $format) => $query->where('format', $format))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereHas('audit', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('audit.client', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('audit.location', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->limit(80)
            ->get();

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return view('reports.exports.index', [
            'exports' => $exports,
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'statuses' => AuditReportExport::STATUSES,
            'reportTypes' => AuditReportExport::REPORT_TYPES,
            'formats' => AuditReportExport::FORMATS,
        ]);
    }

    public function download(Request $request, AuditReportExport $export): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('download', $export), 403);
        abort_unless($export->status === 'completed' && $export->path, 409);
        abort_unless(Storage::disk('local')->exists($export->path), 404);

        return Storage::disk('local')->download(
            $export->path,
            $this->filename($export),
            ['Content-Type' => $this->contentType($export->format)],
        );
    }

    public function retry(Request $request, AuditReportExport $export): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('retry', $export), 403);
        abort_unless($export->status === 'failed', 409);

        $export->update([
            'status' => 'queued',
            'error' => null,
            'path' => null,
            'completed_at' => null,
        ]);

        GenerateAuditReportExport::dispatch($export->id);

        return redirect()
            ->route('reports.exports.index')
            ->with('status', 'Eksport zostal ponownie dodany do kolejki.');
    }

    private function visibleExportsQuery(User $user)
    {
        return AuditReportExport::query()
            ->with(['audit.client', 'audit.location', 'queuedBy'])
            ->when(! $user->canManageAllAudits(), fn ($query) => $query
                ->whereHas('audit', fn ($query) => $query->where('lead_reviewer_id', $user->id)));
    }

    private function filename(AuditReportExport $export): string
    {
        $auditTitle = str($export->audit?->title ?? 'audyt')->slug();

        return "{$export->report_type}-{$auditTitle}-{$export->id}.{$export->format}";
    }

    private function contentType(string $format): string
    {
        return $format === 'docx'
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/pdf';
    }
}
