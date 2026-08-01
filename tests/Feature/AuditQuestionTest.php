<?php

namespace Tests\Feature;

use App\Models\AuditModule;
use App\Models\AuditQuestion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_utm_questions_with_required_evidence(): void
    {
        $this->seed(DatabaseSeeder::class);

        $utm = AuditModule::where('name', 'UTM/firewall')->firstOrFail();

        $this->assertSame(14, $utm->questions()->count());
        $this->assertDatabaseHas(AuditQuestion::class, [
            'audit_module_id' => $utm->id,
            'question' => 'Dodaj zdjecie urzadzenia w szafie rack.',
            'field_type' => 'photo',
            'require_photo' => true,
            'is_required' => true,
        ]);
        $this->assertDatabaseHas(AuditQuestion::class, [
            'audit_module_id' => $utm->id,
            'question' => 'Dodaj screenshot dashboardu.',
            'field_type' => 'screenshot',
            'require_screenshot' => true,
            'is_required' => true,
        ]);
        $this->assertDatabaseHas(AuditQuestion::class, [
            'audit_module_id' => $utm->id,
            'question' => 'Czy panel administracyjny jest dostepny z WAN?',
            'risk_enabled' => true,
        ]);
    }

    public function test_database_seeder_creates_questions_for_each_basic_audit_module(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['UTM/firewall', 'Switche', 'Serwery', 'Microsoft 365', 'Backup'] as $moduleName) {
            $module = AuditModule::where('name', $moduleName)->firstOrFail();

            $this->assertGreaterThanOrEqual(
                6,
                $module->questions()->count(),
                "Modul {$moduleName} powinien miec pytania audytowe.",
            );
        }

        $this->assertDatabaseHas(AuditQuestion::class, [
            'question' => 'Czy MFA jest wymuszone dla kont administracyjnych Microsoft 365?',
            'risk_enabled' => true,
        ]);
        $this->assertDatabaseHas(AuditQuestion::class, [
            'question' => 'Czy wykonywane sa testy odtworzeniowe?',
            'risk_enabled' => true,
        ]);
    }

    public function test_module_questions_can_be_sorted_by_sort_order(): void
    {
        $module = AuditModule::create([
            'name' => 'Modul testowy',
            'active' => true,
            'sort_order' => 10,
        ]);

        AuditQuestion::create([
            'audit_module_id' => $module->id,
            'question' => 'Drugie pytanie',
            'field_type' => 'short_text',
            'sort_order' => 20,
            'active' => true,
        ]);
        AuditQuestion::create([
            'audit_module_id' => $module->id,
            'question' => 'Pierwsze pytanie',
            'field_type' => 'short_text',
            'sort_order' => 10,
            'active' => true,
        ]);

        $this->assertSame(
            ['Pierwsze pytanie', 'Drugie pytanie'],
            $module->questions()->orderBy('sort_order')->pluck('question')->all(),
        );
    }

    public function test_authenticated_admin_can_open_audit_questions_resource(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();

        $this
            ->actingAs($admin)
            ->get('/admin/audit-questions')
            ->assertOk()
            ->assertSee('Pytania Audytowe')
            ->assertSee('UTM/firewall');
    }
}
