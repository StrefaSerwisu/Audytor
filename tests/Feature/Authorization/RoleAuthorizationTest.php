<?php

namespace Tests\Feature\Authorization;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditAnswerAttachment;
use App\Models\AuditPublication;
use App\Models\AuditReportExport;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_global_admin_and_technical_lead_can_access_internal_management_sections(): void
    {
        $this->seed(DatabaseSeeder::class);
        $audit = $this->approvedAudit();
        $panel = Panel::make()->id('admin');

        foreach (['superadmin@globalit.test', 'admin@globalit.test', 'lider@globalit.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertTrue($user->canAccessPanel($panel));
            $this->actingAs($user)->get(route('auditor.index'))->assertOk();
            $this->actingAs($user)->get(route('reviewer.index'))->assertOk();
            $this->actingAs($user)->get(route('dashboard.index'))->assertOk();
            $this->actingAs($user)->get(route('reports.technical', $audit))->assertOk();
            $this->actingAs($user)->get(route('reports.business', $audit))->assertOk();
            $this->actingAs($user)->get(route('reports.sales', $audit))->assertOk();
            $this->actingAs($user)->get(route('archive.index'))->assertOk();
        }
    }

    public function test_management_roles_can_publish_and_close_reports(): void
    {
        Bus::fake();
        $this->seed(DatabaseSeeder::class);

        foreach (['superadmin@globalit.test', 'admin@globalit.test', 'lider@globalit.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $audit = $this->approvedAudit();

            $this
                ->actingAs($user)
                ->post(route('reports.publish', $audit), ['notes' => 'Publikacja testowa.'])
                ->assertRedirect();

            $audit->refresh();

            $this->assertSame('published_to_client', $audit->status);

            $this
                ->actingAs($user)
                ->post(route('reports.close', $audit), ['notes' => 'Zamkniecie testowe.'])
                ->assertRedirect();

            $this->assertSame('closed', $audit->refresh()->status);
        }
    }

    public function test_auditor_has_only_assigned_audit_access_and_cannot_access_management_sections(): void
    {
        $this->seed(DatabaseSeeder::class);
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $unassignedAudit = $this->unassignedAudit();
        $panel = Panel::make()->id('admin');

        $this->assertFalse($auditor->canAccessPanel($panel));
        $this->actingAs($auditor)->get(route('auditor.index'))->assertOk();
        $this->actingAs($auditor)->get(route('auditor.audits.show', $audit))->assertOk();
        $this->actingAs($auditor)->get(route('auditor.audits.show', $unassignedAudit))->assertForbidden();
        $this->actingAs($auditor)->get(route('reviewer.index'))->assertForbidden();
        $this->actingAs($auditor)->get(route('dashboard.index'))->assertForbidden();
        $this->actingAs($auditor)->get(route('archive.index'))->assertForbidden();
        $this->actingAs($auditor)->post(route('reports.publish', $this->approvedAudit()))->assertForbidden();
        $this->actingAs($auditor)->post(route('reports.close', $this->publishedAudit()))->assertForbidden();
    }

    public function test_sales_cannot_access_admin_or_technical_sections_but_can_access_sales_report_and_follow_ups(): void
    {
        $this->seed(DatabaseSeeder::class);
        $sales = User::where('email', 'sales@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();
        $panel = Panel::make()->id('admin');

        $this->assertFalse($sales->canAccessPanel($panel));
        $this->actingAs($sales)->get(route('reports.sales', $audit))->assertOk();
        $this->actingAs($sales)->get(route('reports.technical', $audit))->assertForbidden();
        $this->actingAs($sales)->get(route('reports.business', $audit))->assertForbidden();
        $this->actingAs($sales)->get(route('reviewer.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('follow-ups.index'))->assertOk();
        $this->actingAs($sales)->post(route('reports.publish', $audit))->assertForbidden();
    }

    public function test_client_can_use_own_portal_and_cannot_access_internal_sections_or_sales_report(): void
    {
        $this->seed(DatabaseSeeder::class);
        $client = User::where('email', 'klient@globalit.test')->firstOrFail();
        $ownPublication = $this->publishedAudit()->publications()->latest()->firstOrFail();
        $otherPublication = $this->otherClientPublication();

        $this->actingAs($client)->get(route('client.portal.index'))->assertOk();
        $this->actingAs($client)->get(route('client.portal.reports.show', $ownPublication))->assertOk();
        $this->actingAs($client)->get(route('client.portal.reports.show', $otherPublication))->assertForbidden();
        $this->actingAs($client)->get(route('auditor.index'))->assertForbidden();
        $this->actingAs($client)->get(route('reviewer.index'))->assertForbidden();
        $this->actingAs($client)->get(route('dashboard.index'))->assertForbidden();
        $this->actingAs($client)->get(route('notifications.index'))->assertForbidden();
        $this->actingAs($client)->get(route('reports.sales', $ownPublication->audit))->assertForbidden();
    }

    public function test_inactive_user_cannot_log_in_or_use_protected_sections(): void
    {
        $this->seed(DatabaseSeeder::class);
        $inactiveAuditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $inactiveAuditor->forceFill(['active' => false])->save();

        $this
            ->post(route('auditor.login.store'), [
                'email' => $inactiveAuditor->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($inactiveAuditor)->get(route('auditor.index'))->assertForbidden();
    }

    public function test_existing_session_loses_access_after_account_is_deactivated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();

        $this->post(route('auditor.login.store'), [
            'email' => $auditor->email,
            'password' => 'password',
        ])->assertRedirect(route('auditor.index'));

        $this->assertAuthenticatedAs($auditor);

        User::query()->whereKey($auditor->id)->update(['active' => false]);

        $this->get(route('auditor.index'))->assertForbidden();
    }

    public function test_client_login_rejects_inactive_client_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $client = User::where('email', 'klient@globalit.test')->firstOrFail();
        $client->forceFill(['active' => false])->save();

        $this
            ->post(route('client.login.store'), [
                'email' => $client->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_idor_attachment_download_is_blocked_for_unassigned_auditor(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $assignedAudit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $answer = AuditAnswer::create([
            'audit_id' => $assignedAudit->id,
            'audit_question_id' => $assignedAudit->selectedModules()->firstOrFail()->module->questions()->firstOrFail()->id,
            'audit_module_id' => $assignedAudit->selectedModules()->firstOrFail()->audit_module_id,
            'answered_by' => User::where('email', 'audytor@globalit.test')->value('id'),
            'value_json' => ['value' => 'test'],
            'status' => 'completed',
            'sync_status' => 'synced',
            'local_uuid' => Str::uuid()->toString(),
        ]);
        Storage::disk('local')->put('audit-evidence/test.txt', 'secret');
        $attachment = AuditAnswerAttachment::create([
            'audit_answer_id' => $answer->id,
            'audit_id' => $assignedAudit->id,
            'audit_question_id' => $answer->audit_question_id,
            'audit_module_id' => $answer->audit_module_id,
            'uploaded_by' => $answer->answered_by,
            'evidence_type' => 'file',
            'disk' => 'local',
            'path' => 'audit-evidence/test.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 6,
            'local_uuid' => Str::uuid()->toString(),
        ]);
        $otherAuditor = User::factory()->create([
            'role' => 'auditor',
            'active' => true,
            'password' => Hash::make('password'),
        ]);

        $this
            ->actingAs($otherAuditor)
            ->get(route('auditor.attachments.download', [$assignedAudit, $attachment]))
            ->assertForbidden();
    }

    public function test_sales_cannot_download_technical_report_export(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $sales = User::where('email', 'sales@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();
        Storage::disk('local')->put('report-exports/technical.pdf', '%PDF');
        $export = AuditReportExport::create([
            'audit_id' => $audit->id,
            'queued_by' => User::where('email', 'lider@globalit.test')->value('id'),
            'report_type' => 'technical',
            'format' => 'pdf',
            'status' => 'completed',
            'path' => 'report-exports/technical.pdf',
            'completed_at' => now(),
        ]);

        $this
            ->actingAs($sales)
            ->get(route('reports.exports.download', $export))
            ->assertForbidden();
    }

    private function approvedAudit(): Audit
    {
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();

        $audit->forceFill([
            'status' => 'technically_approved',
            'approved_at' => now(),
        ])->save();

        return $audit->refresh();
    }

    private function publishedAudit(): Audit
    {
        $audit = $this->approvedAudit();

        $audit->publications()->create([
            'published_by' => User::where('email', 'lider@globalit.test')->value('id'),
            'token' => Str::random(48),
            'published_at' => now(),
        ]);

        $audit->forceFill(['status' => 'published_to_client'])->save();

        return $audit->refresh();
    }

    private function unassignedAudit(): Audit
    {
        $baseAudit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();

        return Audit::create([
            'client_id' => $baseAudit->client_id,
            'client_location_id' => $baseAudit->client_location_id,
            'audit_template_id' => $baseAudit->audit_template_id,
            'title' => 'Audyt nieprzypisany',
            'status' => 'scheduled',
            'lead_reviewer_id' => $baseAudit->lead_reviewer_id,
        ]);
    }

    private function otherClientPublication(): AuditPublication
    {
        $otherClient = Client::create(['name' => 'Inny klient', 'status' => 'active']);
        $audit = Audit::create([
            'client_id' => $otherClient->id,
            'title' => 'Audyt innego klienta',
            'status' => 'published_to_client',
            'lead_reviewer_id' => User::where('email', 'lider@globalit.test')->value('id'),
        ]);

        return $audit->publications()->create([
            'published_by' => User::where('email', 'lider@globalit.test')->value('id'),
            'token' => Str::random(48),
            'published_at' => now(),
        ]);
    }
}
