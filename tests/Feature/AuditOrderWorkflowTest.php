<?php

namespace Tests\Feature;

use App\Enums\AuditOrderStatus;
use App\Enums\CompetencyLevel;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Audit;
use App\Models\AuditControlDefinition;
use App\Models\AuditOrder;
use App\Models\AuditOrderAssignee;
use App\Models\AuditType;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
use App\Models\Client;
use App\Models\QualificationAttachment;
use App\Models\Quotation;
use App\Models\SalesQualification;
use App\Models\User;
use App\Services\AuditOrderCreationService;
use App\Services\AuditOrderWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_current_quotation_creates_one_order_with_snapshot_checklist_and_number(): void
    {
        [$quotation, $sales] = $this->quotation();
        $order = $this->createOrder($quotation, $sales);

        $this->assertMatchesRegularExpression('/^AUD-ZL\/\d{4}\/0001$/', $order->number);
        $this->assertSame(AuditOrderStatus::AwaitingPlanning, $order->status);
        $this->assertSame('12.00', $order->expected_hours);
        $this->assertCount(12, $order->preparationItems);
        $this->assertSame($quotation->number, $order->source_snapshot['quotation']['number']);
        $this->assertArrayHasKey('technical_modules', $order->configuration_snapshot);
        $this->assertSame('CTRL-01', $order->configuration_snapshot['technical_modules'][0]['controls'][0]['code']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'audit_order.created', 'subject_id' => $order->id]);
        $this->assertSame(0, Audit::count());
    }

    public function test_source_attachment_is_linked_without_copying_file(): void
    {
        Storage::fake('local');
        [$quotation, $sales] = $this->quotation();
        Storage::disk('local')->put('qualification/source.pdf', 'content');
        $source = QualificationAttachment::create(['sales_qualification_id' => $quotation->sales_qualification_id, 'uploaded_by' => $sales->id, 'disk' => 'local', 'path' => 'qualification/source.pdf', 'original_name' => 'source.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 7]);

        $document = $this->createOrder($quotation, $sales)->documents->firstOrFail();

        $this->assertSame(QualificationAttachment::class, $document->source_type);
        $this->assertSame($source->id, $document->source_id);
        $this->assertSame($source->path, $document->path);
        Storage::disk('local')->assertExists('qualification/source.pdf');
    }

    public function test_duplicate_or_non_accepted_quotation_cannot_create_order(): void
    {
        [$quotation, $sales] = $this->quotation();
        $this->createOrder($quotation, $sales);
        try {
            $this->createOrder($quotation, $sales);
            $this->fail('Duplicate should fail.');
        } catch (ValidationException) {
            $this->assertSame(1, AuditOrder::count());
        }

        [$draft] = $this->quotation(['status' => QuotationStatus::Calculated]);
        $this->expectException(AuthorizationException::class);
        $this->createOrder($draft, $sales);
    }

    public function test_snapshot_does_not_change_after_source_changes(): void
    {
        [$quotation, $sales] = $this->quotation();
        $order = $this->createOrder($quotation, $sales);
        $snapshot = $order->source_snapshot;

        $quotation->qualification->update(['title' => 'Nowy tytul', 'scope_summary' => 'Nowy zakres']);
        $quotation->update(['total_hours' => 99]);

        $this->assertSame($snapshot, $order->refresh()->source_snapshot);
    }

    public function test_sales_sees_only_own_orders_auditor_only_assigned_and_client_is_denied(): void
    {
        [$quotation, $sales] = $this->quotation();
        $order = $this->createOrder($quotation, $sales);
        $otherSales = $this->user(UserRole::Sales);
        $auditor = $this->user(UserRole::Auditor, CompetencyLevel::Senior);
        $clientUser = $this->user(UserRole::Client, null, $quotation->client_id);

        $this->actingAs($sales)->get(route('delivery.audit-orders.show', $order))->assertOk();
        $this->actingAs($otherSales)->get(route('delivery.audit-orders.show', $order))->assertForbidden();
        $this->actingAs($auditor)->get(route('delivery.audit-orders.show', $order))->assertForbidden();
        $this->actingAs($clientUser)->get(route('delivery.audit-orders.index'))->assertForbidden();

        $this->assign($order, $auditor, 'auditor');
        $this->actingAs($auditor)->get(route('delivery.audit-orders.show', $order))->assertOk();
        $this->actingAs($auditor)->patch(route('delivery.audit-orders.plan', $order), ['planned_hours' => 4])->assertForbidden();
    }

    public function test_competency_level_meets_uses_ordered_scale(): void
    {
        $this->assertTrue(CompetencyLevel::Senior->meets(CompetencyLevel::Regular));
        $this->assertTrue(CompetencyLevel::TechnicalLead->meets(CompetencyLevel::Specialist));
        $this->assertFalse(CompetencyLevel::Junior->meets(CompetencyLevel::Regular));
    }

    public function test_user_competency_is_removed_for_non_technical_role(): void
    {
        $sales = $this->user(UserRole::Sales);
        $sales->update(['competency_level' => CompetencyLevel::Senior]);
        $this->assertNull($sales->refresh()->competency_level);
    }

    public function test_ready_transition_requires_complete_plan_and_required_checklist(): void
    {
        [$quotation, $sales, $lead] = $this->quotation();
        $order = $this->createOrder($quotation, $sales);
        app(AuditOrderWorkflowService::class)->transition($order, AuditOrderStatus::Planning, $lead);

        $this->expectException(ValidationException::class);
        app(AuditOrderWorkflowService::class)->transition($order->refresh(), AuditOrderStatus::Ready, $lead);
    }

    public function test_complete_order_can_be_ready_scheduled_and_started_but_not_skip_status(): void
    {
        [$quotation, $sales, $lead] = $this->quotation();
        $order = $this->createOrder($quotation, $sales);
        $auditor = $this->user(UserRole::Auditor, CompetencyLevel::Senior);
        $this->assign($order, $lead, 'delivery_owner');
        $this->assign($order, $lead, 'technical_lead');
        $this->assign($order, $auditor, 'auditor');
        $order->update(['planned_start_at' => now()->addDay(), 'planned_end_at' => now()->addDays(2), 'planned_hours' => 12]);
        $order->preparationItems()->update(['status' => 'completed', 'completed' => true, 'completed_by' => $lead->id, 'completed_at' => now()]);
        $workflow = app(AuditOrderWorkflowService::class);
        $workflow->transition($order, AuditOrderStatus::Planning, $lead);
        $workflow->transition($order->refresh(), AuditOrderStatus::Ready, $lead);
        $workflow->transition($order->refresh(), AuditOrderStatus::Scheduled, $lead);
        $workflow->transition($order->refresh(), AuditOrderStatus::InProgress, $lead);

        $this->assertSame(AuditOrderStatus::InProgress, $order->refresh()->status);
        $this->assertDatabaseHas('audit_notifications', ['user_id' => $sales->id, 'type' => 'audit_order.ready']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'audit_order.status_changed', 'subject_id' => $order->id]);
    }

    public function test_assignment_snapshots_competency_and_sends_notification(): void
    {
        [$quotation, $sales, $lead] = $this->quotation();
        $order = $this->createOrder($quotation, $sales);
        $auditor = $this->user(UserRole::Auditor, CompetencyLevel::Regular);

        $this->actingAs($lead)->post(route('delivery.audit-orders.assignees.store', $order), ['user_id' => $auditor->id, 'assignment_role' => 'auditor', 'planned_hours' => 8])
            ->assertRedirect()->assertSessionHas('status', fn (string $status) => str_contains($status, 'nizszy od wymaganego'));

        $this->assertDatabaseHas('audit_order_assignees', ['user_id' => $auditor->id, 'competency_level' => 'regular']);
        $this->assertDatabaseHas('audit_notifications', ['user_id' => $auditor->id, 'type' => 'audit_order.assigned']);
    }

    public function test_delivery_document_upload_is_audited_and_download_is_protected_against_idor(): void
    {
        Storage::fake('local');
        [$quotation, $sales, $lead] = $this->quotation();
        $order = $this->createOrder($quotation, $sales);
        $this->actingAs($lead)->post(route('delivery.audit-orders.documents.store', $order), ['category' => 'technical_document', 'document' => UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')])->assertRedirect();
        $document = $order->documents()->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['event' => 'audit_order.document_uploaded', 'subject_id' => $order->id]);

        [$otherQuote, $otherSales] = $this->quotation();
        $otherOrder = $this->createOrder($otherQuote, $otherSales);
        $this->actingAs($lead)->get(route('delivery.audit-orders.documents.download', [$otherOrder, $document]))->assertForbidden();
    }

    public function test_order_numbers_are_sequential_and_cancellation_requires_reason(): void
    {
        [$firstQuote, $firstSales, $lead] = $this->quotation();
        [$secondQuote, $secondSales] = $this->quotation();
        $first = $this->createOrder($firstQuote, $firstSales);
        $second = $this->createOrder($secondQuote, $secondSales);
        $this->assertStringEndsWith('/0001', $first->number);
        $this->assertStringEndsWith('/0002', $second->number);

        $this->expectException(ValidationException::class);
        app(AuditOrderWorkflowService::class)->transition($first, AuditOrderStatus::Cancelled, $lead);
    }

    /** @return array{Quotation, User, User} */
    private function quotation(array $quotationOverrides = []): array
    {
        $sales = $this->user(UserRole::Sales);
        $lead = $this->user(UserRole::TechnicalLead, CompetencyLevel::TechnicalLead);
        $client = Client::create(['name' => fake()->unique()->company(), 'status' => 'active']);
        $type = AuditType::create(['name' => 'Audyt infrastruktury', 'code' => fake()->unique()->lexify('TYPE-????'), 'active' => true, 'created_by' => $lead->id]);
        $version = AuditTypeVersion::create(['audit_type_id' => $type->id, 'version' => 1, 'status' => 'draft', 'name_snapshot' => $type->name, 'minimum_competency_level' => CompetencyLevel::Senior, 'delivery_instructions' => 'Instrukcje Delivery']);
        $module = AuditTypeModule::create(['audit_type_version_id' => $version->id, 'name' => 'Infrastruktura', 'code' => 'TECH', 'module_type' => 'technical', 'active' => true]);
        AuditControlDefinition::create(['audit_type_module_id' => $module->id, 'code' => 'CTRL-01', 'name' => 'Kontrola dostepu', 'field_type' => 'boolean', 'active' => true]);
        $qualification = SalesQualification::create(['client_id' => $client->id, 'audit_type_id' => $type->id, 'audit_type_version_id' => $version->id, 'title' => 'Audyt klienta', 'purpose' => 'Ocena bezpieczenstwa', 'contact_name' => 'Jan Klient', 'contact_email' => 'jan@example.test', 'sales_owner_id' => $sales->id, 'status' => 'ready_for_pricing', 'qualification_snapshot' => ['version' => ['id' => $version->id]], 'scope_summary' => 'Infrastruktura centralna']);
        $quotation = Quotation::create(array_merge(['number' => 'AUD/'.now()->format('Y').'/'.fake()->unique()->numerify('####'), 'sales_qualification_id' => $qualification->id, 'client_id' => $client->id, 'audit_type_id' => $type->id, 'audit_type_version_id' => $version->id, 'sales_owner_id' => $sales->id, 'version' => 1, 'is_current' => true, 'status' => QuotationStatus::Accepted, 'total_hours' => 12, 'engineers_count' => 1, 'calculation_snapshot' => ['source' => 'test'], 'accepted_at' => now(), 'accepted_by' => 'Klient'], $quotationOverrides));

        return [$quotation, $sales, $lead];
    }

    private function user(UserRole $role, ?CompetencyLevel $competency = null, ?int $clientId = null): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'client_id' => $clientId, 'competency_level' => $competency]);
    }

    private function createOrder(Quotation $quotation, User $actor): AuditOrder
    {
        $this->actingAs($actor);

        return app(AuditOrderCreationService::class)->create($quotation, $actor);
    }

    private function assign(AuditOrder $order, User $user, string $role): AuditOrderAssignee
    {
        $assignee = $order->assignees()->create(['user_id' => $user->id, 'assignment_role' => $role, 'planned_hours' => 4, 'competency_level' => $user->competency_level, 'assigned_by' => $user->id, 'assigned_at' => now()]);
        if ($role === 'delivery_owner') {
            $order->update(['delivery_owner_id' => $user->id]);
        }
        if ($role === 'technical_lead') {
            $order->update(['technical_lead_id' => $user->id]);
        }

        return $assignee;
    }
}
