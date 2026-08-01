<?php

namespace Tests\Feature;

use App\Models\AuditModule;
use App\Models\AuditTemplate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTemplateModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_stage_two_template_and_modules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas(AuditTemplate::class, [
            'name' => 'Audyt podstawowy IT',
            'active' => true,
        ]);
        $this->assertDatabaseHas(AuditModule::class, [
            'name' => 'UTM/firewall',
            'category' => 'security',
            'active' => true,
        ]);
        $this->assertDatabaseCount(AuditTemplate::class, 1);
        $this->assertDatabaseCount(AuditModule::class, 5);

        $template = AuditTemplate::where('name', 'Audyt podstawowy IT')->firstOrFail();

        $this->assertSame(5, $template->modules()->count());
    }

    public function test_template_modules_are_returned_in_pivot_sort_order(): void
    {
        $template = AuditTemplate::create([
            'name' => 'Audyt testowy',
            'active' => true,
        ]);
        $first = AuditModule::create(['name' => 'Pierwszy', 'active' => true, 'sort_order' => 20]);
        $second = AuditModule::create(['name' => 'Drugi', 'active' => true, 'sort_order' => 10]);

        $template->templateModules()->create([
            'audit_module_id' => $second->id,
            'sort_order' => 20,
        ]);
        $template->templateModules()->create([
            'audit_module_id' => $first->id,
            'sort_order' => 10,
        ]);

        $this->assertSame(['Pierwszy', 'Drugi'], $template->modules->pluck('name')->all());
    }
}
