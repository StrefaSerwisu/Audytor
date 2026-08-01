<?php

namespace Tests\Feature;

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

        foreach (['super_admin', 'global_admin', 'technical_lead', 'sales'] as $role) {
            $user = User::factory()->create(['role' => $role, 'active' => true]);

            $this->assertTrue($user->canAccessPanel($panel), "{$role} should access admin panel.");
        }
    }

    public function test_auditor_and_inactive_users_cannot_access_filament_panel(): void
    {
        $panel = Panel::make()->id('admin');
        $auditor = User::factory()->create(['role' => 'auditor', 'active' => true]);
        $inactiveAdmin = User::factory()->create(['role' => 'global_admin', 'active' => false]);

        $this->assertFalse($auditor->canAccessPanel($panel));
        $this->assertFalse($inactiveAdmin->canAccessPanel($panel));
    }
}
