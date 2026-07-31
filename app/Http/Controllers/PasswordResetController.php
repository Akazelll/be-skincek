<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $email = mb_strtolower($validated['email']);
        $user = User::where('email', $email)->where('is_active', true)->first();

        if ($user) {
            $otp = (string) random_int(100000, 999999);
            Cache::put($this->key($email), [
                'hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ], 600);
            Mail::to($user->email)->send(new PasswordResetOtpMail($otp));
        }

        return $this->successResponse(null, [
            'message' => 'Jika email terdaftar, kode OTP telah dikirim',
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = mb_strtolower($validated['email']);
        $key = $this->key($email);
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

        $user = User::where('email', $email)->where('is_active', true)->first();

        if (! $user) {
            Cache::forget($key);

            return $this->errorResponse('Kode OTP tidak valid atau kedaluwarsa', 422);
        }

        $user->update(['password' => $validated['password']]);
        $user->tokens()->delete();
        Cache::forget($key);

        return $this->successResponse(null, ['message' => 'Password berhasil diperbarui']);
    }

    private function key(string $email): string
    {
        return 'password-reset-otp:'.hash('sha256', $email);
    }
}
