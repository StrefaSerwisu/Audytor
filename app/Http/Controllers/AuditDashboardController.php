<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditDashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $audits = $this->visibleAudits($user)
            ->with(['client', 'location', 'leadReviewer', 'publications', 'answers'])
            ->latest()
            ->get();

        $openStatuses = ['scheduled', 'in_progress', 'needs_completion', 'submitted_for_review', 'changes_requested'];
        $reportStatuses = ['technically_approved', 'reports_generated', 'published_to_client'];
        $historyStatuses = ['closed', 'cancelled'];

        return view('dashboard.index', [
            'audits' => $audits->take(8),
            'kpis' => [
                'all' => $audits->count(),
                'open' => $audits->whereIn('status', $openStatuses)->count(),
                'review' => $audits->where('status', 'submitted_for_review')->count(),
                'reports' => $audits->whereIn('status', $reportStatuses)->count(),
                'published' => $audits->where('status', 'published_to_client')->count(),
                'closed' => $audits->whereIn('status', $historyStatuses)->count(),
            ],
            'statusCounts' => $this->statusCounts($audits),
            'riskSummary' => $this->riskSummary($audits),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $audits = $this->visibleAudits($user)
            ->with('answers')
            ->latest()
            ->get();

        $openStatuses = ['scheduled', 'in_progress', 'needs_completion', 'submitted_for_review', 'changes_requested'];
        $reportStatuses = ['technically_approved', 'reports_generated', 'published_to_client'];
        $historyStatuses = ['closed', 'cancelled'];
        $statusCounts = $this->statusCounts($audits);
        $riskSummary = $this->riskSummary($audits);

        $rows = [
            ['Sekcja', 'Metryka', 'Wartosc'],
            ['KPI', 'Wszystkie audyty', $audits->count()],
            ['KPI', 'Otwarte', $audits->whereIn('status', $openStatuses)->count()],
            ['KPI', 'Do weryfikacji', $audits->where('status', 'submitted_for_review')->count()],
            ['KPI', 'Raporty', $audits->whereIn('status', $reportStatuses)->count()],
            ['KPI', 'Opublikowane', $audits->where('status', 'published_to_client')->count()],
            ['KPI', 'Historyczne', $audits->whereIn('status', $historyStatuses)->count()],
        ];

        foreach ($statusCounts as $status => $count) {
            if ($count > 0) {
                $rows[] = ['Status', Audit::STATUSES[$status] ?? $status, $count];
            }
        }

        foreach ($riskSummary as $risk => $count) {
            $rows[] = ['Ryzyko', AuditAnswer::RISK_LEVELS[$risk] ?? $risk, $count];
        }

        return $this->streamCsv('audytor-it-dashboard-kpi.csv', $rows);
    }

    private function visibleAudits(User $user): Builder
    {
        return Audit::query()
            ->when(! $this->canViewAllAudits($user), fn ($query) => $query->where('lead_reviewer_id', $user->id));
    }

    private function canViewAllAudits(User $user): bool
    {
        return $user->canManageAllAudits();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function streamCsv(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            echo "\xEF\xBB\xBF";

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  Collection<int, Audit>  $audits
     * @return array<string, int>
     */
    private function statusCounts(Collection $audits): array
    {
        $counts = array_fill_keys(array_keys(Audit::STATUSES), 0);

        foreach ($audits as $audit) {
            if (array_key_exists($audit->status, $counts)) {
                $counts[$audit->status]++;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Audit>  $audits
     * @return array<string, int>
     */
    private function riskSummary(Collection $audits): array
    {
        $summary = array_fill_keys(array_keys(AuditAnswer::RISK_LEVELS), 0);

        foreach ($audits->flatMap->answers as $answer) {
            if ($answer->risk_level && array_key_exists($answer->risk_level, $summary)) {
                $summary[$answer->risk_level]++;
            }
        }

        return $summary;
    }
}
