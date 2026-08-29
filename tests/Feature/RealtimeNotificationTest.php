<?php

namespace Tests\Feature;

use App\Contracts\GoogleTokenVerifierContract;
use App\Models\User;
use App\Notifications\AppNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_login_sends_welcome_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            AppNotification::class,
            fn (AppNotification $notification) => $notification->notificationType === 'welcome'
        );
    }

    public function test_failed_login_does_not_send_welcome_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        Notification::assertNothingSent();
    }

    public function test_google_login_sends_welcome_notification(): void
    {
        Notification::fake();

        $this->mockGoogleVerifier();

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'privacy_consent' => true,
        ])->assertOk();

        $user = User::where('email', 'googleuser@gmail.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            AppNotification::class,
            fn (AppNotification $notification) => $notification->notificationType === 'welcome'
        );
    }

    public function test_logout_sends_logout_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Berhasil logout dari sesi ini');

        Notification::assertSentTo(
            $user,
            AppNotification::class,
            fn (AppNotification $notification) => $notification->notificationType === 'logout'
        );
    }

    public function test_logout_all_sends_logout_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logout-all')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Berhasil logout dari semua perangkat');

        Notification::assertSentTo(
            $user,
            AppNotification::class,
            fn (AppNotification $notification) => $notification->notificationType === 'logout'
        );
    }

    public function test_notification_payload_contains_type_title_body_and_data(): void
    {
        $user = User::factory()->create();

        $user->notify(new AppNotification(
            'Hasil scan kamu sudah ready!',
            'Prediksi acne dengan tingkat keyakinan 91%.',
            ['prediction_id' => 'abc-123'],
            notificationType: 'scan',
        ));

        $notification = $user->notifications()->first();

        $this->assertEquals('scan', $notification->data['notification_type']);
        $this->assertEquals('Hasil scan kamu sudah ready!', $notification->data['title']);
        $this->assertEquals('Prediksi acne dengan tingkat keyakinan 91%.', $notification->data['body']);
        $this->assertEquals('abc-123', $notification->data['prediction_id']);
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
