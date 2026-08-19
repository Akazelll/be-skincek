<?php

namespace App\Http\Controllers;

use App\Models\DoctorVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['roles', 'doctorVerification']);
        $role = $user->roles->first()?->name ?? 'user';

        $profileData = [
            'uuid' => $user->uuid,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $role,
            'avatar_url' => $user->avatarUrl(),
            'google_avatar_url' => $user->google_avatar_url,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'gender' => $user->gender?->value,
            'profile_completed' => $user->hasCompletedProfile(),
        ];

        if ($role === 'user') {
            $profileData['subscription_status'] = $user->subscriptions()->where('status', 'active')->exists() ? 'Pro' : 'Free';
            $profileData['scan_count'] = $user->predictionHistories()->count();
            $profileData['user_messages_count'] = $user->user_messages_count;
            $profileData['remaining_free_messages'] = max(
                0,
                config('chat.free_message_limit', 3) - $user->user_messages_count
            );
        } elseif ($role === 'doctor') {
            $profileData['verification_status'] = $user->doctorVerification?->verification_status?->value ?? 'unverified';
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
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:laki_laki,perempuan'],
        ]);

        if (isset($validated['full_name'])) {
            $user->full_name = $validated['full_name'];
        }

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (isset($validated['date_of_birth'])) {
            $user->date_of_birth = $validated['date_of_birth'];
        }

        if (isset($validated['gender'])) {
            $user->gender = $validated['gender'];
        }

        if ($request->hasFile('avatar')) {
            abort_unless($user->canChangeAvatar(), 422, 'Kamu hanya dapat mengganti foto profil maksimal 1x dalam 24 jam');

            $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');
            $user->markAvatarChanged();
            $user->unsetRelation('media');
        }

        $user->save();

        return $this->successResponse([
            'uuid' => $user->uuid,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatarUrl(),
            'google_avatar_url' => $user->google_avatar_url,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'gender' => $user->gender?->value,
            'profile_completed' => $user->hasCompletedProfile(),
        ], ['message' => 'Profil berhasil diperbarui']);
    }

    public function destroyAvatar(Request $request)
    {
        $user = $request->user();

        $user->clearMediaCollection('avatar');
        $user->forceFill(['avatar_updated_at' => null])->save();
        $user->unsetRelation('media');

        return $this->successResponse([
            'avatar_url' => $user->avatarUrl(),
            'google_avatar_url' => $user->google_avatar_url,
        ], ['message' => 'Foto profil berhasil dihapus']);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        activity()
            ->useLog('account_deletion')
            ->performedOn($user)
            ->causedBy($user)
            ->log('Account deletion requested (soft delete)');

        return $this->successResponse(null, ['message' => 'Akun berhasil dihapus']);
    }
}
