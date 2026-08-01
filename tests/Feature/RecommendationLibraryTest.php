<?php

namespace Tests\Feature;

use App\Models\AuditQuestion;
use App\Models\Recommendation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_recommendation_library(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount(Recommendation::class, 5);
        $this->assertDatabaseHas(Recommendation::class, [
            'title' => 'Wymuszenie MFA dla administratorow M365',
            'risk_level' => 'critical',
            'priority' => 'critical',
            'sales_category' => 'Microsoft 365 / Security',
            'global_it_can_do' => true,
        ]);
        $this->assertDatabaseHas(Recommendation::class, [
            'title' => 'Aktualizacja firmware UTM',
            'risk_level' => 'high',
            'priority' => 'high',
        ]);
    }

    public function test_recommendations_can_be_linked_to_audit_questions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $question = AuditQuestion::where('question', 'Czy firmware jest aktualny?')->firstOrFail();

        $this->assertTrue(
            $question->recommendations()
                ->where('title', 'Aktualizacja firmware UTM')
                ->exists(),
        );
    }

    public function test_authenticated_admin_can_open_recommendations_resource(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@globalit.test')->firstOrFail();

        $this
            ->actingAs($admin)
            ->get('/admin/recommendations')
            ->assertOk()
            ->assertSee('Rekomendacje')
            ->assertSee('Aktualizacja firmware UTM');
    }
}
