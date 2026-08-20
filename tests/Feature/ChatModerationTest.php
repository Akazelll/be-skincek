<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['chat.bad_words' => ['anjing', 'kebodohan']]);
    }

    public function test_message_with_bad_word_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => 'approved',
        ]);
        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [
            'content' => 'kamu anjing',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Pesan mengandung kata yang tidak pantas');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_clean_message_passes_moderation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => 'approved',
        ]);
        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [
            'content' => 'Terima kasih dokternya baik sekali',
        ])->assertCreated();
    }
}
