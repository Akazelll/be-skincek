<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
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

    private function sendMessage(User $sender, Conversation $conversation, string $content)
    {
        Sanctum::actingAs($sender);

        return $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [
            'content' => $content,
        ]);
    }

    public function test_user_can_start_conversation_with_verified_doctor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/conversations', ['doctor_id' => $doctor->uuid])
            ->assertCreated()
            ->assertJsonPath('data.doctor.uuid', $doctor->uuid)
            ->assertJsonPath('data.message_count', 0);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_only_one_thread_exists_per_user_doctor_pair(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/conversations', ['doctor_id' => $doctor->uuid]);
        $second = $this->postJson('/api/v1/conversations', ['doctor_id' => $doctor->uuid]);

        $this->assertEquals(
            $first->json('data.uuid'),
            $second->json('data.uuid')
        );
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_user_cannot_start_conversation_with_unverified_doctor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/conversations', ['doctor_id' => $doctor->uuid])
            ->assertUnprocessable();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_user_can_send_first_three_messages_for_free(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
        ]);

        foreach (['pesan 1', 'pesan 2', 'pesan 3'] as $content) {
            $this->sendMessage($user, $conversation, $content)->assertCreated();
        }

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'message_count' => 3,
        ]);
    }

    public function test_fourth_user_message_is_blocked_without_subscription(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'message_count' => 3,
        ]);

        $this->sendMessage($user, $conversation, 'pesan keempat')
            ->assertStatus(402);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_doctor_replies_are_never_blocked(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'message_count' => 3,
        ]);

        $this->sendMessage($doctor, $conversation, 'balasan dokter')
            ->assertCreated();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'message_count' => 3,
        ]);
    }

    public function test_active_subscription_unlocks_chat_paywall(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'message_count' => 3,
        ]);

        $this->sendMessage($user, $conversation, 'pesan lanjutan')
            ->assertCreated();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'message_count' => 4,
        ]);
    }

    public function test_message_history_stays_readable_after_paywall(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'message_count' => 3,
        ]);
        Message::create(['conversation_id' => $conversation->id, 'sender_id' => $user->id, 'content' => 'pesan lama']);
        Message::create(['conversation_id' => $conversation->id, 'sender_id' => $doctor->id, 'content' => 'balasan dokter']);

        $this->sendMessage($user, $conversation, 'ditolak paywall')->assertStatus(402);

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_non_participant_cannot_access_conversation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
        ]);

        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages")
            ->assertNotFound();
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'hack'])
            ->assertNotFound();
    }

    public function test_doctor_can_list_their_conversations(): void
    {
        $doctor = $this->makeVerifiedDoctor();
        $userA = User::factory()->create();
        $userA->assignRole('user');
        $userB = User::factory()->create();
        $userB->assignRole('user');
        Conversation::create(['user_id' => $userA->id, 'doctor_id' => $doctor->id]);
        Conversation::create(['user_id' => $userB->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_message_content_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = $this->makeVerifiedDoctor();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->sendMessage($user, $conversation, 'rahasia kulitku')->assertCreated();

        $stored = DB::table('messages')->first()->content;
        $this->assertNotSame('rahasia kulitku', $stored);
    }
}
