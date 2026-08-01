<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditFollowUpTask;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FollowUpTaskController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('viewAny', AuditFollowUpTask::class), 403);

        $filters = $this->validatedFilters($request);
        $tasks = $this->filteredTasks($filters)->get();

        return view('follow-ups.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'statuses' => AuditFollowUpTask::STATUSES,
            'priorities' => AuditFollowUpTask::PRIORITIES,
            'owners' => User::query()
                ->where('active', true)
                ->whereIn('role', [
                    UserRole::SuperAdmin->value,
                    UserRole::GlobalAdmin->value,
                    UserRole::TechnicalLead->value,
                    UserRole::Auditor->value,
                    UserRole::Sales->value,
                ])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('export', AuditFollowUpTask::class), 403);

        $filters = $this->validatedFilters($request);
        $rows = [[
            'ID',
            'Klient',
            'Lokalizacja',
            'Audyt',
            'Zadanie',
            'Opis',
            'Status',
            'Priorytet',
            'Wlasciciel',
            'Termin',
            'Widoczne dla klienta',
            'Notatki',
        ]];

        foreach ($this->filteredTasks($filters)->get() as $task) {
            $rows[] = [
                $task->id,
                $task->audit->client->name,
                $task->audit->location->name,
                $task->audit->title,
                $task->title,
                $task->description,
                AuditFollowUpTask::STATUSES[$task->status] ?? $task->status,
                $task->priority ? (AuditFollowUpTask::PRIORITIES[$task->priority] ?? $task->priority) : '',
                $task->owner?->name ?? '',
                $task->due_date?->format('Y-m-d') ?? '',
                $task->client_visible ? 'Tak' : 'Nie',
                $task->notes,
            ];
        }

        return $this->streamCsv('audytor-it-plan-wdrozen.csv', $rows);
    }

    public function update(Request $request, AuditFollowUpTask $task): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('update', $task), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,planned,in_progress,done,rejected'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'client_visible' => ['nullable', 'boolean'],
        ]);

        $task->update([
            'status' => $validated['status'],
            'priority' => $validated['priority'] ?? null,
            'owner_id' => $validated['owner_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'client_visible' => (bool) ($validated['client_visible'] ?? false),
        ]);

        return back()->with('status', 'Zadanie follow-up zostalo zaktualizowane.');
    }

    /**
     * @return array{status?: string, priority?: string, q?: string}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'string', 'in:new,planned,in_progress,done,rejected'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function filteredTasks(array $filters)
    {
        return AuditFollowUpTask::query()
            ->with(['audit.client', 'audit.location', 'owner'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('audit.client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%"));
                });
            })
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
}
