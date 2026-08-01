<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_stage_one_sample_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas(User::class, [
            'email' => 'superadmin@globalit.test',
            'role' => 'super_admin',
        ]);
        $this->assertDatabaseHas(Client::class, [
            'name' => 'Klient Testowy Sp. z o.o.',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas(User::class, [
            'email' => 'klient@globalit.test',
            'role' => 'client',
        ]);
        $this->assertDatabaseCount(User::class, 6);
        $this->assertDatabaseCount(Client::class, 1);
        $this->assertDatabaseCount(ClientLocation::class, 3);
    }

    public function test_client_has_many_locations(): void
    {
        $client = Client::create(['name' => 'Acme Sp. z o.o.', 'status' => 'active']);

        ClientLocation::create([
            'client_id' => $client->id,
            'name' => 'Centrala',
            'location_type' => 'office',
        ]);

        $this->assertTrue($client->locations()->where('name', 'Centrala')->exists());
    }
}
