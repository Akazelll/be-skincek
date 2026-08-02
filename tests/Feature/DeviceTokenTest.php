<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_a_device_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/device-tokens', [
            'fcm_token' => 'fcm-token-abc-123',
            'platform' => 'android',
        ])
            ->assertCreated()
            ->assertJsonPath('data.fcm_token', 'fcm-token-abc-123')
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'fcm_token' => 'fcm-token-abc-123',
            'platform' => 'android',
        ]);
    }

    public function test_registering_same_token_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/device-tokens', [
            'fcm_token' => 'same-token',
            'platform' => 'android',
        ])->assertCreated();

        $this->postJson('/api/v1/device-tokens', [
            'fcm_token' => 'same-token',
            'platform' => 'web',
        ])->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'fcm_token' => 'same-token',
            'platform' => 'web',
        ]);
    }

    public function test_user_can_remove_own_device_token(): void
    {
        $user = User::factory()->create();
        $token = $user->deviceTokens()->create([
            'fcm_token' => 'remove-me',
            'platform' => 'ios',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/device-tokens/{$token->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['id' => $token->id]);
    }

    public function test_user_cannot_remove_another_users_device_token(): void
    {
        $other = User::factory()->create();
        $token = $other->deviceTokens()->create([
            'fcm_token' => 'foreign',
            'platform' => 'ios',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/device-tokens/{$token->uuid}")
            ->assertNotFound();

        $this->assertDatabaseHas('device_tokens', ['id' => $token->id]);
    }

    public function test_registering_token_requires_valid_platform(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/device-tokens', [
            'fcm_token' => 'fcm-token',
            'platform' => 'windows-phone',
        ])
            ->assertUnprocessable();
    }
}
