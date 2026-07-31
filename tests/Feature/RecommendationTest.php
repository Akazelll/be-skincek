<?php

namespace Tests\Feature;

use App\Models\SkinConcern;
use App\Models\SkinRecommendation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_filter_recommendations_by_ml_label(): void
    {
        $concern = SkinConcern::create([
            'name' => 'Acne',
            'ml_label' => 'acne',
            'description' => 'Acne vulgaris',
        ]);
        SkinRecommendation::create([
            'concern_id' => $concern->id,
            'doctor_id' => User::factory()->create()->id,
            'title' => 'Use Salicylic Acid',
            'recommendation_text' => 'Apply twice daily',
            'priority_level' => 'high',
        ]);
        SkinRecommendation::create([
            'concern_id' => SkinConcern::create(['name' => 'Eczema', 'ml_label' => 'eczema'])->id,
            'doctor_id' => User::factory()->create()->id,
            'title' => 'Use Moisturizer',
            'recommendation_text' => 'Apply daily',
        ]);

        $this->getJson('/api/v1/recommendations?ml_label=acne')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Use Salicylic Acid');
    }

    public function test_doctor_can_create_recommendation(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);
        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/recommendations', [
            'concern_id' => $concern->id,
            'title' => 'Gentle Cleanser',
            'recommendation_text' => 'Use morning and night',
            'priority_level' => 'medium',
        ])->assertCreated()
            ->assertJsonPath('data.doctor.uuid', $doctor->uuid);
    }

    public function test_non_doctor_cannot_create_recommendation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/recommendations', [
            'concern_id' => 1,
            'title' => 'Test',
            'recommendation_text' => 'Test',
        ])->assertForbidden();
    }
}
