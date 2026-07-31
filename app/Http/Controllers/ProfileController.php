<?php

namespace App\Http\Controllers;

use App\Models\DoctorVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $role = $user->roles->first()?->name ?? 'user';

        $profileData = [
            'uuid' => $user->uuid,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $role,
            'avatar_url' => $user->getFirstMediaUrl('avatar'),
        ];

        if ($role === 'user') {
            $activeSub = $user->subscriptions()->where('status', 'active')->latest()->first();
            $profileData['subscription_status'] = $activeSub ? 'Pro' : 'Free';
            $profileData['scan_count'] = $user->predictionHistories()->count();
        } elseif ($role === 'doctor') {
            $verification = $user->doctorVerification;
            $profileData['verification_status'] = $verification?->verification_status?->value ?? 'unverified';
            $profileData['product_count'] = $user->skincareProducts()->count();
            $profileData['recommendation_count'] = $user->skinRecommendations()->count();
        } elseif ($role === 'admin') {
            $profileData['pending_doctor_verifications'] = DoctorVerification::where('verification_status', 'pending')->count();
        }

        return $this->successResponse($profileData);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|required|string|min:8',
            'avatar' => 'sometimes|nullable|image|max:2048',
        ]);

        if (isset($validated['full_name'])) {
            $user->full_name = $validated['full_name'];
        }

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        $user->save();

        return $this->successResponse([
            'uuid' => $user->uuid,
            'full_name' => $user->full_name,
            'avatar_url' => $user->getFirstMediaUrl('avatar'),
        ], ['message' => 'Profil berhasil diperbarui']);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        return $this->successResponse(null, ['message' => 'Akun berhasil dihapus']);
    }
}
