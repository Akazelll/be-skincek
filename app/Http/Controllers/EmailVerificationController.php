<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EmailVerificationController extends Controller
{
    public function send(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->errorResponse('Email sudah terverifikasi', 422);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($this->key($user->email), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ], 600);
        Mail::to($user->email)->send(new EmailVerificationOtpMail($otp));

        return $this->successResponse(null, ['message' => 'Kode OTP verifikasi telah dikirim ke email Anda']);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->errorResponse('Email sudah terverifikasi', 422);
        }

        $key = $this->key($user->email);
        $stored = Cache::get($key);

        if (! $stored || ! Hash::check($validated['otp'], $stored['hash'])) {
            if ($stored) {
                $stored['attempts']++;
                $stored['attempts'] >= 5
                    ? Cache::forget($key)
                    : Cache::put($key, $stored, max(1, $stored['expires_at'] - now()->timestamp));
            }

            return $this->errorResponse('Kode OTP tidak valid atau kedaluwarsa', 422);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        Cache::forget($key);

        return $this->successResponse(null, ['message' => 'Email berhasil diverifikasi']);
    }

    private function key(string $email): string
    {
        return 'email-verify-otp:'.hash('sha256', mb_strtolower($email));
    }
}
