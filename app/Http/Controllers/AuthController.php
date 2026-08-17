<?php

namespace App\Http\Controllers;

use App\Contracts\GoogleTokenVerifierContract;
use App\Enums\VerificationStatus;
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

    public function registerDoctor(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'str_number' => ['nullable', 'string', 'max:50'],
            'specialization' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:100'],
            'sub_specialization' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'between:0,100'],
            'alma_mater' => ['nullable', 'string', 'max:255'],
            'practice_locations' => ['nullable', 'array', 'max:20'],
            'practice_locations.*' => ['string', 'max:255'],
            'professional_organizations' => ['nullable', 'array', 'max:20'],
            'professional_organizations.*' => ['string', 'max:100'],
            'documents' => ['required', function ($attribute, $value, $fail) {
                if (is_array($value)) {
                    foreach ($value as $file) {
                        if (! $file instanceof \Illuminate\Http\UploadedFile) {
                            $fail('Each document must be a valid file.');
                        }
                    }
                } elseif (! $value instanceof \Illuminate\Http\UploadedFile) {
                    $fail('The documents field must be a file or an array of files.');
                }
            }],
            'documents.*' => ['file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'privacy_consent' => ['required', 'accepted'],
        ]);

        $user = DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'privacy_consent_at' => now(),
                'is_active' => true,
            ]);

            $user->assignRole('doctor');

            $verification = $user->doctorVerification()->create([
                'str_number' => $validated['str_number'] ?? null,
                'specialization' => $validated['specialization'],
                'title' => $validated['title'] ?? null,
                'sub_specialization' => $validated['sub_specialization'] ?? null,
                'experience_years' => $validated['experience_years'] ?? null,
                'alma_mater' => $validated['alma_mater'] ?? null,
                'practice_locations' => $validated['practice_locations'] ?? [],
                'professional_organizations' => $validated['professional_organizations'] ?? [],
                'verification_status' => VerificationStatus::PENDING,
            ]);

            if ($request->hasFile('documents')) {
                $files = $request->file('documents');
                if (! is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    $verification->addMedia($file)->toMediaCollection('verification-document');
                }
            }

            return $user;
        });

        $token = $this->createSessionToken($user, $request);

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], ['message' => 'Registrasi dokter berhasil diajukan.'], 201);
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
