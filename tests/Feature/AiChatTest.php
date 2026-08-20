<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['ai.gemini.api_key' => 'test-gemini-key']);
    }

    private function fakeGemini(string $answer = 'Ini balasan Aura Skin.'): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $answer]]]],
                ],
            ], 200),
        ]);
    }

    private function makeBot(): User
    {
        $bot = User::factory()->create([
            'email' => 'aura@skincek.com',
            'full_name' => 'Aura Skin',
            'ai_bot' => true,
        ]);
        $bot->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $bot->id,
            'specialization' => 'AI Assistant',
            'verification_status' => VerificationStatus::APPROVED,
        ]);

        return $bot;
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function consent(User $user): void
    {
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/ai-chat/consent', ['accepted' => true])->assertOk();
    }

    private function startConversation(User $user, User $bot): Conversation
    {
        return Conversation::create(['user_id' => $user->id, 'doctor_id' => $bot->id]);
    }

    public function test_bot_chat_requires_consent(): void
    {
        $user = $this->makeUser();
        $bot = $this->makeBot();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ai-chat/conversations')
            ->assertStatus(403);

        $conversation = $this->startConversation($user, $bot);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Halo Aura'])
            ->assertStatus(403);
    }

    public function test_user_can_consent_and_see_consent_status(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/ai-chat/consent')
            ->assertOk()
            ->assertJsonPath('data.accepted', false);

        $this->postJson('/api/v1/ai-chat/consent', ['accepted' => true])->assertOk();

        $this->getJson('/api/v1/ai-chat/consent')
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonPath('data.text', config('ai.consent_text'));
    }

    public function test_user_can_revoke_consent(): void
    {
        $user = $this->makeUser();
        $this->consent($user);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/ai-chat/consent', ['accepted' => false])->assertOk();

        $this->getJson('/api/v1/ai-chat/consent')
            ->assertOk()
            ->assertJsonPath('data.accepted', false);
    }

    public function test_start_ai_conversation_returns_bot_conversation(): void
    {
        $user = $this->makeUser();
        $bot = $this->makeBot();
        $this->consent($user);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/ai-chat/conversations')
            ->assertCreated()
            ->assertJsonPath('data.doctor.uuid', $bot->uuid)
            ->assertJsonPath('data.doctor.full_name', 'Aura Skin');
    }

    public function test_ai_reply_is_generated_after_user_message(): void
    {
        $user = $this->makeUser();
        $bot = $this->makeBot();
        $this->fakeGemini('Cuci muka dua kali sehari itu penting.');
        $this->consent($user);

        $conversation = $this->startConversation($user, $bot);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Cara merawat kulit berminyak?'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $bot->id,
            'type' => 'text',
        ]);

        $this->assertSame(
            'Cuci muka dua kali sehari itu penting.',
            Message::where('conversation_id', $conversation->id)->where('sender_id', $bot->id)->first()->content
        );

        Http::assertSentCount(1);
    }

    public function test_unsafe_keyword_escalates_without_calling_gemini(): void
    {
        $user = $this->makeUser();
        $bot = $this->makeBot();
        $this->fakeGemini();
        $this->consent($user);

        $conversation = $this->startConversation($user, $bot);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Berapa dosis obat untuk jerawat saya?'])
            ->assertCreated();

        $botMessage = Message::where('conversation_id', $conversation->id)->where('sender_id', $bot->id)->first();
        $this->assertNotNull($botMessage);
        $this->assertSame(config('ai.escalation_reply'), $botMessage->content);
        Http::assertNothingSent();
    }

    public function test_free_user_quota_is_10_messages_per_day(): void
    {
        $user = $this->makeUser();
        $bot = $this->makeBot();
        $this->fakeGemini();
        $this->consent($user);

        $conversation = $this->startConversation($user, $bot);

        Sanctum::actingAs($user);
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => "Pertanyaan ke-{$i}"])
                ->assertCreated();
        }

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Pertanyaan ke-11'])
            ->assertStatus(429);
    }

    public function test_pro_user_has_unlimited_ai_chat(): void
    {
        $user = $this->makeUser();
        $bot = $this->makeBot();
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => SubscriptionStatus::ACTIVE,
            'amount' => 15000,
            'currency' => 'IDR',
            'ends_at' => now()->addDays(30),
        ]);
        $this->fakeGemini();
        $this->consent($user);

        $conversation = $this->startConversation($user, $bot);

        Sanctum::actingAs($user);
        for ($i = 1; $i <= 12; $i++) {
            $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => "Pertanyaan ke-{$i}"])
                ->assertCreated();
        }
    }

    public function test_ai_bot_is_pinned_at_top_of_doctor_list(): void
    {
        $bot = $this->makeBot();
        $doctor = $this->makeUser();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => VerificationStatus::APPROVED,
        ]);
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $bot->uuid)
            ->assertJsonPath('data.0.is_ai_bot', true)
            ->assertJsonPath('data.0.full_name', 'Aura Skin');
    }

    public function test_user_can_delete_ai_conversation_history(): void
    {
        $user = $this->makeUser();
        $bot = $this->makeBot();
        $this->consent($user);

        $conversation = $this->startConversation($user, $bot);
        $conversation->messages()->create(['sender_id' => $user->id, 'content' => 'halo', 'type' => 'text']);
        $conversation->messages()->create(['sender_id' => $bot->id, 'content' => 'hai', 'type' => 'text']);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/ai-chat/conversations/{$conversation->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertSame(0, Message::where('conversation_id', $conversation->id)->count());
    }

    public function test_only_ai_conversations_can_be_deleted(): void
    {
        $user = $this->makeUser();
        $doctor = $this->makeUser();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => VerificationStatus::APPROVED,
        ]);
        $conversation = $this->startConversation($user, $doctor);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/ai-chat/conversations/{$conversation->uuid}")
            ->assertStatus(422);

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }
}
