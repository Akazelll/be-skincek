<?php

namespace Tests\Feature;

use App\Models\DoctorVerification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_admin_can_access_activity_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/activity-log')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_non_admin_cannot_access_activity_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/activity-log')->assertForbidden();
    }

    public function test_doctor_verification_review_records_activity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $verification = DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => 'pending',
            'uuid' => Str::uuid(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/doctor-verifications/{$verification->uuid}/review", [
            'status' => 'approved',
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'doctor_verification_review',
            'subject_id' => $verification->id,
            'causer_id' => $admin->id,
            'event' => null,
        ]);

        $this->getJson('/api/v1/admin/activity-log?log_name=doctor_verification_review')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_role_change_records_activity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('user');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$user->uuid}/role", ['role' => 'doctor'])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role_management',
            'subject_id' => $user->id,
            'causer_id' => $admin->id,
            'description' => 'Role changed to doctor',
        ]);
    }

    public function test_account_deletion_records_activity(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/profile')->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'account_deletion',
            'subject_id' => $user->id,
            'description' => 'Account deletion requested (soft delete)',
        ]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
