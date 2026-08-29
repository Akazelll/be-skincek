<?php

namespace Tests\Feature;

use App\Models\DoctorVerification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_profile_returns_admin_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatologi',
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/profile')
            ->assertOk()
            ->assertJsonPath('data.uuid', $admin->uuid)
            ->assertJsonPath('data.full_name', $admin->full_name)
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.summary.total_users', 1)
            ->assertJsonPath('data.summary.total_doctors', 1)
            ->assertJsonPath('data.summary.pending_doctor_verifications', 1)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'full_name',
                    'email',
                    'role',
                    'avatar_url',
                    'email_verified',
                    'account_created_at',
                    'last_login' => ['at', 'ip_address', 'user_agent'],
                    'active_sessions',
                    'summary' => ['total_users', 'total_doctors', 'pending_doctor_verifications'],
                ],
            ]);
    }

    public function test_admin_profile_includes_last_login_session(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('admin-session');
        $token->accessToken->forceFill([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Test Browser)',
        ])->save();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/profile')
            ->assertOk()
            ->assertJsonPath('data.active_sessions', 1)
            ->assertJsonPath('data.last_login.ip_address', '127.0.0.1')
            ->assertJsonPath('data.last_login.user_agent', 'Mozilla/5.0 (Test Browser)')
            ->assertJsonPath('data.last_login.at', $token->accessToken->created_at->toISOString());
    }

    public function test_non_admin_cannot_access_admin_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/profile')->assertForbidden();

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/admin/profile')->assertForbidden();
    }
}
