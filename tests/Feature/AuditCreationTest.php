<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_test_audit_from_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $audit = Audit::where('title', 'Audyt podstawowy IT - Klient Testowy')->firstOrFail();

        $this->assertSame('scheduled', $audit->status);
        $this->assertSame('Klient Testowy Sp. z o.o.', $audit->client->name);
        $this->assertSame('Centrala', $audit->location->name);
        $this->assertSame('Audyt podstawowy IT', $audit->template->name);
        $this->assertSame(5, $audit->selectedModules()->count());
        $this->assertSame(2, $audit->assignees()->count());
        $this->assertTrue($audit->modules()->where('name', 'UTM/firewall')->exists());
    }

    public function test_authenticated_admin_can_open_audits_resource(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();

        $this
            ->actingAs($admin)
            ->get('/admin/audits')
            ->assertOk()
            ->assertSee('Audyty')
            ->assertSee('Audyt podstawowy IT - Klient Testowy');
    }
}
