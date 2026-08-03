<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishAuditReportRequest;
use App\Http\Requests\QueueAuditReportExportRequest;
use App\Jobs\GenerateAuditReportExport;
use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditQuestion;
use App\Models\Recommendation;
use App\Models\User;
use App\Support\AuditLogService;
use App\Support\AuditNotifier;
use App\Support\AuditReportData;
use App\Support\SimpleDocx;
use App\Support\SimplePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AuditReportController extends Controller
{
    public function technical(Request $request, Audit $audit): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('generateTechnicalReport', $audit), 403);
        $this->ensureReportable($audit);

        $audit = $this->loadReportAudit($audit);

        return view('reports.technical', [
            'audit' => $audit,
            'answersByQuestion' => $audit->answers->keyBy('audit_question_id'),
            'riskLevels' => AuditAnswer::RISK_LEVELS,
            'riskSummary' => $this->riskSummary($audit),
        ]);
    }

    public function business(Request $request, Audit $audit): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('generateBusinessReport', $audit), 403);
        $this->ensureReportable($audit);

        $audit = $this->loadReportAudit($audit);

        return view('reports.business', [
            'audit' => $audit,
            'riskLevels' => AuditAnswer::RISK_LEVELS,
            'riskSummary' => $this->riskSummary($audit),
            'recommendations' => $this->recommendationsFor($audit),
        ]);
    }

    public function sales(Request $request, Audit $audit): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('generateSalesReport', $audit), 403);
        $this->ensureReportable($audit);

        $audit = $this->loadReportAudit($audit);
        $opportunities = $this->salesOpportunitiesFor($audit);

        return view('reports.sales', [
            'audit' => $audit,
            'riskLevels' => AuditAnswer::RISK_LEVELS,
            'priorityLabels' => Recommendation::PRIORITIES,
            'riskSummary' => $this->riskSummary($audit),
            'opportunities' => $opportunities,
            'hoursSummary' => [
                'min' => $opportunities->sum(fn ($item) => $item['estimated_hours_min'] ?? 0),
                'max' => $opportunities->sum(fn ($item) => $item['estimated_hours_max'] ?? 0),
            ],
            'categories' => $opportunities
                ->groupBy(fn ($item) => $item['sales_category'] ?: 'Bez kategorii')
                ->map->count()
                ->sortDesc(),
        ]);
    }

    public function publish(PublishAuditReportRequest $request, Audit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('publish', $audit), 403);
        $this->ensurePublishable($audit);

        $validated = $request->validated();

        $publication = $audit->publications()->create([
            'published_by' => $user->id,
            'token' => Str::random(48),
            'notes' => $validated['notes'] ?? null,
            'published_at' => now(),
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        $oldStatus = $audit->status;

        $audit->forceFill([
            'status' => 'published_to_client',
        ])->save();

        AuditLogService::record(
            'report.published',
            $publication,
            oldValues: ['audit_status' => $oldStatus],
            newValues: [
                'audit_status' => $audit->status,
                'expires_at' => $publication->expires_at,
            ],
            metadata: ['audit_id' => $audit->id],
        );

        AuditNotifier::notifyAssignees(
            $audit,
            'report_published',
            'Raport opublikowany dla klienta',
            "{$audit->title} ma aktywny link klienta.",
            route('client.reports.show', $publication->token),
        );

        return redirect()
            ->route('client.reports.show', $publication->token)
            ->with('status', 'Raport opublikowany dla klienta.');
    }

    public function pdf(Request $request, Audit $audit, string $type): Response
    {
        return $this->downloadReport($request, $audit, $type, 'pdf');
    }

    public function docx(Request $request, Audit $audit, string $type): Response
    {
        return $this->downloadReport($request, $audit, $type, 'docx');
    }

    public function queueExport(QueueAuditReportExportRequest $request, Audit $audit, string $type): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorizeReportType($user, $audit, $type);
        $this->ensureReportable($audit);

        $validated = $request->validated();

        $export = $audit->reportExports()->create([
            'queued_by' => $user->id,
            'report_type' => $type,
            'format' => $validated['format'],
            'status' => 'queued',
        ]);

        AuditLogService::record('report_export.queued', $export, newValues: [
            'audit_id' => $audit->id,
            'report_type' => $type,
            'format' => $validated['format'],
            'status' => 'queued',
        ]);

        GenerateAuditReportExport::dispatch($export->id);

        return back()->with('status', 'Eksport raportu zostal dodany do kolejki.');
    }

    private function loadReportAudit(Audit $audit): Audit
    {
        return $audit->load([
            'client',
            'location',
            'template',
            'leadReviewer',
            'closures.closer',
            'reviews.reviewer',
            'answers.attachments',
            'selectedModules' => fn ($query) => $query->orderBy('sort_order'),
            'selectedModules.module.questions' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('sort_order')
                ->with('recommendations'),
        ]);
    }

    private function ensureReportable(Audit $audit): void
    {
        abort_unless(in_array($audit->status, ['technically_approved', 'reports_generated', 'published_to_client', 'closed'], true), 409);
    }

    private function ensurePublishable(Audit $audit): void
    {
        abort_unless(in_array($audit->status, ['technically_approved', 'reports_generated'], true), 409);
    }

    private function downloadReport(Request $request, Audit $audit, string $type, string $format): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorizeReportType($user, $audit, $type);
        $this->ensureReportable($audit);

        $audit = $this->loadReportAudit($audit);
        $lines = AuditReportData::lines($audit, $type);
        $title = AuditReportData::label($type).' - '.$audit->title;
        $content = $format === 'docx'
            ? SimpleDocx::make($title, $lines)
            : SimplePdf::make($title, $lines);
        $extension = $format === 'docx' ? 'docx' : 'pdf';
        $contentType = $format === 'docx'
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/pdf';

        AuditLogService::record('report.downloaded', $audit, metadata: [
            'report_type' => $type,
            'format' => $format,
        ]);

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$this->filename($audit, $type, $extension).'"',
        ]);
    }

    private function authorizeReportType(User $user, Audit $audit, string $type): void
    {
        abort_unless(in_array($type, ['technical', 'business', 'sales'], true), 404);

        if ($type === 'sales') {
            abort_unless($user->can('generateSalesReport', $audit), 403);

            return;
        }

        abort_unless($user->can($type === 'technical' ? 'generateTechnicalReport' : 'generateBusinessReport', $audit), 403);
    }

    private function filename(Audit $audit, string $type, string $extension): string
    {
        return Str::slug($type.'-'.$audit->title).'.'.$extension;
    }

    /**
     * @return array<string, int>
     */
    private function riskSummary(Audit $audit): array
    {
        $summary = array_fill_keys(array_keys(AuditAnswer::RISK_LEVELS), 0);

        foreach ($audit->answers as $answer) {
            if ($answer->risk_level && array_key_exists($answer->risk_level, $summary)) {
                $summary[$answer->risk_level]++;
            }
        }

        return $summary;
    }

    /**
     * @return Collection<int, array{source:string, title:string, risk_level:?string, priority:?string, business_description:?string, recommendation_text:string, sales_category:?string}>
     */
    private function recommendationsFor(Audit $audit): Collection
    {
        $answers = $audit->answers->keyBy('audit_question_id');

        return $audit->selectedModules
            ->flatMap(fn ($selectedModule) => $selectedModule->module?->questions ?? collect())
            ->flatMap(function (AuditQuestion $question) use ($answers): Collection {
                $answer = $answers->get($question->id);
                $items = collect();

                if ($answer?->recommendation_text) {
                    $items->push([
                        'source' => $question->question,
                        'title' => 'Rekomendacja audytora',
                        'risk_level' => $answer->risk_level,
                        'priority' => $answer->risk_level,
                        'business_description' => null,
                        'recommendation_text' => $answer->recommendation_text,
                        'sales_category' => null,
                    ]);
                }

                foreach ($question->recommendations as $recommendation) {
                    $items->push([
                        'source' => $question->question,
                        'title' => $recommendation->title,
                        'risk_level' => $recommendation->risk_level,
                        'priority' => $recommendation->priority,
                        'business_description' => $recommendation->business_description,
                        'recommendation_text' => $recommendation->recommendation_text,
                        'sales_category' => $recommendation->sales_category,
                    ]);
                }

                return $items;
            })
            ->values();
    }

    /**
     * @return Collection<int, array{source:string, title:string, risk_level:?string, priority:?string, recommendation_text:string, sales_category:?string, estimated_hours_min:?int, estimated_hours_max:?int, suggested_deadline:?string, global_it_can_do:bool}>
     */
    private function salesOpportunitiesFor(Audit $audit): Collection
    {
        $answers = $audit->answers->keyBy('audit_question_id');

        return $audit->selectedModules
            ->flatMap(fn ($selectedModule) => $selectedModule->module?->questions ?? collect())
            ->flatMap(function (AuditQuestion $question) use ($answers): Collection {
                $answer = $answers->get($question->id);

                return $question->recommendations
                    ->filter(fn (Recommendation $recommendation) => $recommendation->global_it_can_do)
                    ->map(fn (Recommendation $recommendation) => [
                        'source' => $question->question,
                        'title' => $recommendation->title,
                        'risk_level' => $answer?->risk_level ?? $recommendation->risk_level,
                        'priority' => $recommendation->priority,
                        'recommendation_text' => $recommendation->recommendation_text,
                        'sales_category' => $recommendation->sales_category,
                        'estimated_hours_min' => $recommendation->estimated_hours_min,
                        'estimated_hours_max' => $recommendation->estimated_hours_max,
                        'suggested_deadline' => $recommendation->suggested_deadline,
                        'global_it_can_do' => $recommendation->global_it_can_do,
                    ]);
            })
            ->sortByDesc(fn ($item) => array_search($item['priority'], ['low', 'medium', 'high', 'critical'], true) ?: 0)
            ->values();
    }
}
