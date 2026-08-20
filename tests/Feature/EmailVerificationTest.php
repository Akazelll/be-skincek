<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Mail\EmailVerificationOtpMail;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    public function test_user_can_request_verification_otp(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verify/send')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Kode OTP verifikasi telah dikirim ke email Anda');

        Mail::assertSent(EmailVerificationOtpMail::class);
    }

    public function test_user_can_verify_email_with_otp(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verify/send')->assertOk();

        $otp = $this->lastOtp();

        $this->postJson('/api/v1/email/verify', ['otp' => $otp])
            ->assertOk()
            ->assertJsonPath('meta.message', 'Email berhasil diverifikasi');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_with_wrong_otp_fails(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verify/send')->assertOk();

        $this->postJson('/api/v1/email/verify', ['otp' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kode OTP tidak valid atau kedaluwarsa');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_cannot_scan(): void
    {
        $user = User::factory()->unverified()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertForbidden();

        $this->assertDatabaseCount('prediction_histories', 0);
    }

    public function test_unverified_user_cannot_send_chat_message(): void
    {
        $user = User::factory()->unverified()->create();
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

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'halo'])
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_unverified_user_cannot_checkout_subscription(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions/checkout')
            ->assertForbidden();

        $this->assertDatabaseCount('subscriptions', 0);
    }

    private function lastOtp(): string
    {
        $mail = Mail::sent(EmailVerificationOtpMail::class)->last();

        return $mail->otp;
    }
}
