<?php

namespace Tests\Feature;

use App\Enums\CompetencyLevel;
use App\Enums\UserRole;
use App\Models\AuditControlDefinition;
use App\Models\AuditLog;
use App\Models\AuditType;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
use App\Models\SalesQualificationQuestion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditTypeLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_audit_type_and_code_is_unique(): void
    {
        $admin = $this->actingAsAdministrator();
        $type = $this->createAuditType('BASIC-IT');

        $this->assertSame($admin->id, $type->created_by);
        $this->assertTrue($type->active);
        $this->assertDatabaseHas('audit_types', ['code' => 'BASIC-IT']);

        $this->expectException(QueryException::class);
        $this->createAuditType('BASIC-IT');
    }

    public function test_first_and_next_draft_versions_receive_sequential_numbers(): void
    {
        $this->actingAsAdministrator();
        $type = $this->createAuditType();

        $first = $type->createDraftVersion();
        $second = $type->createDraftVersion(['name_snapshot' => 'Audyt IT 2']);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(AuditTypeVersion::STATUS_DRAFT, $first->status);
        $this->assertSame($type->name, $first->name_snapshot);
    }

    public function test_sales_and_technical_modules_are_separate(): void
    {
        $this->actingAsAdministrator();
        [, $version, $salesModule, $technicalModule] = $this->createDraftLibrary();

        $this->assertSame(AuditTypeModule::TYPE_SALES, $salesModule->module_type);
        $this->assertSame(AuditTypeModule::TYPE_TECHNICAL, $technicalModule->module_type);
        $this->assertCount(2, $version->modules);
    }

    public function test_sales_questions_can_only_be_added_to_sales_module(): void
    {
        $this->actingAsAdministrator();
        [, , $salesModule, $technicalModule] = $this->createDraftLibrary(includeDefinitions: false);

        $question = $this->createSalesQuestion($salesModule);
        $this->assertSame('number', $question->field_type);
        $this->assertTrue($question->affects_pricing);

        $this->expectException(ValidationException::class);
        $this->createSalesQuestion($technicalModule, 'INVALID-SALES');
    }

    public function test_controls_can_only_be_added_to_technical_module(): void
    {
        $this->actingAsAdministrator();
        [, , $salesModule, $technicalModule] = $this->createDraftLibrary(includeDefinitions: false);

        $control = $this->createTechnicalControl($technicalModule);
        $this->assertSame(CompetencyLevel::Senior, $control->minimum_competency_level);
        $this->assertTrue($control->require_evidence);

        $this->expectException(ValidationException::class);
        $this->createTechnicalControl($salesModule, 'INVALID-CONTROL');
    }

    public function test_complete_version_can_be_published_and_becomes_current(): void
    {
        $admin = $this->actingAsAdministrator();
        [$type, $version] = $this->createDraftLibrary();

        $version->publish($admin);

        $this->assertSame(AuditTypeVersion::STATUS_PUBLISHED, $version->status);
        $this->assertSame($admin->id, $version->published_by);
        $this->assertNotNull($version->published_at);
        $this->assertSame($version->id, $type->refresh()->current_version_id);
    }

    public function test_publication_is_blocked_without_modules(): void
    {
        $admin = $this->actingAsAdministrator();
        $version = $this->createAuditType()->createDraftVersion();

        $this->expectException(ValidationException::class);
        $version->publish($admin);
    }

    public function test_publication_is_blocked_without_technical_module(): void
    {
        $admin = $this->actingAsAdministrator();
        $version = $this->createAuditType()->createDraftVersion();
        $version->modules()->create([
            'name' => 'Kwalifikacja',
            'code' => 'SALES',
            'module_type' => AuditTypeModule::TYPE_SALES,
            'active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $version->publish($admin);
    }

    public function test_published_version_and_its_definitions_are_immutable(): void
    {
        $admin = $this->actingAsAdministrator();
        [, $version, , $technicalModule] = $this->createDraftLibrary();
        $version->publish($admin);

        try {
            $version->update(['name_snapshot' => 'Zmieniona nazwa']);
            $this->fail('Opublikowana wersja zostala zmieniona.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $technicalModule->update(['name' => 'Zmieniony modul']);
    }

    public function test_new_version_does_not_change_published_version_snapshot(): void
    {
        $admin = $this->actingAsAdministrator();
        [$type, $published] = $this->createDraftLibrary();
        $published->publish($admin);
        $snapshot = $published->snapshot();

        $next = $type->createDraftVersion(['name_snapshot' => 'Nowa wersja produktu']);
        $next->update([
            'delivery_instructions' => 'Calkowicie nowa instrukcja.',
            'ai_enabled' => true,
            'ai_configuration' => ['mode' => 'future'],
        ]);
        $next->modules()->create([
            'name' => 'Nowy modul techniczny',
            'code' => 'TECH-V2',
            'module_type' => AuditTypeModule::TYPE_TECHNICAL,
            'active' => true,
        ]);

        $this->assertSame(2, $next->version);
        $this->assertSame($snapshot, $published->fresh()->snapshot());
    }

    public function test_snapshot_contains_sales_technical_times_competency_and_ai_configuration(): void
    {
        $this->actingAsAdministrator();
        [, $version] = $this->createDraftLibrary();
        $snapshot = $version->snapshot();

        $this->assertSame('senior', $snapshot['version']['minimum_competency_level']);
        $this->assertSame(30, $snapshot['version']['estimated_preparation_minutes']);
        $this->assertTrue($snapshot['version']['ai_enabled']);
        $this->assertSame(['provider' => 'future'], $snapshot['version']['ai_configuration']);
        $this->assertCount(1, $snapshot['sales_modules']);
        $this->assertCount(1, $snapshot['sales_modules'][0]['questions']);
        $this->assertCount(1, $snapshot['technical_modules']);
        $this->assertCount(1, $snapshot['technical_modules'][0]['controls']);
        $this->assertStringContainsString('krok po kroku', $snapshot['technical_modules'][0]['controls'][0]['execution_instructions']);
    }

    public function test_sales_and_auditor_cannot_access_audit_type_library(): void
    {
        foreach ([UserRole::Sales, UserRole::Auditor] as $role) {
            $user = User::factory()->create(['role' => $role, 'active' => true]);
            $this->actingAs($user)->get('/admin/audit-types')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_management_roles_can_access_all_audit_type_resources(): void
    {
        $paths = [
            '/admin/audit-types',
            '/admin/audit-type-versions',
            '/admin/audit-type-modules',
            '/admin/sales-qualification-questions',
            '/admin/audit-control-definitions',
        ];

        foreach ([UserRole::SuperAdmin, UserRole::GlobalAdmin, UserRole::TechnicalLead] as $role) {
            $user = User::factory()->create(['role' => $role, 'active' => true]);

            foreach ($paths as $path) {
                $this->actingAs($user)->get($path)->assertOk();
            }

            $this->flushSession();
        }
    }

    public function test_unauthorized_role_cannot_publish_version(): void
    {
        $this->actingAsAdministrator();
        [, $version] = $this->createDraftLibrary();
        $auditor = User::factory()->create(['role' => UserRole::Auditor, 'active' => true]);

        $this->expectException(AuthorizationException::class);
        $version->publish($auditor);
    }

    public function test_publication_and_library_creation_are_audited_without_large_instructions(): void
    {
        $admin = $this->actingAsAdministrator();
        [$type, $version] = $this->createDraftLibrary();
        $version->publish($admin);

        foreach ([
            'audit_type.created',
            'audit_type_version.created',
            'audit_type_module.created',
            'sales_question.created',
            'audit_control.created',
            'audit_type_version.published',
        ] as $event) {
            $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'event' => $event]);
        }

        $publicationLog = AuditLog::where('event', 'audit_type_version.published')->firstOrFail();
        $this->assertSame($type->id, $publicationLog->metadata['audit_type_id']);
        $this->assertArrayNotHasKey('delivery_instructions', $publicationLog->metadata);
        $this->assertArrayNotHasKey('execution_instructions', $publicationLog->metadata);
    }

    public function test_only_draft_versions_can_be_deleted_and_types_with_published_history_are_protected(): void
    {
        $admin = $this->actingAsAdministrator();
        [$type, $version] = $this->createDraftLibrary();
        $version->publish($admin);

        $this->assertFalse($admin->can('delete', $version));
        $this->assertFalse($admin->can('delete', $type));

        $this->expectException(ValidationException::class);
        $version->delete();
    }

    public function test_old_published_version_can_be_archived_without_changing_new_current_version(): void
    {
        $admin = $this->actingAsAdministrator();
        [$type, $first] = $this->createDraftLibrary();
        $first->publish($admin);

        $second = $type->createDraftVersion();
        $second->modules()->create([
            'name' => 'Techniczny v2',
            'code' => 'TECH-V2',
            'module_type' => AuditTypeModule::TYPE_TECHNICAL,
            'active' => true,
        ]);
        $second->publish($admin);
        $first->archive($admin);

        $this->assertSame(AuditTypeVersion::STATUS_ARCHIVED, $first->status);
        $this->assertSame($second->id, $type->refresh()->current_version_id);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'audit_type_version.archived',
            'subject_id' => $first->id,
        ]);
    }

    private function actingAsAdministrator(): User
    {
        $admin = User::factory()->create([
            'role' => UserRole::GlobalAdmin,
            'active' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    private function createAuditType(string $code = 'AUDIT-IT'): AuditType
    {
        return AuditType::create([
            'name' => 'Audyt podstawowy IT',
            'code' => $code,
            'category' => 'IT',
            'description' => 'Wersjonowany produkt audytowy.',
            'sales_instructions' => 'Zbierz informacje o skali srodowiska.',
            'delivery_instructions' => 'Zweryfikuj zakres przed rozpoczeciem.',
            'active' => true,
        ]);
    }

    /** @return array{AuditType, AuditTypeVersion, AuditTypeModule, AuditTypeModule} */
    private function createDraftLibrary(bool $includeDefinitions = true): array
    {
        $type = $this->createAuditType();
        $version = $type->createDraftVersion([
            'minimum_competency_level' => CompetencyLevel::Senior,
            'estimated_preparation_minutes' => 30,
            'estimated_execution_minutes' => 180,
            'estimated_reporting_minutes' => 60,
            'estimated_review_minutes' => 30,
            'ai_enabled' => true,
            'ai_configuration' => ['provider' => 'future'],
        ]);
        $salesModule = $version->modules()->create([
            'name' => 'Kwalifikacja Sales',
            'code' => 'SALES',
            'module_type' => AuditTypeModule::TYPE_SALES,
            'sort_order' => 10,
            'active' => true,
            'estimated_minutes' => 20,
        ]);
        $technicalModule = $version->modules()->create([
            'name' => 'Kontrole techniczne',
            'code' => 'TECH',
            'module_type' => AuditTypeModule::TYPE_TECHNICAL,
            'sort_order' => 20,
            'active' => true,
            'estimated_minutes' => 180,
        ]);

        if ($includeDefinitions) {
            $this->createSalesQuestion($salesModule);
            $this->createTechnicalControl($technicalModule);
        }

        return [$type, $version, $salesModule, $technicalModule];
    }

    private function createSalesQuestion(AuditTypeModule $module, string $code = 'DEVICE-COUNT'): SalesQualificationQuestion
    {
        return $module->salesQuestions()->create([
            'code' => $code,
            'question' => 'Ile urzadzen obejmuje audyt?',
            'field_type' => 'number',
            'required' => true,
            'affects_scope' => true,
            'affects_pricing' => true,
            'pricing_variable' => 'device_count',
            'sort_order' => 10,
            'active' => true,
        ]);
    }

    private function createTechnicalControl(AuditTypeModule $module, string $code = 'BACKUP-RESTORE'): AuditControlDefinition
    {
        return $module->controlDefinitions()->create([
            'code' => $code,
            'name' => 'Test odtworzenia backupu',
            'objective' => 'Potwierdzic mozliwosc odtworzenia danych.',
            'execution_instructions' => 'Wykonaj test krok po kroku i zapisz wynik.',
            'where_to_check' => 'Konsola systemu backupowego.',
            'required_access' => 'Konto tylko do odczytu.',
            'required_tools' => 'Konsola backupu.',
            'minimum_competency_level' => CompetencyLevel::Senior,
            'estimated_minutes' => 45,
            'field_type' => 'boolean',
            'required' => true,
            'allow_not_applicable' => false,
            'require_evidence' => true,
            'evidence_types' => ['screenshot'],
            'positive_criteria' => 'Odtworzenie zakonczone powodzeniem.',
            'negative_criteria' => 'Brak testu lub blad odtworzenia.',
            'default_risk_level' => 'high',
            'sort_order' => 10,
            'active' => true,
        ]);
    }
}
