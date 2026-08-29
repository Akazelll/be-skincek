<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\PredictionHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneDeletedUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_prune_removes_all_user_related_data(): void
    {
        $user = User::factory()->create();
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $history = PredictionHistory::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'scan_mode' => 'upload',
            'predicted_class' => 'acne',
            'confidence' => 0.9,
            'probabilities' => ['acne' => 0.9],
            'severity_score' => 50,
            'severity_level' => 'medium',
            'model_used' => 'test-model',
        ]);
        $history->addMediaFromString('fake-image-bytes')->usingFileName('face.jpg')->toMediaCollection('scan-photo');

        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);
        $message = Message::create(['conversation_id' => $conversation->id, 'sender_id' => $user->id, 'content' => 'hai']);
        $message->addMediaFromString('fake-image-bytes')->usingFileName('chat.jpg')->toMediaCollection('chat-media');

        $user->subscriptions()->create([
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'category' => 'welcome',
            'title' => 'Halo',
            'message' => 'Isi',
        ]);
        $user->createToken('auth_token');
        $user->deviceTokens()->create(['fcm_token' => 'fcm-token', 'platform' => 'android']);

        $user->forceFill(['deleted_at' => now()->subDays(31)])->save();

        Storage::disk('local')->put("exports/{$user->uuid}/data.json", '{}');

        $this->artisan('users:prune')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('prediction_histories', ['id' => $history->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('media', ['model_id' => $message->id]);
        Storage::disk('local')->assertMissing("exports/{$user->uuid}/data.json");
    }

    public function test_prune_skips_users_within_grace_period(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['deleted_at' => now()->subDays(5)])->save();

        $this->artisan('users:prune')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
