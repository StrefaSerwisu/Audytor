<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditAnswerAttachment;
use App\Models\AuditQuestion;
use App\Models\User;
use App\Support\AuditNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditorAuditController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $audits = Audit::query()
            ->with(['client', 'location', 'selectedModules.module.questions', 'answers.attachments'])
            ->when(! $this->canViewAllAudits($user), fn ($query) => $query->whereHas(
                'assignees',
                fn ($assignees) => $assignees->where('user_id', $user->id),
            ))
            ->latest('scheduled_at')
            ->get()
            ->map(fn (Audit $audit) => [
                'audit' => $audit,
                'progress' => $this->progressFor($audit),
            ]);

        return view('auditor.index', [
            'audits' => $audits,
        ]);
    }

    public function show(Request $request, Audit $audit): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canViewAudit($user, $audit), 403);

        $audit->load([
            'client',
            'location',
            'template',
            'answers.attachments',
            'selectedModules' => fn ($query) => $query->orderBy('sort_order'),
            'selectedModules.module.questions' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('sort_order')
                ->with('recommendations'),
        ]);

        return view('auditor.show', [
            'audit' => $audit,
            'answersByQuestion' => $audit->answers->keyBy('audit_question_id'),
            'progress' => $this->progressFor($audit),
            'riskLevels' => AuditAnswer::RISK_LEVELS,
            'validationMessagesByQuestion' => $this->validationMessagesFor($audit),
            'submitBlockers' => $this->submitBlockersFor($audit),
        ]);
    }

    public function updateAnswer(Request $request, Audit $audit, AuditQuestion $question): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canViewAudit($user, $audit), 403);
        abort_unless($audit->modules()->whereKey($question->audit_module_id)->exists(), 404);

        $validated = $request->validate([
            'value' => ['nullable', 'string', 'max:5000'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'not_applicable' => ['nullable', 'boolean'],
            'not_applicable_reason' => ['nullable', 'string', 'max:5000'],
            'risk_level' => ['nullable', 'in:low,medium,high,critical'],
            'recommendation_text' => ['nullable', 'string', 'max:5000'],
            'attachment_caption' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,txt,csv,doc,docx,xls,xlsx,zip'],
        ]);

        $notApplicable = (bool) ($validated['not_applicable'] ?? false);
        $riskLevel = $question->risk_enabled || $question->field_type === 'risk_level'
            ? ($validated['risk_level'] ?? null)
            : null;
        $value = match (true) {
            $notApplicable => null,
            $question->field_type === 'risk_level' => $riskLevel,
            default => $validated['value'] ?? null,
        };

        $answer = AuditAnswer::firstOrNew([
            'audit_id' => $audit->id,
            'audit_question_id' => $question->id,
        ]);

        $answer->fill([
            'audit_module_id' => $question->audit_module_id,
            'answered_by' => $user->id,
            'value_json' => $value === null || $value === '' ? null : ['value' => $value],
            'comment' => $validated['comment'] ?? null,
            'not_applicable' => $notApplicable,
            'not_applicable_reason' => $validated['not_applicable_reason'] ?? null,
            'risk_level' => $riskLevel,
            'recommendation_text' => $validated['recommendation_text'] ?? null,
            'sync_status' => 'synced',
        ]);

        if (! $answer->exists) {
            $answer->local_uuid = Str::uuid()->toString();
        }

        $answer->save();

        foreach ($request->file('attachments', []) as $file) {
            $storedPath = $file->store("audit-evidence/audits/{$audit->id}/answers/{$answer->id}", 'local');

            $answer->attachments()->create([
                'audit_id' => $audit->id,
                'audit_question_id' => $question->id,
                'audit_module_id' => $question->audit_module_id,
                'uploaded_by' => $user->id,
                'evidence_type' => $this->evidenceTypeFor($question),
                'disk' => 'local',
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'caption' => $validated['attachment_caption'] ?? null,
                'local_uuid' => Str::uuid()->toString(),
            ]);
        }

        $answer->load('attachments');
        $answer->status = $this->storedAnswerIsComplete($question, $answer) ? 'completed' : 'draft';
        $answer->save();

        $issues = $this->completionIssuesFor($question, $answer);

        if ($issues !== []) {
            return back()
                ->withErrors(['answer' => implode(' ', $issues)])
                ->withInput()
                ->with('status', 'Odpowiedz zapisana jako robocza.');
        }

        return back()->with('status', 'Odpowiedz zapisana.');
    }

    public function submitForReview(Request $request, Audit $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canViewAudit($user, $audit), 403);

        $audit->load([
            'answers.attachments',
            'selectedModules' => fn ($query) => $query->orderBy('sort_order'),
            'selectedModules.module.questions' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('sort_order'),
        ]);

        $blockers = $this->submitBlockersFor($audit);

        if ($blockers !== []) {
            return back()
                ->withErrors(['submit' => 'Audyt ma braki blokujace wyslanie do weryfikacji.'])
                ->with('submitBlockers', $blockers);
        }

        $audit->forceFill([
            'status' => 'submitted_for_review',
            'submitted_at' => now(),
        ])->save();

        if ($audit->leadReviewer) {
            AuditNotifier::notify(
                $audit->leadReviewer,
                $audit,
                'audit_submitted',
                'Audyt wyslany do weryfikacji',
                "{$audit->title} czeka na decyzje lidera technicznego.",
                route('reviewer.audits.show', $audit),
            );
        }

        return back()->with('status', 'Audyt wyslany do weryfikacji lidera technicznego.');
    }

    public function downloadAttachment(Request $request, Audit $audit, AuditAnswerAttachment $attachment): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canViewAudit($user, $audit), 403);
        abort_unless($attachment->audit_id === $audit->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function deleteAttachment(Request $request, Audit $audit, AuditAnswerAttachment $attachment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canViewAudit($user, $audit), 403);
        abort_unless($attachment->audit_id === $audit->id, 404);

        Storage::disk($attachment->disk)->delete($attachment->path);

        $answer = $attachment->answer;
        $question = $attachment->question;
        $attachment->delete();

        if ($answer && $question) {
            $answer->load('attachments');
            $answer->status = $this->storedAnswerIsComplete($question, $answer) ? 'completed' : 'draft';
            $answer->save();
        }

        return back()->with('status', 'Zalacznik usuniety.');
    }

    private function canViewAudit(User $user, Audit $audit): bool
    {
        if ($this->canViewAllAudits($user)) {
            return true;
        }

        return $audit->assignees()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function canViewAllAudits(User $user): bool
    {
        return $user->active && in_array($user->role, [
            'super_admin',
            'global_admin',
            'technical_lead',
        ], true);
    }

    /**
     * @return array{total:int, completed:int, missing:int, percent:int}
     */
    private function progressFor(Audit $audit): array
    {
        $questions = $this->questionsFor($audit);
        $answers = $audit->answers->keyBy('audit_question_id');
        $completed = $questions
            ->filter(fn (AuditQuestion $question) => $this->storedAnswerIsComplete($question, $answers->get($question->id)))
            ->count();

        $total = $questions->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'missing' => max(0, $total - $completed),
            'percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
        ];
    }

    /**
     * @return Collection<int, AuditQuestion>
     */
    private function questionsFor(Audit $audit): Collection
    {
        return $audit->selectedModules
            ->sortBy('sort_order')
            ->flatMap(fn ($selectedModule) => $selectedModule->module?->questions
                ->where('active', true)
                ->sortBy('sort_order')
                ->values() ?? collect())
            ->values();
    }

    private function storedAnswerIsComplete(AuditQuestion $question, ?AuditAnswer $answer): bool
    {
        if (! $answer) {
            return false;
        }

        return $this->completionIssuesFor($question, $answer) === [];
    }

    /**
     * @return array<int, string>
     */
    private function completionIssuesFor(AuditQuestion $question, AuditAnswer $answer): array
    {
        $value = $answer->value_json['value'] ?? null;

        return $this->answerIssues(
            question: $question,
            value: $value,
            notApplicable: $answer->not_applicable,
            notApplicableReason: $answer->not_applicable_reason,
            hasAttachment: $answer->attachments->isNotEmpty(),
            riskLevel: $answer->risk_level,
            recommendationText: $answer->recommendation_text,
        );
    }

    /**
     * @return array<int, string>
     */
    private function answerIssues(
        AuditQuestion $question,
        mixed $value,
        bool $notApplicable,
        ?string $notApplicableReason,
        bool $hasAttachment = false,
        ?string $riskLevel = null,
        ?string $recommendationText = null,
    ): array {
        $issues = [];

        if ($notApplicable) {
            if ($question->require_comment_when_na && blank($notApplicableReason)) {
                $issues[] = 'Podaj powod oznaczenia pytania jako N/D.';
            }

            return $issues;
        }

        if ($question->require_photo || $question->require_screenshot || in_array($question->field_type, ['photo', 'screenshot', 'file'], true)) {
            if (! $hasAttachment) {
                $issues[] = 'Dodaj wymagany zalacznik.';
            }
        } elseif (blank($value)) {
            $issues[] = 'Uzupelnij odpowiedz.';
        }

        if (($question->risk_enabled || $question->field_type === 'risk_level') && blank($riskLevel)) {
            $issues[] = 'Wybierz poziom ryzyka.';
        }

        if (in_array($riskLevel, AuditAnswer::RISK_LEVELS_REQUIRING_RECOMMENDATION, true) && blank($recommendationText)) {
            $issues[] = 'Dla ryzyka wysokiego lub krytycznego wpisz rekomendacje.';
        }

        return $issues;
    }

    private function evidenceTypeFor(AuditQuestion $question): string
    {
        return match (true) {
            $question->field_type === 'photo' || $question->require_photo => 'photo',
            $question->field_type === 'screenshot' || $question->require_screenshot => 'screenshot',
            default => 'file',
        };
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function validationMessagesFor(Audit $audit): array
    {
        $answers = $audit->answers->keyBy('audit_question_id');

        return $this->questionsFor($audit)
            ->mapWithKeys(function (AuditQuestion $question) use ($answers): array {
                $answer = $answers->get($question->id);

                return [
                    $question->id => $answer ? $this->completionIssuesFor($question, $answer) : [],
                ];
            })
            ->filter()
            ->all();
    }

    /**
     * @return array<int, array{module:string, question:string, issues:array<int, string>}>
     */
    private function submitBlockersFor(Audit $audit): array
    {
        $answers = $audit->answers->keyBy('audit_question_id');

        return $this->questionsFor($audit)
            ->map(function (AuditQuestion $question) use ($answers): ?array {
                $answer = $answers->get($question->id);
                $issues = $answer
                    ? $this->completionIssuesFor($question, $answer)
                    : ['Brak odpowiedzi.'];

                if ($issues === []) {
                    return null;
                }

                return [
                    'module' => $question->module?->name ?? 'Modul audytu',
                    'question' => $question->question,
                    'issues' => $issues,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
