<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditNotification;
use App\Models\AuditQuestion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TechnicalReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_lead_can_open_review_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($lead)
            ->get(route('reviewer.index'))
            ->assertOk()
            ->assertSee('Weryfikacja techniczna')
            ->assertSee($audit->title);
    }

    public function test_technical_lead_can_open_review_detail(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($lead)
            ->get(route('reviewer.audits.show', $audit))
            ->assertOk()
            ->assertSee('Decyzja lidera')
            ->assertSee('Zatwierdz technicznie')
            ->assertSee('Zwroc do poprawek');
    }

    public function test_technical_lead_can_approve_submitted_audit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($lead)
            ->post(route('reviewer.audits.approve', $audit), [
                'notes' => 'Zakres audytu technicznie poprawny.',
            ])
            ->assertRedirect(route('reviewer.audits.show', $audit));

        $audit->refresh();

        $this->assertSame('technically_approved', $audit->status);
        $this->assertNotNull($audit->approved_at);
        $this->assertDatabaseHas('audit_reviews', [
            'audit_id' => $audit->id,
            'reviewer_id' => $lead->id,
            'decision' => 'approved',
            'notes' => 'Zakres audytu technicznie poprawny.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $lead->id,
            'event' => 'audit.technically_approved',
            'subject_type' => Audit::class,
            'subject_id' => $audit->id,
        ]);
    }

    public function test_technical_lead_can_request_changes_with_notes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($lead)
            ->post(route('reviewer.audits.request-changes', $audit), [
                'notes' => 'Uzupelnij opis rekomendacji dla firmware oraz zalacznik dashboardu.',
            ])
            ->assertRedirect(route('reviewer.audits.show', $audit));

        $audit->refresh();

        $this->assertSame('changes_requested', $audit->status);
        $this->assertDatabaseHas('audit_reviews', [
            'audit_id' => $audit->id,
            'reviewer_id' => $lead->id,
            'decision' => 'changes_requested',
        ]);
    }

    public function test_changes_request_requires_notes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($lead)
            ->post(route('reviewer.audits.request-changes', $audit), [
                'notes' => '',
            ])
            ->assertSessionHasErrors('notes');

        $this->assertSame('submitted_for_review', $audit->refresh()->status);
    }

    public function test_auditor_cannot_open_technical_review_area(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($auditor)
            ->get(route('reviewer.audits.show', $audit))
            ->assertForbidden();
    }

    public function test_approval_creates_notification_for_auditor(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($lead)
            ->post(route('reviewer.audits.approve', $audit), [
                'notes' => 'Gotowe do raportowania.',
            ])
            ->assertRedirect(route('reviewer.audits.show', $audit));

        $this->assertDatabaseHas('audit_notifications', [
            'user_id' => $auditor->id,
            'audit_id' => $audit->id,
            'type' => 'audit_approved',
            'title' => 'Audyt zatwierdzony technicznie',
        ]);

        $notification = AuditNotification::where('user_id', $auditor->id)->firstOrFail();

        $this
            ->actingAs($auditor)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Powiadomienia')
            ->assertSee('Audyt zatwierdzony technicznie');

        $this
            ->actingAs($auditor)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_lead_sees_reminder_for_submitted_audit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $this
            ->actingAs($lead)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Przypomnienia')
            ->assertSee('Audyt czeka na weryfikacje')
            ->assertSee($audit->title);
    }

    public function test_user_cannot_mark_other_user_notification_as_read(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = $this->submittedAudit();

        $notification = $lead->auditNotifications()->create([
            'audit_id' => $audit->id,
            'type' => 'manual',
            'title' => 'Tajne powiadomienie lidera',
            'body' => 'Tylko dla lidera.',
        ]);

        $this
            ->actingAs($auditor)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();
    }

    private function submittedAudit(): Audit
    {
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();

        $this->completeAuditAnswers($audit, $auditor);

        $audit->forceFill([
            'status' => 'submitted_for_review',
            'submitted_at' => now(),
        ])->save();

        return $audit->refresh();
    }

    private function completeAuditAnswers(Audit $audit, User $auditor): void
    {
        $audit->load('selectedModules.module.questions');

        foreach ($audit->selectedModules as $selectedModule) {
            foreach ($selectedModule->module->questions as $question) {
                $riskLevel = $question->risk_enabled || $question->field_type === 'risk_level' ? 'low' : null;
                $value = match ($question->field_type) {
                    'yes_no' => 'yes',
                    'risk_level' => $riskLevel,
                    'long_text' => 'Opis testowy.',
                    default => 'Odpowiedz testowa.',
                };

                $answer = AuditAnswer::updateOrCreate(
                    [
                        'audit_id' => $audit->id,
                        'audit_question_id' => $question->id,
                    ],
                    [
                        'audit_module_id' => $question->audit_module_id,
                        'answered_by' => $auditor->id,
                        'value_json' => ['value' => $value],
                        'risk_level' => $riskLevel,
                        'status' => 'completed',
                        'sync_status' => 'synced',
                        'local_uuid' => Str::uuid()->toString(),
                    ],
                );

                if ($this->needsEvidence($question)) {
                    $answer->attachments()->create([
                        'audit_id' => $audit->id,
                        'audit_question_id' => $question->id,
                        'audit_module_id' => $question->audit_module_id,
                        'uploaded_by' => $auditor->id,
                        'evidence_type' => $question->field_type === 'photo' || $question->require_photo ? 'photo' : 'screenshot',
                        'disk' => 'local',
                        'path' => "audit-evidence/tests/{$question->id}.png",
                        'original_name' => "evidence-{$question->id}.png",
                        'mime_type' => 'image/png',
                        'size_bytes' => 1024,
                        'local_uuid' => Str::uuid()->toString(),
                    ]);
                }
            }
        }
    }

    private function needsEvidence(AuditQuestion $question): bool
    {
        return $question->require_photo
            || $question->require_screenshot
            || in_array($question->field_type, ['photo', 'screenshot', 'file'], true);
    }
}
