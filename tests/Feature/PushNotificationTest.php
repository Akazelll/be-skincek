<?php

namespace Tests\Feature;

use App\Contracts\PushNotificationServiceContract;
use App\Enums\VerificationStatus;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\User;
use App\Services\FcmPushNotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeVerifiedDoctor(): User
    {
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => VerificationStatus::APPROVED,
        ]);

        return $doctor;
    }

    private function fakePushRecorder(): object
    {
        $fake = new class implements PushNotificationServiceContract
        {
            public array $calls = [];

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                $this->calls[] = compact('user', 'title', 'body', 'data');
            }
        };

        $this->app->instance(PushNotificationServiceContract::class, $fake);

        return $fake;
    }

    public function test_chat_message_from_doctor_pushes_to_user(): void
    {
        $fake = $this->fakePushRecorder();
        $user = User::factory()->create();
        $user->assignRole('user');
        $user->deviceTokens()->create(['fcm_token' => 'tok-user', 'platform' => 'android']);
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($doctor);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Coba toner asam salisilat'])
            ->assertCreated();

        $this->assertCount(1, $fake->calls);
        $this->assertSame($user->id, $fake->calls[0]['user']->id);
        $this->assertSame('Pesan baru', $fake->calls[0]['title']);
        $this->assertSame('Coba toner asam salisilat', $fake->calls[0]['body']);
        $this->assertSame('chat_message_received', $fake->calls[0]['data']['type']);
        $this->assertSame($conversation->uuid, $fake->calls[0]['data']['conversation_uuid']);
    }

    public function test_chat_message_from_user_pushes_to_doctor(): void
    {
        $fake = $this->fakePushRecorder();
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        $doctor->deviceTokens()->create(['fcm_token' => 'tok-doctor', 'platform' => 'android']);
        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Dok, kulit saya breakout'])
            ->assertCreated();

        $this->assertCount(1, $fake->calls);
        $this->assertSame($doctor->id, $fake->calls[0]['user']->id);
        $this->assertSame('chat_message_received', $fake->calls[0]['data']['type']);
    }

    public function test_verification_review_pushes_to_doctor(): void
    {
        $fake = $this->fakePushRecorder();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $doctor->deviceTokens()->create(['fcm_token' => 'tok-doctor', 'platform' => 'ios']);
        $verification = DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => VerificationStatus::PENDING,
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/doctor-verifications/{$verification->uuid}/review", ['status' => 'approved'])
            ->assertOk();

        $this->assertCount(1, $fake->calls);
        $this->assertSame($doctor->id, $fake->calls[0]['user']->id);
        $this->assertSame('Status verifikasi dokter', $fake->calls[0]['title']);
        $this->assertStringContainsString('disetujui', $fake->calls[0]['body']);
        $this->assertSame('doctor_verification_reviewed', $fake->calls[0]['data']['type']);
        $this->assertSame('approved', $fake->calls[0]['data']['verification_status']);
    }

    public function test_push_service_sends_http_v1_message_to_every_token(): void
    {
        config()->set('services.fcm', [
            'enabled' => true,
            'project_id' => 'test-project',
            'credentials_json' => '{}',
            'api_url' => 'https://fcm.googleapis.com/v1/projects/%s/messages:send',
        ]);
        Http::fake();

        $service = \Mockery::mock(FcmPushNotificationService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('accessToken')->andReturn('fake-access-token');
        $this->app->instance(PushNotificationServiceContract::class, $service);

        $user = User::factory()->create();
        $user->deviceTokens()->create(['fcm_token' => 'tok-1', 'platform' => 'android']);
        $user->deviceTokens()->create(['fcm_token' => 'tok-2', 'platform' => 'ios']);

        $service->sendToUser($user, 'Judul', 'Isi', ['conversation_uuid' => 'conv-1', 'count' => 2]);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), 'fcm.googleapis.com/v1/projects/test-project/messages:send')
                && $request->hasHeader('Authorization', 'Bearer fake-access-token')
                && $payload['message']['notification']['title'] === 'Judul'
                && $payload['message']['notification']['body'] === 'Isi'
                && in_array($payload['message']['token'], ['tok-1', 'tok-2'], true)
                && $payload['message']['data']['conversation_uuid'] === 'conv-1'
                && $payload['message']['data']['count'] === '2';
        });
    }

    public function test_push_service_is_disabled_when_not_configured(): void
    {
        config()->set('services.fcm.enabled', false);
        Http::fake();

        $service = $this->app->make(PushNotificationServiceContract::class);
        $user = User::factory()->create();
        $user->deviceTokens()->create(['fcm_token' => 'tok-1', 'platform' => 'android']);

        $service->sendToUser($user, 'Judul', 'Isi');

        Http::assertNothingSent();
    }
}
