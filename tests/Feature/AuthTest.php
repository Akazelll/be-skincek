<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_register_with_privacy_consent(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'privacy_consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.full_name', 'Test User')
            ->assertJsonPath('data.user.email', 'user@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $user = User::where('email', 'user@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('user'));
        $this->assertNotNull($user->privacy_consent_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_requires_privacy_consent(): void
    {
        $this->postJson('/api/v1/register', [
            'full_name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'privacy_consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('privacy_consent');
    }

    public function test_active_user_can_login_and_logout(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->withToken($login->json('data.token'))
            ->postJson('/api/v1/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }
}
