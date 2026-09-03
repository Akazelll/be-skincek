<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $payload = [
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'role' => 'user',
        ];

        $this->postJson('/api/v1/admin/users', $payload)
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Budi Santoso')
            ->assertJsonPath('data.role', 'user');

        $user = User::where('email', 'budi@example.com')->first();
        $this->assertTrue($user->hasRole('user'));
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_admin_create_user_rejects_duplicate_email(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $existing = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/users', [
            'full_name' => 'Duplikat',
            'email' => $existing->email,
            'password' => 'password123',
            'role' => 'user',
        ])->assertUnprocessable();
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create(['full_name' => 'Lama']);
        $user->assignRole('user');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$user->uuid}", [
            'full_name' => 'Nama Baru',
            'password' => 'newpassword123',
        ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Nama Baru');

        $user = $user->fresh();
        $this->assertSame('Nama Baru', $user->full_name);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_admin_update_user_email_must_be_unique(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$user->uuid}", [
            'email' => $other->email,
        ])->assertUnprocessable();
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('user');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/users/{$user->uuid}")
            ->assertOk()
            ->assertJsonPath('meta.message', 'User '.$user->full_name.' berhasil dihapus');

        $this->assertSoftDeleted($user);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/users/{$admin->uuid}")
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_create_or_update_or_delete_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $target = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/users', [
            'full_name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ])->assertForbidden();

        $this->patchJson("/api/v1/admin/users/{$target->uuid}", ['full_name' => 'Hacked'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/admin/users/{$target->uuid}")
            ->assertForbidden();
    }
}
