<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_list_users_filtered_by_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        User::factory()->count(3)->create()->each(fn ($u) => $u->assignRole('user'));
        User::factory()->create()->assignRole('doctor');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/users?role=user')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_assign_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('user');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$user->uuid}/role", ['role' => 'doctor'])
            ->assertOk()
            ->assertJsonPath('data.role', 'doctor');

        $this->assertTrue($user->fresh()->hasRole('doctor'));
    }

    public function test_admin_can_view_user_detail_with_verification(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $doctor->doctorVerification()->create([
            'specialization' => 'Dermatology',
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/users/{$doctor->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $doctor->uuid)
            ->assertJsonPath('data.role', 'doctor')
            ->assertJsonPath('data.doctor_verification.specialization', 'Dermatology');
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$user->uuid}/role", ['role' => 'admin'])
            ->assertForbidden();
    }

    public function test_invalid_role_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$user->uuid}/role", ['role' => 'superadmin'])
            ->assertUnprocessable();
    }
}
