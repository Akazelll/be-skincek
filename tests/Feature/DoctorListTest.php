<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\DoctorVerification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeVerifiedDoctor(array $profile = []): User
    {
        $doctor = User::factory()->create(['full_name' => 'dr. Test']);
        $doctor->assignRole('doctor');
        DoctorVerification::create(array_merge([
            'doctor_id' => $doctor->id,
            'str_number' => '12345',
            'specialization' => 'Dermatologi',
            'verification_status' => VerificationStatus::APPROVED,
        ], $profile));

        return $doctor;
    }

    public function test_doctor_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/doctors')->assertUnauthorized();
    }

    public function test_user_can_list_verified_doctors_only(): void
    {
        $this->makeVerifiedDoctor();

        $unverified = User::factory()->create(['full_name' => 'dr. Belum']);
        $unverified->assignRole('doctor');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'dr. Test')
            ->assertJsonPath('data.0.specialization', 'Dermatologi');
    }

    public function test_user_can_view_full_doctor_profile(): void
    {
        $doctor = $this->makeVerifiedDoctor([
            'title' => 'dr. Sp.PD',
            'sub_specialization' => 'Dermatologi Venereologi',
            'experience_years' => 10,
            'alma_mater' => 'Universitas Indonesia',
            'practice_locations' => ['RS Cipto', 'Klinik Sehat'],
            'professional_organizations' => ['IDI', 'PERDOSKI'],
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/doctors/{$doctor->uuid}")
            ->assertOk()
            ->assertJsonPath('data.full_name', 'dr. Test')
            ->assertJsonPath('data.title', 'dr. Sp.PD')
            ->assertJsonPath('data.specialization', 'Dermatologi')
            ->assertJsonPath('data.sub_specialization', 'Dermatologi Venereologi')
            ->assertJsonPath('data.experience_years', 10)
            ->assertJsonPath('data.alma_mater', 'Universitas Indonesia')
            ->assertJsonPath('data.practice_locations', ['RS Cipto', 'Klinik Sehat'])
            ->assertJsonPath('data.professional_organizations', ['IDI', 'PERDOSKI']);
    }

    public function test_unverified_doctor_profile_is_hidden(): void
    {
        $doctor = User::factory()->create(['full_name' => 'dr. Belum']);
        $doctor->assignRole('doctor');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/doctors/{$doctor->uuid}")->assertNotFound();
    }

    public function test_doctor_can_see_their_own_approved_profile(): void
    {
        $doctor = $this->makeVerifiedDoctor(['title' => 'dr. Sp.KK']);

        Sanctum::actingAs($doctor);

        $this->getJson("/api/v1/doctors/{$doctor->uuid}")
            ->assertOk()
            ->assertJsonPath('data.title', 'dr. Sp.KK');
    }

    public function test_per_page_accepts_only_5_10_20_50(): void
    {
        $this->makeVerifiedDoctor();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/doctors?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5);

        $this->getJson('/api/v1/doctors?per_page=20')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20);

        $this->getJson('/api/v1/doctors?per_page=7')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10);

        $this->getJson('/api/v1/doctors?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10);
    }
}
