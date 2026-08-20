<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\User;
use App\Notifications\AppNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_list_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new AppNotification('Pembayaran berhasil', 'Langganan aktif.'));

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pembayaran berhasil')
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new AppNotification('Halo', 'Isi notifikasi'));
        $notification = $user->notifications()->first();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.read_at', $notification->fresh()->read_at?->toISOString());

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new AppNotification('Satu'));
        $user->notify(new AppNotification('Dua'));

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Semua notifikasi telah dibaca');

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_user_cannot_read_other_users_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new AppNotification('Rahasia'));
        $notification = $owner->notifications()->first();

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
    }

    public function test_chat_message_creates_notification_for_recipient(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => VerificationStatus::APPROVED,
        ]);
        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'halo dokter'])
            ->assertCreated();

        Notification::assertSentTo(
            $doctor,
            AppNotification::class,
            fn (AppNotification $notification) => $notification->body === 'halo dokter'
        );
    }
}
