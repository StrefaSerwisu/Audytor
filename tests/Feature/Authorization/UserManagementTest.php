<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_and_global_admin_can_open_user_management_and_audit_log(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['superadmin@globalit.test', 'admin@globalit.test'] as $email) {
            $admin = User::where('email', $email)->firstOrFail();
            $this->flushSession();

            $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Uzytkownicy');
            $this->actingAs($admin)->get('/admin/audit-logs')->assertOk()->assertSee('Dziennik zdarzen');
        }
    }

    public function test_technical_lead_cannot_manage_users_or_view_audit_log(): void
    {
        $this->seed(DatabaseSeeder::class);
        $lead = User::where('email', 'lider@globalit.test')->firstOrFail();

        $this->actingAs($lead)->get('/admin/users')->assertForbidden();
        $this->actingAs($lead)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_user_changes_are_recorded_with_actor_and_changed_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();
        $this->actingAs($admin);

        $managedUser = User::create([
            'name' => 'Nowy Audytor',
            'email' => 'nowy.audytor@globalit.test',
            'password' => Hash::make('bezpieczne-haslo'),
            'role' => UserRole::Auditor,
            'active' => true,
        ]);

        $createdLog = AuditLog::where('event', 'user.created')->where('subject_id', $managedUser->id)->firstOrFail();

        $this->assertSame($admin->id, $createdLog->actor_id);
        $this->assertSame('nowy.audytor@globalit.test', $createdLog->new_values['email']);
        $this->assertArrayNotHasKey('password', $createdLog->new_values);

        $managedUser->update(['active' => false]);

        $updatedLog = AuditLog::where('event', 'user.updated')->where('subject_id', $managedUser->id)->latest('id')->firstOrFail();

        $this->assertTrue($updatedLog->old_values['active']);
        $this->assertFalse($updatedLog->new_values['active']);
    }

    public function test_global_admin_cannot_grant_or_modify_super_admin_role(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();
        $auditor = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@globalit.test')->firstOrFail();

        $this->assertFalse($admin->can('update', $superAdmin));

        $this->actingAs($admin);
        $this->expectException(AuthorizationException::class);

        $auditor->update(['role' => UserRole::SuperAdmin]);
    }

    public function test_user_cannot_deactivate_own_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();
        $this->actingAs($admin);

        $this->assertFalse($admin->can('delete', $admin));

        $this->expectException(AuthorizationException::class);

        $admin->update(['active' => false]);
    }

    public function test_client_assignment_is_removed_from_internal_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $superAdmin = User::where('email', 'superadmin@globalit.test')->firstOrFail();
        $client = Client::firstOrFail();
        $this->actingAs($superAdmin);

        $user = User::create([
            'name' => 'Uzytkownik wewnetrzny',
            'email' => 'wewnetrzny@globalit.test',
            'password' => Hash::make('bezpieczne-haslo'),
            'role' => UserRole::Auditor,
            'client_id' => $client->id,
            'active' => true,
        ]);

        $this->assertNull($user->client_id);
    }

    public function test_client_account_requires_client_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $superAdmin = User::where('email', 'superadmin@globalit.test')->firstOrFail();
        $this->actingAs($superAdmin);

        $this->expectException(ValidationException::class);

        User::create([
            'name' => 'Klient bez firmy',
            'email' => 'bez-firmy@globalit.test',
            'password' => Hash::make('bezpieczne-haslo'),
            'role' => UserRole::Client,
            'active' => true,
        ]);
    }

    public function test_empty_password_during_edit_keeps_existing_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $superAdmin = User::where('email', 'superadmin@globalit.test')->firstOrFail();
        $user = User::where('email', 'audytor@globalit.test')->firstOrFail();
        $passwordHash = $user->password;
        $this->actingAs($superAdmin);

        $data = UserResource::prepareFormData([
            'name' => 'Audytor po zmianie',
            'password' => '',
            'role' => UserRole::Auditor->value,
            'client_id' => Client::firstOrFail()->id,
        ]);

        $this->assertArrayNotHasKey('password', $data);
        $this->assertNull($data['client_id']);

        $user->update($data);

        $this->assertSame($passwordHash, $user->refresh()->password);
    }

    public function test_audit_log_is_read_only_for_administrators(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();
        $log = AuditLog::create(['event' => 'test.event']);

        $this->assertTrue($admin->can('viewAny', AuditLog::class));
        $this->assertFalse($admin->can('create', AuditLog::class));
        $this->assertFalse($admin->can('update', $log));
        $this->assertFalse($admin->can('delete', $log));
    }
}
