<?php

namespace Tests\Feature;

use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::SUCCESS,
            'category' => NotificationCategory::SUBSCRIPTION_ACTIVE,
            'title' => 'Pembayaran berhasil',
            'message' => 'Langganan aktif.',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pembayaran berhasil')
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::INFO,
            'category' => NotificationCategory::WELCOME,
            'title' => 'Halo',
            'message' => 'Isi notifikasi',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::INFO,
            'category' => NotificationCategory::WELCOME,
            'title' => 'Satu',
            'message' => 'Pesan satu',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::INFO,
            'category' => NotificationCategory::WELCOME,
            'title' => 'Dua',
            'message' => 'Pesan dua',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'Semua notifikasi ditandai sebagai dibaca.');

        $this->assertEquals(0, Notification::forUser($user->id)->unread()->count());
    }

    public function test_user_cannot_read_other_users_notification(): void
    {
        $owner = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $owner->id,
            'type' => NotificationType::INFO,
            'category' => NotificationCategory::WELCOME,
            'title' => 'Rahasia',
            'message' => 'Isi rahasia',
        ]);

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
    }

    public function test_unread_count_endpoint(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::INFO,
            'category' => NotificationCategory::WELCOME,
            'title' => 'Notif 1',
            'message' => 'Pesan 1',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::INFO,
            'category' => NotificationCategory::WELCOME,
            'title' => 'Notif 2',
            'message' => 'Pesan 2',
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }
}
