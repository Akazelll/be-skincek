<?php

namespace App\Http\Controllers;

use App\Contracts\GoogleTokenVerifierContract;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'privacy_consent' => 'required|accepted',
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'privacy_consent_at' => now(),
        ]);

        $user->assignRole('user');

        $token = $this->createSessionToken($user, $request);

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], [], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! $user->is_active || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Kredensial tidak valid', 401);
        }

        $token = $this->createSessionToken($user, $request);

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function google(Request $request, GoogleTokenVerifierContract $verifier)
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'privacy_consent' => ['sometimes', 'accepted'],
        ]);

        $payload = $verifier->verify($validated['id_token']);

        $emailVerified = in_array($payload['email_verified'] ?? false, [true, 1, '1', 'true'], true);

        if (! $payload || empty($payload['sub']) || empty($payload['email']) || ! $emailVerified) {
            return $this->errorResponse('Token Google tidak valid', 401);
        }

        $user = DB::transaction(function () use ($payload, $validated) {
            $user = User::where('google_id', $payload['sub'])->first();

            if (! $user) {
                $user = User::where('email', $payload['email'])->lockForUpdate()->first();

                if ($user?->google_id && $user->google_id !== $payload['sub']) {
                    return null;
                }

                if (! $user) {
                    abort_unless($validated['privacy_consent'] ?? false, 422, 'Persetujuan privasi wajib untuk pengguna baru');

                    $user = User::create([
                        'google_id' => $payload['sub'],
                        'full_name' => $payload['name'] ?? $payload['email'],
                        'email' => $payload['email'],
                        'email_verified_at' => now(),
                        'password' => null,
                        'privacy_consent_at' => now(),
                        'is_active' => true,
                    ]);
                    $user->assignRole('user');
                } else {
                    $user->update(['google_id' => $payload['sub'], 'email_verified_at' => $user->email_verified_at ?? now()]);
                }
            }

            return $user;
        });

        if (! $user || ! $user->is_active) {
            return $this->errorResponse('Akun Google tidak dapat digunakan', 401);
        }

        return $this->successResponse([
            'user' => $user,
            'token' => $this->createSessionToken($user, $request),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, ['message' => 'Berhasil logout dari sesi ini']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->successResponse(null, ['message' => 'Berhasil logout dari semua perangkat']);
    }

    private function createSessionToken(User $user, Request $request): string
    {
        $tokenResult = $user->createToken('auth_token');

        $tokenResult->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();

        return $tokenResult->plainTextToken;
    }
}
