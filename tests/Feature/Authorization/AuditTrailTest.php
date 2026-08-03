<?php

namespace Tests\Feature\Authorization;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditAnswerAttachment;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_download_and_deletion_are_recorded(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $selectedModule = $audit->selectedModules()->with('module.questions')->firstOrFail();
        $question = $selectedModule->module->questions->first();
        $answer = AuditAnswer::create([
            'audit_id' => $audit->id,
            'audit_question_id' => $question->id,
            'audit_module_id' => $question->audit_module_id,
            'answered_by' => $auditor->id,
            'value_json' => ['value' => 'test'],
            'status' => 'completed',
            'sync_status' => 'synced',
            'local_uuid' => Str::uuid()->toString(),
        ]);

        Storage::disk('local')->put('audit-evidence/test.txt', 'evidence');
        $attachment = AuditAnswerAttachment::create([
            'audit_answer_id' => $answer->id,
            'audit_id' => $audit->id,
            'audit_question_id' => $question->id,
            'audit_module_id' => $question->audit_module_id,
            'uploaded_by' => $auditor->id,
            'evidence_type' => 'file',
            'disk' => 'local',
            'path' => 'audit-evidence/test.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 8,
            'local_uuid' => Str::uuid()->toString(),
        ]);

        $this
            ->actingAs($auditor)
            ->get(route('auditor.attachments.download', [$audit, $attachment]))
            ->assertOk()
            ->assertDownload('test.txt');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $auditor->id,
            'event' => 'evidence.downloaded',
            'subject_type' => AuditAnswerAttachment::class,
            'subject_id' => $attachment->id,
        ]);

        $this
            ->actingAs($auditor)
            ->delete(route('auditor.attachments.destroy', [$audit, $attachment]))
            ->assertRedirect();

        $deletedLog = AuditLog::where('event', 'evidence.deleted')->where('subject_id', $attachment->id)->firstOrFail();

        $this->assertSame($auditor->id, $deletedLog->actor_id);
        $this->assertSame('test.txt', $deletedLog->old_values['original_name']);
        $this->assertDatabaseMissing('audit_answer_attachments', ['id' => $attachment->id]);
    }

    public function test_passwords_tokens_and_nested_secrets_are_redacted(): void
    {
        $log = AuditLogService::record('security.redaction_test', metadata: [
            'password' => 'plain-password',
            'api_token' => 'plain-token',
            'nested' => [
                'client_secret' => 'plain-secret',
                'safe_value' => 'visible',
            ],
        ]);

        $this->assertSame('[REDACTED]', $log->metadata['password']);
        $this->assertSame('[REDACTED]', $log->metadata['api_token']);
        $this->assertSame('[REDACTED]', $log->metadata['nested']['client_secret']);
        $this->assertSame('visible', $log->metadata['nested']['safe_value']);
        $this->assertStringNotContainsString('plain-', json_encode($log->metadata));
    }
}
