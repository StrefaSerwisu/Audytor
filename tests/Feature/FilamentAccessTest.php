<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_roles_can_access_filament_panel(): void
    {
        $panel = Panel::make()->id('admin');

        foreach (['super_admin', 'global_admin', 'technical_lead'] as $role) {
            $user = User::factory()->create(['role' => $role, 'active' => true]);

            $this->assertTrue($user->canAccessPanel($panel), "{$role} should access admin panel.");
        }
    }

    public function test_sales_auditor_client_and_inactive_users_cannot_access_filament_panel(): void
    {
        $panel = Panel::make()->id('admin');
        $sales = User::factory()->create(['role' => 'sales', 'active' => true]);
        $auditor = User::factory()->create(['role' => 'auditor', 'active' => true]);
        $clientAccount = Client::create(['name' => 'Klient testowy']);
        $client = User::factory()->create([
            'role' => 'client',
            'client_id' => $clientAccount->id,
            'active' => true,
        ]);
        $inactiveAdmin = User::factory()->create(['role' => 'global_admin', 'active' => false]);

        $this->assertFalse($sales->canAccessPanel($panel));
        $this->assertFalse($auditor->canAccessPanel($panel));
        $this->assertFalse($client->canAccessPanel($panel));
        $this->assertFalse($inactiveAdmin->canAccessPanel($panel));
    }
}
