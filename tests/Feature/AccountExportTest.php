<?php

namespace Tests\Feature;

use App\Models\PredictionHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    public function test_user_can_request_data_export(): void
    {
        $user = User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ]);
        $user->subscriptions()->create([
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);
        PredictionHistory::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'scan_mode' => 'upload',
            'predicted_class' => 'acne',
            'confidence' => 0.91,
            'probabilities' => ['acne' => 0.91],
            'severity_score' => 73,
            'severity_level' => 'high',
            'model_used' => 'test-model',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/export')
            ->assertOk()
            ->assertJsonStructure(['data' => ['download_url', 'expires_in_minutes']]);

        Storage::disk('local')->assertExists("exports/{$user->uuid}");
    }

    public function test_export_download_requires_valid_signature(): void
    {
        $user = User::factory()->create();
        Storage::disk('local')->put("exports/{$user->uuid}/data.json", '{}');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile/exports/download?file=exports/'.$user->uuid.'/data.json')
            ->assertForbidden();
    }

    public function test_export_download_rejects_path_traversal(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile/exports/download?file=exports/../../.env')
            ->assertForbidden();
    }

    public function test_account_deletion_soft_deletes_user(): void
    {
        $user = User::factory()->create();
        $user->createToken('auth_token');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Akun berhasil dihapus');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
