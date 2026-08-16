<?php

namespace Tests\Feature;

use App\Events\DoctorVerificationReviewed;
use App\Models\DoctorVerification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_doctor_can_submit_verification_with_documents(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/doctor-verifications', [
            'str_number' => 'STR123456',
            'specialization' => 'Dermatology',
            'documents' => [UploadedFile::fake()->image('license.jpg')],
        ])->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending');

        $this->assertDatabaseHas('doctor_verifications', [
            'doctor_id' => $doctor->id,
            'str_number' => 'STR123456',
        ]);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_doctor_can_submit_profile_fields_with_verification(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/doctor-verifications', [
            'str_number' => 'STR999',
            'specialization' => 'Dermatology',
            'title' => 'dr. Sp.PD',
            'sub_specialization' => 'Kulit & Kelamin',
            'experience_years' => 8,
            'alma_mater' => 'Universitas Indonesia',
            'practice_locations' => ['RS Cipto', 'Klinik Sehat'],
            'professional_organizations' => ['IDI', 'PERDOSKI'],
            'documents' => [UploadedFile::fake()->image('license.jpg')],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'dr. Sp.PD')
            ->assertJsonPath('data.sub_specialization', 'Kulit & Kelamin')
            ->assertJsonPath('data.experience_years', 8)
            ->assertJsonPath('data.alma_mater', 'Universitas Indonesia')
            ->assertJsonPath('data.practice_locations', ['RS Cipto', 'Klinik Sehat'])
            ->assertJsonPath('data.professional_organizations', ['IDI', 'PERDOSKI']);

        $this->assertDatabaseHas('doctor_verifications', [
            'doctor_id' => $doctor->id,
            'title' => 'dr. Sp.PD',
            'experience_years' => 8,
        ]);
    }

    public function test_doctor_cannot_submit_twice_while_pending(): void
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => 'pending',
            'uuid' => Str::uuid(),
        ]);
        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/doctor-verifications', [
            'specialization' => 'Dermatology',
        ])->assertUnprocessable();
    }

    public function test_admin_can_approve_verification(): void
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
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'approved');

        $this->assertNotNull($verification->fresh()->reviewed_at);
        $this->assertEquals($admin->id, $verification->fresh()->reviewed_by);
    }

    public function test_admin_can_view_verification_detail(): void
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

        $this->getJson("/api/v1/admin/verifications/{$verification->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', (string) $verification->uuid)
            ->assertJsonPath('data.specialization', 'Dermatology')
            ->assertJsonPath('data.doctor.uuid', $doctor->uuid);
    }

    public function test_admin_can_request_revision_and_doctor_can_resubmit(): void
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
            'status' => 'needs_revision',
            'revision_note' => 'Foto tidak jelas',
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'needs_revision')
            ->assertJsonPath('data.revision_note', 'Foto tidak jelas');

        Sanctum::actingAs($doctor);
        $this->postJson("/api/v1/doctor-verifications/{$verification->uuid}/resubmit", [
            'specialization' => 'Dermatology',
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_admin_cannot_review_already_processed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $verification = DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => 'approved',
            'uuid' => Str::uuid(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/doctor-verifications/{$verification->uuid}/review", [
            'status' => 'rejected',
            'rejection_reason' => 'Invalid',
        ])->assertUnprocessable();
    }

    public function test_non_doctor_cannot_access_verification_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/doctor-verifications', [
            'specialization' => 'Dermatology',
        ])->assertForbidden();
    }

    public function test_admin_review_broadcasts_event_to_doctor_channel(): void
    {
        Event::fake([DoctorVerificationReviewed::class]);

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

        Event::assertDispatched(DoctorVerificationReviewed::class, function (DoctorVerificationReviewed $event) use ($doctor) {
            return $event->broadcastOn()[0] instanceof PrivateChannel
                && $event->broadcastOn()[0]->name === 'private-user.'.$doctor->uuid
                && $event->broadcastAs() === 'doctor_verification_reviewed'
                && $event->broadcastWith()['verification_status'] === 'approved';
        });
    }
}
