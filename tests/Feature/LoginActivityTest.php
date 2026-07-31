<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_login_activity_and_identify_current_session(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $current->accessToken->forceFill([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
        ])->save();
        $user->createToken('other');

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/login-activity')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'uuid' => $current->accessToken->uuid,
                'device' => 'Chrome di Windows',
                'is_current' => true,
            ])
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.token');
    }

    public function test_user_can_revoke_specific_session_by_uuid(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $other = $user->createToken('other');

        $this->withToken($current->plainTextToken)
            ->deleteJson("/api/v1/login-activity/{$other->accessToken->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $foreign = User::factory()->create()->createToken('foreign');

        $this->withToken($current->plainTextToken)
            ->deleteJson("/api/v1/login-activity/{$foreign->accessToken->uuid}")
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }
}
