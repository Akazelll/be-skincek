<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\Conversation;
use App\Models\DoctorRating;
use App\Models\DoctorVerification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorRatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeVerifiedDoctor(): User
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => VerificationStatus::APPROVED,
        ]);

        return $doctor;
    }

    public function test_user_can_rate_doctor_after_chatting(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/doctors/{$doctor->uuid}/ratings", [
            'rating' => 5,
            'review' => 'Dokter sangat membantu',
        ])->assertCreated()
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('doctor_ratings', [
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'rating' => 5,
        ]);
    }

    public function test_user_cannot_rate_doctor_without_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/doctors/{$doctor->uuid}/ratings", ['rating' => 5])
            ->assertUnprocessable();

        $this->assertDatabaseCount('doctor_ratings', 0);
    }

    public function test_user_can_update_their_rating(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);
        DoctorRating::create(['user_id' => $user->id, 'doctor_id' => $doctor->id, 'rating' => 3]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/doctors/{$doctor->uuid}/ratings", ['rating' => 4])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4);

        $this->assertDatabaseCount('doctor_ratings', 1);
    }

    public function test_doctor_list_includes_rating_aggregate(): void
    {
        $doctor = $this->makeVerifiedDoctor();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        DoctorRating::create(['user_id' => $userA->id, 'doctor_id' => $doctor->id, 'rating' => 5]);
        DoctorRating::create(['user_id' => $userB->id, 'doctor_id' => $doctor->id, 'rating' => 3]);

        Sanctum::actingAs($userA);

        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonPath('data.0.rating_avg', 4)
            ->assertJsonPath('data.0.rating_count', 2);
    }

    public function test_doctor_rating_history_is_listed(): void
    {
        $doctor = $this->makeVerifiedDoctor();
        $userA = User::factory()->create();
        DoctorRating::create(['user_id' => $userA->id, 'doctor_id' => $doctor->id, 'rating' => 5, 'review' => 'Bagus']);

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/doctors/{$doctor->uuid}/ratings")
            ->assertOk()
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.review', 'Bagus');
    }
}
