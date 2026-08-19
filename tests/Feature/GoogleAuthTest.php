<?php

namespace Tests\Feature;

use App\Contracts\GoogleTokenVerifierContract;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_google_token_creates_user_with_consent(): void
    {
        $this->fakeVerifier();

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'privacy_consent' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'google@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame('google-subject', $user->google_id);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_new_google_user_requires_privacy_consent(): void
    {
        $this->fakeVerifier();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_login_safely_links_existing_email(): void
    {
        $user = User::factory()->create(['email' => 'google@example.com', 'google_id' => null]);
        $this->fakeVerifier();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
            ->assertOk();

        $this->assertSame('google-subject', $user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_google_token_is_rejected(): void
    {
        $this->app->instance(GoogleTokenVerifierContract::class, new class implements GoogleTokenVerifierContract
        {
            public function verify(string $idToken): ?array
            {
                return null;
            }
        });

        $this->postJson('/api/v1/auth/google', ['id_token' => 'invalid'])
            ->assertUnauthorized();
    }

    public function test_inactive_google_user_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'google@example.com',
            'google_id' => 'google-subject',
            'is_active' => false,
        ]);
        $this->fakeVerifier();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
            ->assertUnauthorized();
    }

    public function test_google_auth_is_rate_limited(): void
    {
        $this->fakeVerifier();
        User::factory()->create(['email' => 'google@example.com', 'google_id' => 'google-subject']);

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])->assertOk();
        }

        $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
            ->assertTooManyRequests();
    }

    public function test_google_avatar_picture_is_saved_and_returned(): void
    {
        $this->fakeVerifier();

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'privacy_consent' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', 'https://example.com/photo.jpg');

        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame('https://example.com/photo.jpg', $user->google_avatar_url);
    }

    private function fakeVerifier(): void
    {
        $this->app->instance(GoogleTokenVerifierContract::class, new class implements GoogleTokenVerifierContract
        {
            public function verify(string $idToken): ?array
            {
                return [
                    'sub' => 'google-subject',
                    'aud' => 'web-client-id',
                    'email' => 'google@example.com',
                    'email_verified' => true,
                    'name' => 'Google User',
                    'picture' => 'https://example.com/photo.jpg',
                ];
            }
        });
    }
}
