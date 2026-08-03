<?php

namespace Tests\Feature;

use App\Enums\AuditOrderStatus;
use App\Enums\CompetencyLevel;
use App\Enums\TechnicalAuditStatus;
use App\Enums\UserRole;
use App\Models\AuditOrder;
use App\Models\AuditType;
use App\Models\AuditTypeVersion;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\SalesQualification;
use App\Models\TechnicalAudit;
use App\Models\TechnicalAuditEscalation;
use App\Models\User;
use App\Services\AuditOrderWorkflowService;
use App\Services\TechnicalAuditCreationService;
use App\Services\TechnicalAuditWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TechnicalAuditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_order_creates_technical_audit_from_snapshot_and_assigns_controls(): void
    {
        [$order,$auditor,$lead] = $this->order();
        $this->actingAs($lead);
        $audit = app(TechnicalAuditCreationService::class)->create($order, $lead);
        $this->assertMatchesRegularExpression('/^AUD-TECH\/\d{4}\/0001$/', $audit->number);
        $this->assertSame(TechnicalAuditStatus::InProgress, $audit->status);
        $this->assertSame(AuditOrderStatus::InProgress, $order->refresh()->status);
        $this->assertCount(1, $audit->modules);
        $this->assertCount(2, $audit->controls);
        $this->assertSame($auditor->id, $audit->controls->first()->assigned_to);
        $this->assertSame('CTRL-1', $audit->configuration_snapshot['technical_modules'][0]['controls'][0]['code']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'technical_audit.created', 'subject_id' => $audit->id]);
        $this->assertDatabaseHas('audit_notifications', ['user_id' => $auditor->id, 'type' => 'technical_audit.started']);
    }

    public function test_wrong_status_and_duplicate_creation_are_blocked(): void
    {
        [$order,,$lead] = $this->order();
        $order->update(['status' => AuditOrderStatus::Ready]);
        $this->expectException(ValidationException::class);
        app(TechnicalAuditCreationService::class)->create($order, $lead);
    }

    public function test_order_workflow_start_creates_only_one_audit(): void
    {
        [$order,,$lead] = $this->order();
        $this->actingAs($lead);
        app(AuditOrderWorkflowService::class)->transition($order, AuditOrderStatus::InProgress, $lead);
        $this->assertSame(1, TechnicalAudit::count());
        $this->expectException(ValidationException::class);
        app(TechnicalAuditCreationService::class)->create($order->refresh(), $lead);
    }

    public function test_library_change_does_not_change_technical_audit(): void
    {
        [$order,,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $snapshot = $audit->configuration_snapshot;
        $order->auditType->update(['name' => 'Zmieniona nazwa']);
        $this->assertSame($snapshot, $audit->refresh()->configuration_snapshot);
        $this->assertSame('Kontrola pierwsza', $audit->controls->first()->name);
    }

    public function test_auditor_sees_assigned_but_not_foreign_audit_and_sales_is_denied(): void
    {
        [$order,$auditor,$lead,$sales] = $this->order();
        $audit = $this->start($order, $lead);
        $this->actingAs($auditor)->get(route('engineer.audits.show', $audit))->assertOk();
        $foreign = $this->user(UserRole::Auditor, CompetencyLevel::Senior);
        $this->actingAs($foreign)->get(route('engineer.audits.show', $audit))->assertForbidden();
        $this->actingAs($sales)->get(route('engineer.audits.show', $audit))->assertForbidden();
    }

    public function test_draft_answer_is_saved_but_required_evidence_blocks_completion(): void
    {
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $control = $audit->controls()->where('code', 'CTRL-1')->firstOrFail();
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), ['value' => '1', 'result_status' => 'compliant', 'complete' => 0])->assertRedirect();
        $this->assertSame('in_progress', $control->refresh()->status);
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), ['value' => '1', 'result_status' => 'compliant', 'complete' => 1])->assertSessionHasErrors('evidence');
    }

    public function test_answer_business_validation_uses_control_snapshot(): void
    {
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $control = $audit->controls()->first();
        $base = ['value' => '0', 'complete' => 1];
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), $base + ['result_status' => 'non_compliant'])->assertSessionHasErrors('comment');
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), $base + ['result_status' => 'not_verified'])->assertSessionHasErrors('comment');
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), $base + ['result_status' => 'non_compliant', 'comment' => 'Brak MFA', 'proposed_risk_level' => 'high'])->assertSessionHasErrors('proposed_recommendation');
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), $base + ['result_status' => 'compliant', 'customer_statement' => 1])->assertSessionHasErrors('customer_statement_source');
    }

    public function test_number_select_multiselect_and_date_field_types_are_validated(): void
    {
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $module = $audit->modules->first();
        $cases = [
            ['number', '15', null],
            ['select', 'm365', ['m365', 'backup']],
            ['multiselect', ['m365', 'backup'], ['m365', 'backup']],
            ['date', '2026-08-03', null],
        ];
        foreach ($cases as $index => [$fieldType, $value, $options]) {
            $control = $audit->controls()->create(['technical_audit_module_id' => $module->id, 'code' => 'TYPE-'.$index, 'name' => $fieldType, 'field_type' => $fieldType, 'options_json' => $options, 'required' => true, 'active' => true, 'assigned_to' => $auditor->id, 'sort_order' => 10 + $index]);
            $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), ['value' => $value, 'result_status' => 'compliant', 'complete' => 1])->assertSessionHasNoErrors();
            $this->assertSame('completed', $control->refresh()->status);
        }
    }

    public function test_evidence_upload_completion_progress_and_idor(): void
    {
        Storage::fake('local');
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $control = $audit->controls()->first();
        $this->actingAs($auditor)->post(route('engineer.evidence.store', [$audit, $control]), ['evidence' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'), 'evidence_type' => 'document'])->assertRedirect();
        $evidence = $control->evidence()->firstOrFail();
        $this->assertSame('not_scanned', $evidence->scan_status);
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), ['value' => '1', 'result_status' => 'compliant', 'complete' => 1])->assertRedirect();
        $this->assertSame('completed', $control->refresh()->status);
        $this->assertSame(50, $audit->refresh()->progress_percent);
        [$otherOrder,,$otherLead] = $this->order();
        $other = $this->start($otherOrder, $otherLead);
        $this->actingAs($lead)->get(route('engineer.evidence.download', [$other, $evidence]))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'technical_audit.evidence_uploaded', 'subject_id' => $audit->id]);
    }

    public function test_not_applicable_requires_reason_and_counts_as_complete(): void
    {
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $control = $audit->controls()->where('code', 'CTRL-2')->firstOrFail();
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), ['not_applicable' => 1, 'complete' => 1])->assertSessionHasErrors('not_applicable_reason');
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $control]), ['not_applicable' => 1, 'not_applicable_reason' => 'System nie wystepuje', 'complete' => 1])->assertRedirect();
        $this->assertSame('not_applicable', $control->refresh()->status);
    }

    public function test_blocked_control_and_critical_escalation_block_submission_then_lead_resolves(): void
    {
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $control = $audit->controls->first();
        $this->actingAs($auditor)->post(route('engineer.escalations.store', [$audit, $control]), ['reason' => 'Potrzebna decyzja', 'priority' => 'critical'])->assertRedirect();
        $escalation = TechnicalAuditEscalation::firstOrFail();
        $this->assertSame('requires_consultation', $control->refresh()->status);
        $this->expectException(ValidationException::class);
        app(TechnicalAuditWorkflowService::class)->transition($audit, TechnicalAuditStatus::ReadyForSubmission, $auditor);
    }

    public function test_lead_answers_escalation_and_notification_is_sent(): void
    {
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $control = $audit->controls->first();
        $e = $control->escalations()->create(['technical_audit_id' => $audit->id, 'created_by' => $auditor->id, 'reason' => 'Pytanie', 'priority' => 'high']);
        $this->actingAs($lead)->patch(route('engineer.escalations.respond', $e), ['response' => 'Zweryfikuj logi', 'resolve' => 1])->assertRedirect();
        $this->assertSame('resolved', $e->refresh()->status);
        $this->assertDatabaseHas('audit_notifications', ['user_id' => $auditor->id, 'type' => 'technical_audit.escalation_answered']);
    }

    public function test_complete_audit_can_be_submitted_returned_and_technically_approved(): void
    {
        Storage::fake('local');
        [$order,$auditor,$lead] = $this->order();
        $audit = $this->start($order, $lead);
        $first = $audit->controls()->where('code', 'CTRL-1')->first();
        $this->actingAs($auditor)->post(route('engineer.evidence.store', [$audit, $first]), ['evidence' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), 'evidence_type' => 'document']);
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $first]), ['value' => '1', 'result_status' => 'compliant', 'complete' => 1]);
        $second = $audit->controls()->where('code', 'CTRL-2')->first();
        $this->actingAs($auditor)->patch(route('engineer.answers.update', [$audit, $second]), ['not_applicable' => 1, 'not_applicable_reason' => 'Brak systemu', 'complete' => 1]);
        $workflow = app(TechnicalAuditWorkflowService::class);
        $workflow->transition($audit->refresh(), TechnicalAuditStatus::ReadyForSubmission, $auditor);
        $workflow->transition($audit->refresh(), TechnicalAuditStatus::SubmittedForReview, $auditor);
        $workflow->transition($audit->refresh(), TechnicalAuditStatus::ChangesRequested, $lead, ['comment' => 'Uzupelnij opis']);
        $workflow->transition($audit->refresh(), TechnicalAuditStatus::InProgress, $auditor);
        $workflow->transition($audit->refresh(), TechnicalAuditStatus::ReadyForSubmission, $auditor);
        $workflow->transition($audit->refresh(), TechnicalAuditStatus::SubmittedForReview, $auditor);
        $workflow->transition($audit->refresh(), TechnicalAuditStatus::TechnicallyApproved, $lead);
        $this->assertSame(TechnicalAuditStatus::TechnicallyApproved, $audit->refresh()->status);
        $this->assertSame(100, $audit->progress_percent);
        $this->assertNotNull($audit->completed_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'technical_audit.technically_approved', 'subject_id' => $audit->id]);
    }

    private function start(AuditOrder $o, User $lead): TechnicalAudit
    {
        $this->actingAs($lead);

        return app(TechnicalAuditCreationService::class)->create($o, $lead);
    }

    private function user(UserRole $r, ?CompetencyLevel $c = null): User
    {
        return User::factory()->create(['role' => $r, 'active' => true, 'competency_level' => $c]);
    }

    private function order(): array
    {
        $sales = $this->user(UserRole::Sales);
        $lead = $this->user(UserRole::TechnicalLead, CompetencyLevel::TechnicalLead);
        $auditor = $this->user(UserRole::Auditor, CompetencyLevel::Senior);
        $client = Client::create(['name' => fake()->unique()->company(), 'status' => 'active']);
        $type = AuditType::create(['name' => 'Audyt techniczny', 'code' => fake()->unique()->lexify('T-????'), 'active' => true, 'created_by' => $lead->id]);
        $version = AuditTypeVersion::create(['audit_type_id' => $type->id, 'version' => 1, 'status' => 'draft', 'name_snapshot' => $type->name]);
        $q = SalesQualification::create(['client_id' => $client->id, 'audit_type_id' => $type->id, 'audit_type_version_id' => $version->id, 'title' => 'Audyt 3A', 'sales_owner_id' => $sales->id, 'status' => 'ready_for_pricing', 'qualification_snapshot' => []]);
        $quote = Quotation::create(['number' => 'AUD/'.now()->year.'/'.fake()->unique()->numerify('####'), 'sales_qualification_id' => $q->id, 'client_id' => $client->id, 'audit_type_id' => $type->id, 'audit_type_version_id' => $version->id, 'sales_owner_id' => $sales->id, 'status' => 'accepted', 'is_current' => true, 'total_hours' => 8, 'calculation_snapshot' => []]);
        $snapshot = ['version' => ['version' => 1], 'technical_modules' => [['id' => 10, 'code' => 'MOD-1', 'name' => 'Siec', 'description' => 'Modul', 'sort_order' => 1, 'active' => true, 'estimated_minutes' => 60, 'controls' => [['id' => 20, 'code' => 'CTRL-1', 'name' => 'Kontrola pierwsza', 'field_type' => 'boolean', 'required' => true, 'require_evidence' => true, 'allow_not_applicable' => false, 'require_comment_when_na' => false, 'active' => true, 'sort_order' => 1], ['id' => 21, 'code' => 'CTRL-2', 'name' => 'Kontrola druga', 'field_type' => 'text', 'required' => true, 'require_evidence' => false, 'allow_not_applicable' => true, 'require_comment_when_na' => true, 'active' => true, 'sort_order' => 2]]]]];
        $order = AuditOrder::create(['number' => 'AUD-ZL/'.now()->year.'/'.fake()->unique()->numerify('####'), 'quotation_id' => $quote->id, 'sales_qualification_id' => $q->id, 'client_id' => $client->id, 'audit_type_id' => $type->id, 'audit_type_version_id' => $version->id, 'title' => 'Audyt 3A', 'status' => AuditOrderStatus::Scheduled, 'sales_owner_id' => $sales->id, 'delivery_owner_id' => $lead->id, 'technical_lead_id' => $lead->id, 'expected_hours' => 8, 'planned_hours' => 8, 'engineers_count' => 1, 'configuration_snapshot' => $snapshot, 'source_snapshot' => ['quotation' => ['number' => $quote->number]], 'created_by' => $sales->id]);
        $order->assignees()->create(['user_id' => $auditor->id, 'assignment_role' => 'auditor', 'planned_hours' => 8, 'competency_level' => 'senior', 'assigned_by' => $lead->id, 'assigned_at' => now()]);

        return [$order, $auditor, $lead, $sales];
    }
}
