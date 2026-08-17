<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_register_with_privacy_consent(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'privacy_consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.full_name', 'Test User')
            ->assertJsonPath('data.user.email', 'user@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $user = User::where('email', 'user@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('user'));
        $this->assertNotNull($user->privacy_consent_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_requires_privacy_consent(): void
    {
        $this->postJson('/api/v1/register', [
            'full_name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'privacy_consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('privacy_consent');
    }

    public function test_active_user_can_login_and_logout(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->withToken($login->json('data.token'))
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_doctor_can_register_with_verification(): void
    {
        $response = $this->postJson('/api/v1/register-doctor', [
            'full_name' => 'dr. Test',
            'email' => 'doctor@example.com',
            'password' => 'password123',
            'str_number' => '12345',
            'specialization' => 'Dermatologi',
            'title' => 'dr. Sp.DV',
            'sub_specialization' => 'Kulit dan Kelamin',
            'experience_years' => 5,
            'alma_mater' => 'Universitas Indonesia',
            'practice_locations' => ['RS Cipto', 'Klinik Sehat'],
            'professional_organizations' => ['IDI', 'PERDOSKI'],
            'documents' => [
                UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf'),
            ],
            'privacy_consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.full_name', 'dr. Test')
            ->assertJsonPath('data.user.email', 'doctor@example.com')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $user = User::where('email', 'doctor@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('doctor'));
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->doctorVerification);
        $this->assertSame('pending', $user->doctorVerification->verification_status->value);
        $this->assertSame('Dermatologi', $user->doctorVerification->specialization);
        $this->assertSame('dr. Sp.DV', $user->doctorVerification->title);
        $this->assertSame(5, $user->doctorVerification->experience_years);
        $this->assertSame(['RS Cipto', 'Klinik Sehat'], $user->doctorVerification->practice_locations);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_doctor_registration_requires_documents(): void
    {
        $this->postJson('/api/v1/register-doctor', [
            'full_name' => 'dr. Test',
            'email' => 'doctor@example.com',
            'password' => 'password123',
            'specialization' => 'Dermatologi',
            'privacy_consent' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('documents');
    }

    public function test_doctor_registration_requires_privacy_consent(): void
    {
        $this->postJson('/api/v1/register-doctor', [
            'full_name' => 'dr. Test',
            'email' => 'doctor@example.com',
            'password' => 'password123',
            'specialization' => 'Dermatologi',
            'documents' => [
                UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf'),
            ],
            'privacy_consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('privacy_consent');
    }

    public function test_doctor_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/register-doctor', [
            'full_name' => 'dr. Test',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'specialization' => 'Dermatologi',
            'documents' => [
                UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf'),
            ],
            'privacy_consent' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_doctor_can_login_after_registration(): void
    {
        $this->postJson('/api/v1/register-doctor', [
            'full_name' => 'dr. Test',
            'email' => 'doctor@example.com',
            'password' => 'password123',
            'specialization' => 'Dermatologi',
            'documents' => [
                UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf'),
            ],
            'privacy_consent' => true,
        ])->assertCreated();

        $login = $this->postJson('/api/v1/login', [
            'email' => 'doctor@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/doctor-verifications')
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'pending');
    }
}
