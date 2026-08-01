<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\User;
use App\Support\AuditNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditArchiveController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $filters = $this->validatedFilters($request);
        $audits = $this->filteredAudits($user, $filters)->get();

        return view('archive.index', [
            'audits' => $audits,
            'filters' => $filters,
            'statusOptions' => collect(['closed', 'cancelled'])
                ->mapWithKeys(fn ($status) => [$status => Audit::STATUSES[$status]])
                ->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filters = $this->validatedFilters($request);
        $rows = [[
            'ID',
            'Tytul',
            'Klient',
            'Lokalizacja',
            'Status',
            'Lider',
            'Zamknieto',
            'Zamknal',
            'Publikacja klienta',
            'Notatka zamykajaca',
        ]];

        foreach ($this->filteredAudits($user, $filters)->get() as $audit) {
            $closure = $audit->closures->sortByDesc('closed_at')->first();
            $publication = $audit->publications->sortByDesc('published_at')->first();

            $rows[] = [
                $audit->id,
                $audit->title,
                $audit->client->name,
                $audit->location->name,
                Audit::STATUSES[$audit->status] ?? $audit->status,
                $audit->leadReviewer?->name ?? '',
                ($closure?->closed_at ?? $audit->completed_at)?->format('Y-m-d H:i') ?? '',
                $closure?->closer?->name ?? '',
                $publication?->published_at?->format('Y-m-d H:i') ?? '',
                $closure?->notes ?? '',
            ];
        }

        return $this->streamCsv('audytor-it-archiwum.csv', $rows);
    }

    public function show(Request $request, Audit $audit): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('view', $audit), 403);
        abort_unless(in_array($audit->status, ['closed', 'cancelled'], true), 404);

        $audit->load([
            'client',
            'location',
            'template',
            'leadReviewer',
            'publications.publisher',
            'closures.closer',
            'reviews.reviewer',
            'answers',
        ]);

        return view('archive.show', [
            'audit' => $audit,
            'riskLevels' => AuditAnswer::RISK_LEVELS,
            'riskSummary' => $this->riskSummary($audit),
        ]);
    }

    public function close(Request $request, Audit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('close', $audit), 403);
        abort_unless($audit->status === 'published_to_client', 409);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $audit->closures()->create([
            'closed_by' => $user->id,
            'notes' => $validated['notes'] ?? null,
            'closed_at' => now(),
        ]);

        $audit->forceFill([
            'status' => 'closed',
            'completed_at' => now(),
        ])->save();

        AuditNotifier::notifyAssignees(
            $audit,
            'audit_closed',
            'Audyt zamkniety',
            "{$audit->title} zostal przeniesiony do archiwum.",
            route('archive.show', $audit),
        );

        return redirect()
            ->route('archive.show', $audit)
            ->with('status', 'Audyt zostal zamkniety i przeniesiony do archiwum.');
    }

    /**
     * @return array{q?: string, status?: string, client?: string, closed_from?: string, closed_to?: string}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:closed,cancelled'],
            'client' => ['nullable', 'string', 'max:120'],
            'closed_from' => ['nullable', 'date'],
            'closed_to' => ['nullable', 'date'],
        ]);
    }

    private function filteredAudits(User $user, array $filters)
    {
        return Audit::query()
            ->with(['client', 'location', 'leadReviewer', 'publications', 'closures.closer'])
            ->whereIn('status', ['closed', 'cancelled'])
            ->when(! $user->canManageAllAudits(), fn ($query) => $query->where('lead_reviewer_id', $user->id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['client'] ?? null, function ($query, $client): void {
                $query->whereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$client}%"));
            })
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('leadReviewer', fn ($leadQuery) => $leadQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['closed_from'] ?? null, fn ($query, $date) => $query->where('completed_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['closed_to'] ?? null, fn ($query, $date) => $query->where('completed_at', '<=', Carbon::parse($date)->endOfDay()))
            ->latest('completed_at')
            ->latest();
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
}
