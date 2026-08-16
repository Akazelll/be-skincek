<?php

namespace Tests\Feature;

use App\Models\SkincareProduct;
use App\Models\SkinConcern;
use App\Models\SkinRecommendation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function approvedDoctor(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $doctor->doctorVerification()->create([
            'specialization' => 'Dermatology',
            'verification_status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $doctor;
    }

    public function test_doctor_can_list_own_products_including_inactive(): void
    {
        $doctor = $this->approvedDoctor();
        $otherDoctor = User::factory()->create();
        $otherDoctor->assignRole('doctor');
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);

        SkincareProduct::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'name' => 'My Active Product',
            'category' => 'Serum',
            'usage_instruction' => 'Night',
            'is_active' => true,
        ]);
        SkincareProduct::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'name' => 'My Inactive Product',
            'category' => 'Cream',
            'usage_instruction' => 'Day',
            'is_active' => false,
        ]);
        SkincareProduct::create([
            'doctor_id' => $otherDoctor->id,
            'concern_id' => $concern->id,
            'name' => 'Other Doctor Product',
            'category' => 'Gel',
            'usage_instruction' => 'Day',
            'is_active' => true,
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/v1/doctor/products')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('My Inactive Product'));
        $this->assertFalse($names->contains('Other Doctor Product'));
    }

    public function test_doctor_can_list_own_recommendations(): void
    {
        $doctor = $this->approvedDoctor();
        $otherDoctor = User::factory()->create();
        $otherDoctor->assignRole('doctor');
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);

        SkinRecommendation::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'title' => 'My Rec',
            'recommendation_text' => 'Text',
            'priority_level' => 'high',
        ]);
        SkinRecommendation::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'title' => 'My Inactive Rec',
            'recommendation_text' => 'Text',
            'is_active' => false,
        ]);
        SkinRecommendation::create([
            'doctor_id' => $otherDoctor->id,
            'concern_id' => $concern->id,
            'title' => 'Other Rec',
            'recommendation_text' => 'Text',
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/v1/doctor/recommendations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('My Inactive Rec'));
        $this->assertFalse($titles->contains('Other Rec'));
    }

    public function test_non_doctor_cannot_access_doctor_dashboard_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/doctor/products')->assertForbidden();
        $this->getJson('/api/v1/doctor/recommendations')->assertForbidden();
    }
}
