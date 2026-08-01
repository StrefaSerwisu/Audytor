<?php

namespace Tests\Feature;

use App\Jobs\GenerateAuditReportExport;
use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditFollowUpTask;
use App\Models\AuditQuestion;
use App\Models\AuditReportExport;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_report_is_available_for_approved_audit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($lead)
            ->get(route('reports.technical', $audit))
            ->assertOk()
            ->assertSee('Raport techniczny')
            ->assertSee('Podsumowanie ryzyka')
            ->assertSee('UTM/firewall');
    }

    public function test_business_report_is_available_for_approved_audit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($lead)
            ->get(route('reports.business', $audit))
            ->assertOk()
            ->assertSee('Podsumowanie biznesowe')
            ->assertSee('Mapa ryzyka')
            ->assertSee('Rekomendowane dzialania');
    }

    public function test_sales_report_is_available_for_sales_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $sales = User::where('email', 'sales@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($sales)
            ->get(route('reports.sales', $audit))
            ->assertOk()
            ->assertSee('Raport sprzedazowy Global IT')
            ->assertSee('TOP rekomendacje sprzedazowe')
            ->assertSee('Szacowana pracochlonnosc');
    }

    public function test_approved_report_can_be_downloaded_as_pdf_and_docx(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $pdf = $this
            ->actingAs($lead)
            ->get(route('reports.download.pdf', [$audit, 'technical']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('.pdf', $pdf->headers->get('content-disposition'));

        $docx = $this
            ->actingAs($lead)
            ->get(route('reports.download.docx', [$audit, 'business']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertStringStartsWith('PK', $docx->getContent());
        $this->assertStringContainsString('.docx', $docx->headers->get('content-disposition'));
    }

    public function test_report_is_blocked_before_technical_approval(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $audit->forceFill([
            'status' => 'submitted_for_review',
            'submitted_at' => now(),
        ])->save();

        $this
            ->actingAs($lead)
            ->get(route('reports.technical', $audit))
            ->assertStatus(409);
    }

    public function test_auditor_cannot_open_reports(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($auditor)
            ->get(route('reports.business', $audit))
            ->assertForbidden();

        $this
            ->actingAs($auditor)
            ->get(route('reports.sales', $audit))
            ->assertForbidden();
    }

    public function test_client_cannot_open_sales_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        $clientUser = User::where('email', 'klient@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($clientUser)
            ->get(route('reports.sales', $audit))
            ->assertForbidden();

        $this
            ->actingAs($clientUser)
            ->get(route('reports.download.pdf', [$audit, 'sales']))
            ->assertForbidden();
    }

    public function test_report_export_can_be_queued(): void
    {
        Bus::fake();
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($lead)
            ->post(route('reports.queue-export', [$audit, 'technical']), [
                'format' => 'pdf',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_report_exports', [
            'audit_id' => $audit->id,
            'queued_by' => $lead->id,
            'report_type' => 'technical',
            'format' => 'pdf',
            'status' => 'queued',
        ]);

        Bus::assertDispatched(GenerateAuditReportExport::class);
    }

    public function test_report_exports_index_shows_statuses_and_completed_file_can_be_downloaded(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();
        $path = "report-exports/audit-{$audit->id}/technical-test.pdf";

        Storage::disk('local')->put($path, '%PDF test export');

        $export = AuditReportExport::create([
            'audit_id' => $audit->id,
            'queued_by' => $lead->id,
            'report_type' => 'technical',
            'format' => 'pdf',
            'status' => 'completed',
            'path' => $path,
            'completed_at' => now(),
        ]);

        $this
            ->actingAs($lead)
            ->get(route('reports.exports.index'))
            ->assertOk()
            ->assertSee('Eksporty raportow')
            ->assertSee('Gotowy')
            ->assertSee($audit->title);

        $download = $this
            ->actingAs($lead)
            ->get(route('reports.exports.download', $export))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame('%PDF test export', $download->streamedContent());
    }

    public function test_failed_report_export_can_be_retried(): void
    {
        Bus::fake();
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();
        $export = AuditReportExport::create([
            'audit_id' => $audit->id,
            'queued_by' => $lead->id,
            'report_type' => 'business',
            'format' => 'docx',
            'status' => 'failed',
            'path' => 'report-exports/failed.docx',
            'error' => 'Blad testowy',
            'completed_at' => now(),
        ]);

        $this
            ->actingAs($lead)
            ->post(route('reports.exports.retry', $export))
            ->assertRedirect(route('reports.exports.index'));

        $export->refresh();

        $this->assertSame('queued', $export->status);
        $this->assertNull($export->error);
        $this->assertNull($export->path);
        $this->assertNull($export->completed_at);

        Bus::assertDispatched(GenerateAuditReportExport::class);
    }

    public function test_auditor_cannot_open_report_exports_index(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->get(route('reports.exports.index'))
            ->assertForbidden();
    }

    public function test_approved_audit_can_be_published_to_client(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($lead)
            ->post(route('reports.publish', $audit), [
                'notes' => 'Raport gotowy do omowienia z klientem.',
            ])
            ->assertRedirect();

        $audit->refresh();

        $this->assertSame('published_to_client', $audit->status);
        $this->assertDatabaseHas('audit_publications', [
            'audit_id' => $audit->id,
            'published_by' => $lead->id,
            'notes' => 'Raport gotowy do omowienia z klientem.',
        ]);
        $this->assertNotNull($audit->publications()->firstOrFail()->published_at);
    }

    public function test_client_can_open_published_report_by_token(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));

        $publication = $audit->publications()->firstOrFail();

        $this
            ->get(route('client.reports.show', $publication->token))
            ->assertOk()
            ->assertSee('Raport audytu IT')
            ->assertSee('Mapa ryzyka')
            ->assertSee($audit->client->name);
    }

    public function test_auditor_cannot_publish_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($auditor)
            ->post(route('reports.publish', $audit))
            ->assertForbidden();
    }

    public function test_expired_client_report_token_is_not_available(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();
        $publication = $audit->publications()->create([
            'published_by' => $lead->id,
            'token' => Str::random(48),
            'published_at' => now()->subDays(3),
            'expires_at' => now()->subDay(),
        ]);

        $this
            ->get(route('client.reports.show', $publication->token))
            ->assertNotFound();
    }

    public function test_published_audit_can_be_closed_and_archived(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $audit->refresh();

        $this
            ->actingAs($lead)
            ->post(route('reports.close', $audit), [
                'notes' => 'Audyt omowiony z klientem i zamkniety.',
            ])
            ->assertRedirect(route('archive.show', $audit));

        $audit->refresh();

        $this->assertSame('closed', $audit->status);
        $this->assertNotNull($audit->completed_at);
        $this->assertDatabaseHas('audit_closures', [
            'audit_id' => $audit->id,
            'closed_by' => $lead->id,
            'notes' => 'Audyt omowiony z klientem i zamkniety.',
        ]);
    }

    public function test_unpublished_audit_cannot_be_closed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this
            ->actingAs($lead)
            ->post(route('reports.close', $audit))
            ->assertStatus(409);

        $this->assertSame('technically_approved', $audit->refresh()->status);
    }

    public function test_closed_audit_is_visible_in_archive_and_reports_remain_available(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $this->actingAs($lead)->post(route('reports.close', $audit), [
            'notes' => 'Archiwum po publikacji dla klienta.',
        ]);

        $audit->refresh();

        $this
            ->actingAs($lead)
            ->get(route('archive.index'))
            ->assertOk()
            ->assertSee('Archiwum audytow')
            ->assertSee($audit->title);

        $this
            ->actingAs($lead)
            ->get(route('archive.show', $audit))
            ->assertOk()
            ->assertSee('Archiwum audytu')
            ->assertSee('Archiwum po publikacji dla klienta.');

        $this
            ->actingAs($lead)
            ->get(route('reports.business', $audit))
            ->assertOk()
            ->assertSee('Audyt zamkniety');
    }

    public function test_auditor_cannot_open_archive_or_close_audit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));

        $this
            ->actingAs($auditor)
            ->post(route('reports.close', $audit))
            ->assertForbidden();

        $this
            ->actingAs($auditor)
            ->get(route('archive.index'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_kpis_for_technical_lead(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));

        $this
            ->actingAs($lead)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Dashboard KPI')
            ->assertSee('Opublikowane')
            ->assertSee($audit->title);
    }

    public function test_auditor_cannot_open_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->get(route('dashboard.index'))
            ->assertForbidden();
    }

    public function test_archive_can_be_filtered_by_status_client_and_search(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $this->actingAs($lead)->post(route('reports.close', $audit), [
            'notes' => 'Zamkniecie do testu filtrow.',
        ]);

        $audit->refresh();

        $this
            ->actingAs($lead)
            ->get(route('archive.index', [
                'status' => 'closed',
                'client' => 'Klient Testowy',
                'q' => 'podstawowy',
                'closed_from' => now()->subDay()->toDateString(),
                'closed_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($audit->title)
            ->assertSee('Zamkniety');

        $this
            ->actingAs($lead)
            ->get(route('archive.index', [
                'client' => 'Nieistniejacy klient',
            ]))
            ->assertOk()
            ->assertSee('Brak audytow historycznych')
            ->assertDontSee($audit->title);
    }

    public function test_dashboard_kpi_can_be_exported_to_csv(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));

        $response = $this
            ->actingAs($lead)
            ->get(route('dashboard.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Wszystkie audyty', $csv);
        $this->assertStringContainsString('Opublikowane', $csv);
    }

    public function test_filtered_archive_can_be_exported_to_csv(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $this->actingAs($lead)->post(route('reports.close', $audit), [
            'notes' => 'Eksport archiwum po filtrach.',
        ]);

        $response = $this
            ->actingAs($lead)
            ->get(route('archive.export', [
                'status' => 'closed',
                'client' => 'Klient Testowy',
                'q' => 'podstawowy',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString($audit->title, $csv);
        $this->assertStringContainsString('Eksport archiwum po filtrach.', $csv);
    }

    public function test_client_can_log_in_and_see_own_published_reports(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $clientUser = User::where('email', 'klient@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit), [
            'notes' => 'Raport dostepny w portalu klienta.',
        ]);
        Auth::logout();

        $this
            ->post(route('client.login.store'), [
                'email' => 'klient@globalit.test',
                'password' => 'password',
            ])
            ->assertRedirect(route('client.portal.index'));

        $this
            ->actingAs($clientUser)
            ->get(route('client.portal.index'))
            ->assertOk()
            ->assertSee('Raporty audytowe')
            ->assertSee($audit->title);
    }

    public function test_client_can_open_report_without_token_and_update_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $clientUser = User::where('email', 'klient@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $publication = $audit->publications()->firstOrFail();
        $this
            ->actingAs($clientUser)
            ->get(route('client.portal.reports.show', $publication))
            ->assertOk()
            ->assertSee('Status po stronie klienta')
            ->assertSee($audit->title);

        $this
            ->actingAs($clientUser)
            ->post(route('client.portal.reports.status', $publication), [
                'client_status' => 'accepted',
            ])
            ->assertRedirect();

        $publication->refresh();

        $this->assertSame('accepted', $publication->client_status);
        $this->assertNotNull($publication->client_status_updated_at);
    }

    public function test_client_can_add_report_comment_and_select_recommendations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $clientUser = User::where('email', 'klient@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $publication = $audit->publications()->firstOrFail();
        $answerKey = 'answer:'.AuditAnswer::where('audit_id', $audit->id)
            ->whereNotNull('recommendation_text')
            ->firstOrFail()
            ->id;
        $recommendationKey = 'recommendation:'.$audit
            ->selectedModules()
            ->with('module.questions.recommendations')
            ->get()
            ->flatMap(fn ($selectedModule) => $selectedModule->module->questions)
            ->flatMap(fn ($question) => $question->recommendations)
            ->firstOrFail()
            ->id;

        $this
            ->actingAs($clientUser)
            ->get(route('client.portal.reports.show', $publication))
            ->assertOk()
            ->assertSee('Komentarz i rekomendacje do wdrozenia');

        $this
            ->actingAs($clientUser)
            ->post(route('client.portal.reports.feedback', $publication), [
                'client_comment' => 'Chcemy wdrozyc rekomendacje dotyczace firmware w pierwszej kolejnosci.',
                'accepted_recommendations' => [$answerKey, $recommendationKey],
            ])
            ->assertRedirect();

        $publication->refresh();

        $this->assertSame(
            'Chcemy wdrozyc rekomendacje dotyczace firmware w pierwszej kolejnosci.',
            $publication->client_comment,
        );
        $this->assertSame([$answerKey, $recommendationKey], $publication->accepted_recommendations_json);
        $this->assertNotNull($publication->client_feedback_at);
        $this->assertDatabaseCount(AuditFollowUpTask::class, 2);

        $this
            ->actingAs($lead)
            ->get(route('reviewer.audits.show', $audit))
            ->assertOk()
            ->assertSee('Reakcja klienta')
            ->assertSee('2 rekomendacji do wdrozenia')
            ->assertSee('Chcemy wdrozyc rekomendacje dotyczace firmware');
    }

    public function test_follow_up_tasks_can_be_managed_by_global_it_and_seen_by_client(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $clientUser = User::where('email', 'klient@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $publication = $audit->publications()->firstOrFail();
        $answerKey = 'answer:'.AuditAnswer::where('audit_id', $audit->id)
            ->whereNotNull('recommendation_text')
            ->firstOrFail()
            ->id;

        $this
            ->actingAs($clientUser)
            ->post(route('client.portal.reports.feedback', $publication), [
                'client_comment' => 'Prosimy o przygotowanie planu wdrozenia.',
                'accepted_recommendations' => [$answerKey],
            ])
            ->assertRedirect();

        $task = AuditFollowUpTask::firstOrFail();

        $this
            ->actingAs($lead)
            ->get(route('follow-ups.index'))
            ->assertOk()
            ->assertSee('Plan wdrozen')
            ->assertSee($task->title);

        $this
            ->actingAs($lead)
            ->post(route('follow-ups.update', $task), [
                'status' => 'planned',
                'priority' => 'high',
                'owner_id' => $lead->id,
                'due_date' => now()->addDays(14)->toDateString(),
                'notes' => 'Omowic zakres z klientem.',
                'client_visible' => '1',
            ])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame('planned', $task->status);
        $this->assertSame('high', $task->priority);
        $this->assertSame($lead->id, $task->owner_id);

        $this
            ->actingAs($clientUser)
            ->get(route('client.portal.reports.show', $publication))
            ->assertOk()
            ->assertSee('Plan dzialan poaudytowych')
            ->assertSee('Zaplanowane')
            ->assertSee('Lider Techniczny');
    }

    public function test_auditor_cannot_manage_follow_up_tasks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();

        $this
            ->actingAs($auditor)
            ->get(route('follow-ups.index'))
            ->assertForbidden();

        $this
            ->actingAs($auditor)
            ->get(route('follow-ups.export'))
            ->assertForbidden();
    }

    public function test_follow_up_tasks_can_be_exported_to_csv_with_filters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $clientUser = User::where('email', 'klient@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $publication = $audit->publications()->firstOrFail();
        $answerKey = 'answer:'.AuditAnswer::where('audit_id', $audit->id)
            ->whereNotNull('recommendation_text')
            ->firstOrFail()
            ->id;

        $this
            ->actingAs($clientUser)
            ->post(route('client.portal.reports.feedback', $publication), [
                'client_comment' => 'Eksport planu dzialan.',
                'accepted_recommendations' => [$answerKey],
            ]);

        $task = AuditFollowUpTask::firstOrFail();
        $task->update([
            'status' => 'planned',
            'priority' => 'high',
            'owner_id' => $lead->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'notes' => 'Eksportowalna notatka.',
        ]);

        $response = $this
            ->actingAs($lead)
            ->get(route('follow-ups.export', [
                'status' => 'planned',
                'priority' => 'high',
                'q' => 'wdrozenie',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Klient Testowy', $csv);
        $this->assertStringContainsString('Zaplanowane', $csv);
        $this->assertStringContainsString('Eksportowalna notatka.', $csv);
    }

    public function test_client_cannot_open_other_client_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();
        $otherClient = Client::create([
            'name' => 'Inny Klient Sp. z o.o.',
            'status' => 'active',
        ]);
        $otherClientUser = User::create([
            'name' => 'Inny Klient',
            'email' => 'inny-klient@globalit.test',
            'password' => bcrypt('password'),
            'role' => 'client',
            'client_id' => $otherClient->id,
            'active' => true,
        ]);

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $publication = $audit->publications()->firstOrFail();

        $this
            ->actingAs($otherClientUser)
            ->get(route('client.portal.reports.show', $publication))
            ->assertForbidden();
    }

    public function test_expired_publication_is_hidden_from_client_portal(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();
        $clientUser = User::where('email', 'klient@globalit.test')->firstOrFail();
        $audit = $this->approvedAudit();

        $this->actingAs($lead)->post(route('reports.publish', $audit));
        $publication = $audit->publications()->firstOrFail();
        $publication->forceFill([
            'expires_at' => now()->subDay(),
        ])->save();

        $this
            ->actingAs($clientUser)
            ->get(route('client.portal.index'))
            ->assertOk()
            ->assertSee('Brak aktywnych raportow')
            ->assertDontSee($audit->title);

        $this
            ->actingAs($clientUser)
            ->get(route('client.portal.reports.show', $publication))
            ->assertNotFound();
    }

    private function approvedAudit(): Audit
    {
        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();

        $this->completeAuditAnswers($audit, $auditor);

        $audit->reviews()->create([
            'reviewer_id' => $lead->id,
            'decision' => 'approved',
            'notes' => 'Zatwierdzone do raportu.',
        ]);

        $audit->forceFill([
            'status' => 'technically_approved',
            'submitted_at' => now()->subHour(),
            'approved_at' => now(),
        ])->save();

        return $audit->refresh();
    }

    private function completeAuditAnswers(Audit $audit, User $auditor): void
    {
        $audit->load('selectedModules.module.questions');

        foreach ($audit->selectedModules as $selectedModule) {
            foreach ($selectedModule->module->questions as $question) {
                $riskLevel = $question->risk_enabled || $question->field_type === 'risk_level' ? 'medium' : null;
                $value = match ($question->field_type) {
                    'yes_no' => 'no',
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
                        'comment' => 'Komentarz testowy.',
                        'risk_level' => $riskLevel,
                        'recommendation_text' => $riskLevel ? 'Rekomendacja testowa dla ryzyka.' : null,
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
                        'path' => "audit-evidence/tests/report-{$question->id}.png",
                        'original_name' => "report-evidence-{$question->id}.png",
                        'mime_type' => 'image/png',
                        'size_bytes' => 2048,
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
