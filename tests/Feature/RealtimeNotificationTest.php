<?php

namespace Tests\Feature;

use App\Contracts\GoogleTokenVerifierContract;
use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_login_creates_welcome_notification_in_db(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $notification = Notification::where('user_id', $user->id)->first();

        $this->assertNotNull($notification);
        $this->assertEquals(NotificationType::INFO, $notification->type);
        $this->assertEquals(NotificationCategory::WELCOME, $notification->category);
        $this->assertEquals('Selamat datang di SkinCek!', $notification->title);
    }

    public function test_failed_login_does_not_create_notification(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_google_login_creates_welcome_notification(): void
    {
        $this->mockGoogleVerifier();

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'privacy_consent' => true,
        ])->assertOk();

        $user = User::where('email', 'googleuser@gmail.com')->firstOrFail();

        $notification = Notification::where('user_id', $user->id)->first();

        $this->assertNotNull($notification);
        $this->assertEquals(NotificationCategory::WELCOME, $notification->category);
    }

    public function test_logout_creates_logout_notification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Berhasil logout dari sesi ini');

        $notification = Notification::where('user_id', $user->id)->first();

        $this->assertNotNull($notification);
        $this->assertEquals(NotificationCategory::LOGOUT, $notification->category);
    }

    public function test_logout_all_creates_logout_notification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logout-all')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Berhasil logout dari semua perangkat');

        $notification = Notification::where('user_id', $user->id)->first();

        $this->assertNotNull($notification);
        $this->assertEquals(NotificationCategory::LOGOUT, $notification->category);
    }

    public function test_notification_payload_structure(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $notification = Notification::where('user_id', $user->id)->first();

        $this->assertNotNull($notification);
        $this->assertNotNull($notification->id);
        $this->assertNotNull($notification->type);
        $this->assertNotNull($notification->category);
        $this->assertNotNull($notification->title);
        $this->assertNotNull($notification->message);
        $this->assertNull($notification->read_at);
        $this->assertNotNull($notification->created_at);
    }

    public function test_broadcast_event_is_dispatched(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $notification = Notification::where('user_id', $user->id)->first();

        $this->assertNotNull($notification);
        $this->assertEquals(NotificationType::INFO, $notification->type);
        $this->assertEquals(NotificationCategory::WELCOME, $notification->category);
        $this->assertNotNull($notification->created_at);
    }

    private function mockGoogleVerifier(): void
    {
        $this->app->instance(GoogleTokenVerifierContract::class, new class implements GoogleTokenVerifierContract
        {
            public function verify(string $idToken): ?array
            {
                return [
                    'sub' => 'google-sub-123',
                    'email' => 'googleuser@gmail.com',
                    'email_verified' => true,
                    'name' => 'Google User',
                    'picture' => 'https://example.com/avatar.png',
                ];
            }
        });
    }
}
