<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.full_name', $user->full_name)
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.subscription_status', 'Free')
            ->assertJsonPath('data.user_messages_count', 0)
            ->assertJsonPath('data.remaining_free_messages', 3);

        $this->patchJson('/api/v1/profile', ['full_name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'full_name' => 'Updated Name']);
    }

    public function test_user_can_update_profile_with_demographics(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ])
            ->assertOk()
            ->assertJsonPath('data.date_of_birth', '1995-05-15')
            ->assertJsonPath('data.gender', 'perempuan')
            ->assertJsonPath('data.profile_completed', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'gender' => 'perempuan',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'date_of_birth' => '1995-05-15 00:00:00',
        ]);
    }

    public function test_profile_show_includes_demographics_and_completion_flag(): void
    {
        $user = User::factory()->create([
            'date_of_birth' => '1990-01-01',
            'gender' => 'laki_laki',
        ]);
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.date_of_birth', '1990-01-01')
            ->assertJsonPath('data.gender', 'laki_laki')
            ->assertJsonPath('data.profile_completed', true);
    }

    public function test_gender_validation_rejects_invalid_values(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'gender' => 'alien',
        ])->assertUnprocessable();
    }

    public function test_date_of_birth_cannot_be_in_future(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'date_of_birth' => now()->addDay()->format('Y-m-d'),
        ])->assertUnprocessable();
    }

    public function test_user_can_soft_delete_account_and_revoke_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/v1/profile')
            ->assertOk();

        $this->assertSoftDeleted($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_upload_avatar(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->patch('/api/v1/profile', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.avatar_url', fn ($url) => str_contains($url, '/storage/'));

        $this->assertDatabaseCount('media', 1);
        $this->assertNotNull($user->fresh()->avatar_updated_at);
        Storage::disk('public')->assertExists($user->getFirstMedia('avatar')->getPathRelativeToRoot());
    }

    public function test_avatar_upload_rate_limited_once_per_day(): void
    {
        $user = User::factory()->create(['avatar_updated_at' => now()]);
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->patch('/api/v1/profile', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_avatar_can_be_changed_after_24_hours(): void
    {
        $user = User::factory()->create(['avatar_updated_at' => now()->subDay()]);
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->patch('/api/v1/profile', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertDatabaseCount('media', 1);
    }

    public function test_user_can_delete_avatar_without_rate_limit(): void
    {
        $user = User::factory()->create(['avatar_updated_at' => now()]);
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $user->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('avatar');
        $this->assertDatabaseCount('media', 1);

        $this->deleteJson('/api/v1/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $this->assertDatabaseCount('media', 0);
        $this->assertNull($user->fresh()->avatar_updated_at);
    }

    public function test_avatar_can_be_uploaded_again_after_deletion(): void
    {
        $user = User::factory()->create(['avatar_updated_at' => now()]);
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/profile/avatar')->assertOk();

        $this->patch('/api/v1/profile', [
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertDatabaseCount('media', 1);
    }

    public function test_google_avatar_url_is_fallback_when_no_media(): void
    {
        $user = User::factory()->create([
            'google_avatar_url' => 'https://example.com/google-photo.jpg',
        ]);
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', 'https://example.com/google-photo.jpg');

        $this->patch('/api/v1/profile', [
            'avatar' => UploadedFile::fake()->image('custom.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.avatar_url', fn ($url) => str_contains($url, '/storage/'));

        $this->deleteJson('/api/v1/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', 'https://example.com/google-photo.jpg');
    }
}
