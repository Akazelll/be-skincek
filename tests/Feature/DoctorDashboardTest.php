<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DoctorRating;
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
        $this->getJson('/api/v1/doctor/dashboard')->assertForbidden();
    }

    public function test_doctor_dashboard_returns_stats_and_recent_conversations(): void
    {
        $doctor = $this->approvedDoctor();
        $patient = User::factory()->create(['full_name' => 'Budi Pasien']);
        $patient->assignRole('user');

        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);
        SkincareProduct::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'name' => 'Serum',
            'category' => 'Serum',
            'usage_instruction' => 'Night',
        ]);
        SkinRecommendation::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'title' => 'Rutinitas',
            'recommendation_text' => 'Text',
            'priority_level' => 'high',
        ]);
        DoctorRating::create(['user_id' => $patient->id, 'doctor_id' => $doctor->id, 'rating' => 5]);

        $conversation = Conversation::create([
            'user_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
        $conversation->messages()->create([
            'sender_id' => $patient->id,
            'content' => 'Dok, tolong lihat hasil scan saya dong',
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/v1/doctor/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'verification_status',
                    'stats' => [
                        'total_patients',
                        'conversations_awaiting_reply',
                        'my_products',
                        'my_recommendations',
                        'average_rating',
                        'total_ratings',
                    ],
                    'recent_conversations',
                ],
            ]);

        $this->assertEquals('approved', $response->json('data.verification_status'));
        $this->assertEquals(1, $response->json('data.stats.total_patients'));
        $this->assertEquals(1, $response->json('data.stats.conversations_awaiting_reply'));
        $this->assertEquals(1, $response->json('data.stats.my_products'));
        $this->assertEquals(1, $response->json('data.stats.my_recommendations'));
        $this->assertEquals(5.0, $response->json('data.stats.average_rating'));
        $this->assertEquals(1, $response->json('data.stats.total_ratings'));

        $recent = $response->json('data.recent_conversations.0');
        $this->assertEquals($conversation->uuid, $recent['uuid']);
        $this->assertEquals('Budi Pasien', $recent['user']['full_name']);
        $this->assertEquals('Dok, tolong lihat hasil scan saya dong', $recent['last_message']['content']);
        $this->assertEquals('user', $recent['last_message']['sender_role']);
    }

    public function test_doctor_dashboard_awaiting_reply_zero_when_doctor_replied_last(): void
    {
        $doctor = $this->approvedDoctor();
        $patient = User::factory()->create();
        $patient->assignRole('user');

        $conversation = Conversation::create([
            'user_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
        $conversation->messages()->create([
            'sender_id' => $patient->id,
            'content' => 'Halo dok',
        ]);
        $conversation->messages()->create([
            'sender_id' => $doctor->id,
            'content' => 'Halo, ada yang bisa dibantu?',
        ]);

        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/doctor/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.conversations_awaiting_reply', 0);
    }
}
