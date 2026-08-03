<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditAnswerAttachment;
use App\Models\AuditLog;
use App\Models\AuditQuestion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditorViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_can_log_in_to_auditor_area(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this
            ->post('/auditor/login', [
                'email' => 'audytor@globalit.test',
                'password' => 'password',
            ])
            ->assertRedirect('/auditor');

        $this->assertAuthenticatedAs(User::where('email', 'audytor@globalit.test')->firstOrFail());
    }

    public function test_assigned_auditor_can_open_my_audits(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->get('/auditor')
            ->assertOk()
            ->assertSee('Moje audyty')
            ->assertSee('Audyt podstawowy IT - Klient Testowy')
            ->assertSee('Klient Testowy Sp. z o.o.');
    }

    public function test_assigned_auditor_can_open_audit_detail_and_questions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->get(route('auditor.audits.show', $audit))
            ->assertOk()
            ->assertSee('UTM/firewall')
            ->assertSee('Podaj producenta i model urzadzenia.')
            ->assertSee('Zapisz odpowiedz');
    }

    public function test_auditor_can_save_answer(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $question = AuditQuestion::where('question', 'Podaj producenta i model urzadzenia.')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->post(route('auditor.answers.update', [$audit, $question]), [
                'value' => 'Fortinet FortiGate 80F',
                'comment' => 'Model potwierdzony w dashboardzie.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_answers', [
            'audit_id' => $audit->id,
            'audit_question_id' => $question->id,
            'audit_module_id' => $question->audit_module_id,
            'answered_by' => $auditor->id,
            'comment' => 'Model potwierdzony w dashboardzie.',
            'status' => 'completed',
            'sync_status' => 'synced',
        ]);

        $this->assertSame(
            'Fortinet FortiGate 80F',
            AuditAnswer::firstWhere('audit_question_id', $question->id)->value_json['value'],
        );
    }

    public function test_auditor_can_upload_attachment_to_answer(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $question = AuditQuestion::where('question', 'Dodaj screenshot dashboardu.')->firstOrFail();
        $file = UploadedFile::fake()->image('dashboard.png', 1200, 800);

        $this
            ->actingAs($auditor)
            ->post(route('auditor.answers.update', [$audit, $question]), [
                'value' => 'Dashboard UTM bez danych wrazliwych.',
                'attachment_caption' => 'Widok statusu urzadzenia',
                'attachments' => [$file],
            ])
            ->assertRedirect();

        $answer = AuditAnswer::where('audit_id', $audit->id)
            ->where('audit_question_id', $question->id)
            ->firstOrFail();
        $attachment = AuditAnswerAttachment::where('audit_answer_id', $answer->id)->firstOrFail();

        $this->assertSame('completed', $answer->status);
        $this->assertSame('screenshot', $attachment->evidence_type);
        $this->assertSame('dashboard.png', $attachment->original_name);
        $this->assertSame('Widok statusu urzadzenia', $attachment->caption);
        Storage::disk('local')->assertExists($attachment->path);

        $uploadLog = AuditLog::where('event', 'evidence.uploaded')
            ->where('subject_id', $attachment->id)
            ->firstOrFail();

        $this->assertSame($auditor->id, $uploadLog->actor_id);
        $this->assertSame($audit->id, $uploadLog->metadata['audit_id']);
        $this->assertSame($attachment->id, $uploadLog->metadata['attachment_id']);
        $this->assertSame('dashboard.png', $uploadLog->metadata['original_name']);
        $this->assertSame($attachment->mime_type, $uploadLog->metadata['mime_type']);
        $this->assertSame($attachment->size_bytes, $uploadLog->metadata['size_bytes']);
        $this->assertArrayNotHasKey('contents', $uploadLog->metadata);
    }

    public function test_risk_enabled_answer_requires_risk_level(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $question = AuditQuestion::where('question', 'Czy firmware jest aktualny?')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->post(route('auditor.answers.update', [$audit, $question]), [
                'value' => 'no',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('answer');

        $this->assertDatabaseHas('audit_answers', [
            'audit_id' => $audit->id,
            'audit_question_id' => $question->id,
            'status' => 'draft',
        ]);
    }

    public function test_high_or_critical_risk_requires_recommendation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $question = AuditQuestion::where('question', 'Czy firmware jest aktualny?')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->post(route('auditor.answers.update', [$audit, $question]), [
                'value' => 'no',
                'risk_level' => 'high',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('answer');

        $this->assertDatabaseHas('audit_answers', [
            'audit_id' => $audit->id,
            'audit_question_id' => $question->id,
            'risk_level' => 'high',
            'status' => 'draft',
        ]);
    }

    public function test_high_risk_answer_is_completed_with_recommendation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $question = AuditQuestion::where('question', 'Czy firmware jest aktualny?')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->post(route('auditor.answers.update', [$audit, $question]), [
                'value' => 'no',
                'risk_level' => 'high',
                'recommendation_text' => 'Zaplanowac aktualizacje firmware i weryfikacje po zmianie.',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('audit_answers', [
            'audit_id' => $audit->id,
            'audit_question_id' => $question->id,
            'risk_level' => 'high',
            'recommendation_text' => 'Zaplanowac aktualizacje firmware i weryfikacje po zmianie.',
            'status' => 'completed',
        ]);
    }

    public function test_incomplete_audit_cannot_be_submitted_for_review(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->post(route('auditor.audits.submit', $audit))
            ->assertRedirect()
            ->assertSessionHasErrors('submit')
            ->assertSessionHas('submitBlockers');

        $audit->refresh();

        $this->assertSame('scheduled', $audit->status);
        $this->assertNull($audit->submitted_at);
    }

    public function test_complete_audit_can_be_submitted_for_review(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $this->completeAuditAnswers($audit, $auditor);

        $this
            ->actingAs($auditor)
            ->post(route('auditor.audits.submit', $audit))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $audit->refresh();

        $this->assertSame('submitted_for_review', $audit->status);
        $this->assertNotNull($audit->submitted_at);
    }

    public function test_auditor_can_delete_own_audit_attachment(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $question = AuditQuestion::where('question', 'Dodaj zdjecie urzadzenia w szafie rack.')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->post(route('auditor.answers.update', [$audit, $question]), [
                'attachments' => [UploadedFile::fake()->image('rack.jpg')],
            ])
            ->assertRedirect();

        $attachment = AuditAnswerAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this
            ->actingAs($auditor)
            ->delete(route('auditor.attachments.destroy', [$audit, $attachment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_answer_attachments', [
            'id' => $attachment->id,
        ]);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_unassigned_auditor_cannot_open_audit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $unassignedAuditor = User::create([
            'name' => 'Nieprzypisany Audytor',
            'email' => 'nieprzypisany@globalit.test',
            'password' => Hash::make('password'),
            'role' => 'auditor',
            'active' => true,
        ]);
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();

        $this
            ->actingAs($unassignedAuditor)
            ->get(route('auditor.audits.show', $audit))
            ->assertForbidden();
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

                $answer = AuditAnswer::create([
                    'audit_id' => $audit->id,
                    'audit_question_id' => $question->id,
                    'audit_module_id' => $question->audit_module_id,
                    'answered_by' => $auditor->id,
                    'value_json' => ['value' => $value],
                    'risk_level' => $riskLevel,
                    'status' => 'completed',
                    'sync_status' => 'synced',
                    'local_uuid' => Str::uuid()->toString(),
                ]);

                if ($question->require_photo || $question->require_screenshot || in_array($question->field_type, ['photo', 'screenshot', 'file'], true)) {
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
}
