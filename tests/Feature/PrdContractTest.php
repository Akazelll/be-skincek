<?php

namespace Tests\Feature;

use App\Models\SkinConcern;
use App\Models\SkinType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrdContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_api_version_endpoint_returns_expected_contract(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_prd_section9_endpoints_are_available(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $this->getJson('/api/v1/profile')->assertUnauthorized();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/profile')->assertOk();
        $this->getJson('/api/v1/admin/verifications')->assertOk();
        $this->getJson('/api/v1/admin/users')->assertOk();

        Sanctum::actingAs($doctor);
        $this->getJson('/api/v1/doctor-verifications')->assertStatus(404);
        $this->post('/api/v1/doctor-verifications', [
            'str_number' => 'STR-2026-0001',
            'specialization' => 'Dermatologi',
            'documents' => [UploadedFile::fake()->image('sertif.png')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $doctor->unsetRelation('doctorVerification');
        $this->getJson('/api/v1/doctor-verifications')->assertOk();
    }

    public function test_doctor_must_be_verified_to_publish_products_and_recommendations(): void
    {
        $concern = SkinConcern::query()->first();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        Sanctum::actingAs($doctor);
        $this->postJson('/api/v1/skincare-products', [
            'concern_id' => $concern->id,
            'name' => 'Test Cream',
            'category' => 'Moisturizer',
            'usage_instruction' => 'Oles tipis.',
        ])->assertForbidden();
        $this->postJson('/api/v1/skin-recommendations', [
            'concern_id' => $concern->id,
            'title' => 'Rekomendasi',
            'description' => 'Test',
        ])->assertForbidden();

        $doctor->doctorVerification()->create([
            'specialization' => 'Dermatologi',
            'verification_status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $doctor->unsetRelation('doctorVerification');

        $this->postJson('/api/v1/skincare-products', [
            'concern_id' => $concern->id,
            'name' => 'Test Cream',
            'category' => 'Moisturizer',
            'usage_instruction' => 'Oles tipis.',
        ])->assertCreated();
    }

    public function test_catalog_is_public_without_authentication(): void
    {
        $this->getJson('/api/v1/skincare-products')->assertOk();
        $this->getJson('/api/v1/skin-recommendations')->assertOk();
        $this->getJson('/api/v1/skin-concerns')->assertOk();
        $this->getJson('/api/v1/skin-types')->assertOk();
    }

    public function test_admin_manages_skin_concerns_and_types_on_prd_paths(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $user = User::factory()->create();
        $user->assignRole('user');

        // Skin concerns: read-only for everyone, doctor can update description only
        Sanctum::actingAs($doctor);
        $this->postJson('/api/v1/skin-concerns', ['name' => 'Jerawat', 'ml_label' => 'acne'])->assertStatus(405);
        $concern = SkinConcern::first();
        $this->patchJson("/api/v1/skin-concerns/{$concern->uuid}", ['description' => 'Deskripsi baru dari dokter'])->assertOk();
        $this->patchJson("/api/v1/skin-concerns/{$concern->uuid}", ['name' => 'Edit nama'])->assertUnprocessable();
        $this->deleteJson("/api/v1/skin-concerns/{$concern->uuid}")->assertStatus(405);

        // Skin types: doctor can CRUD
        Sanctum::actingAs($doctor);
        $type = $this->postJson('/api/v1/skin-types', ['name' => 'Kulit Berminyak'])->assertCreated()->json('data');
        $this->patchJson("/api/v1/skin-types/{$type['uuid']}", ['name' => 'Kulit Berminyak Updated'])->assertOk();
        $this->deleteJson("/api/v1/skin-types/{$type['uuid']}")->assertOk();

        // User cannot manage skin types
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/skin-types', ['name' => 'Test'])->assertForbidden();
    }

    public function test_public_catalog_only_lists_active_concerns_and_types(): void
    {
        SkinConcern::create(['name' => 'Eksim', 'ml_label' => 'eczema', 'is_active' => false]);
        SkinType::create(['name' => 'Kering Test', 'is_active' => false]);

        $this->getJson('/api/v1/skin-concerns')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Eksim']);
        $this->getJson('/api/v1/skin-types')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Kering Test']);
    }

    public function test_account_deletion_right_to_erasure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/profile')->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
