<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_six_digit_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('meta.message', 'Jika email terdaftar, kode OTP telah dikirim');

        Mail::assertSent(PasswordResetOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && preg_match('/^\d{6}$/', $mail->otp) === 1;
        });
    }

    public function test_unknown_email_returns_same_response_without_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath('meta.message', 'Jika email terdaftar, kode OTP telah dikirim');

        Mail::assertNothingSent();
    }

    public function test_valid_otp_resets_password_revokes_tokens_and_is_one_time_use(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->createToken('device');

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);
        $otp = null;
        Mail::assertSent(PasswordResetOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $payload = [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $this->postJson('/api/v1/reset-password', $payload)->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/reset-password', $payload)->assertUnprocessable();
    }

    public function test_otp_is_invalidated_after_five_wrong_attempts(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);
        $otp = null;
        Mail::assertSent(PasswordResetOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $payload = [
            'email' => $user->email,
            'otp' => '000000',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/reset-password', $payload)->assertUnprocessable();
        }

        $payload['otp'] = $otp;
        $this->postJson('/api/v1/reset-password', $payload)->assertUnprocessable();
    }
}
