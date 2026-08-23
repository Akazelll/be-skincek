<?php

namespace App\Http\Controllers;

use App\Models\DoctorVerification;
use App\Models\Message;
use App\Support\ImageExifStripper;
use App\Support\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
            'email_verified' => $user->hasVerifiedEmail(),
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

            $user->addMedia(ImageExifStripper::strip($request->file('avatar')))->toMediaCollection('avatar');
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

    public function export(Request $request)
    {
        $user = $request->user()->load(['subscriptions', 'predictionHistories', 'doctorVerification', 'aiChatConsents']);

        $payload = [
            'exported_at' => now()->toISOString(),
            'profile' => [
                'uuid' => $user->uuid,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'gender' => $user->gender?->value,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'privacy_consent_at' => $user->privacy_consent_at?->toISOString(),
                'created_at' => $user->created_at?->toISOString(),
            ],
            'subscriptions' => $user->subscriptions->map(fn ($s) => [
                'uuid' => $s->uuid,
                'status' => $s->status->value,
                'payment_method' => $s->payment_method,
                'paid_at' => $s->paid_at?->toISOString(),
                'created_at' => $s->created_at?->toISOString(),
            ]),
            'scan_histories' => $user->predictionHistories->map(fn ($p) => [
                'uuid' => $p->uuid,
                'scan_mode' => $p->scan_mode->value,
                'predicted_class' => $p->predicted_class,
                'confidence' => (float) $p->confidence,
                'probabilities' => $p->probabilities,
                'severity_score' => $p->severity_score,
                'severity_level' => $p->severity_level->value,
                'model_used' => $p->model_used,
                'created_at' => $p->created_at?->toISOString(),
                'image_url' => MediaHelper::url(
                    $p->getFirstMedia('scan-photo') ?? $p->getFirstMedia('scan-photo-cropped')
                ),
            ]),
            'doctor_verification' => $user->doctorVerification ? [
                'specialization' => $user->doctorVerification->specialization,
                'verification_status' => $user->doctorVerification->verification_status->value,
                'submitted_at' => $user->doctorVerification->created_at?->toISOString(),
            ] : null,
            'messages_count' => Message::where('sender_id', $user->id)->count(),
            'device_tokens_count' => $user->deviceTokens()->count(),
            'ai_chat_consents' => $user->aiChatConsents->map(fn ($c) => [
                'consent_version' => $c->consent_version,
                'accepted_at' => $c->accepted_at?->toISOString(),
                'ip_address' => $c->ip_address,
                'created_at' => $c->created_at?->toISOString(),
            ]),
        ];

        $disk = Storage::disk('local');

        foreach ($disk->files("exports/{$user->uuid}") as $old) {
            $disk->delete($old);
        }

        $filename = "exports/{$user->uuid}/skincek-data-".now()->format('Ymd-His').'.json';
        $disk->put($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $this->successResponse([
            'download_url' => URL::temporarySignedRoute('profile.export.download', now()->addMinutes(30), ['file' => $filename]),
            'expires_in_minutes' => 30,
        ], ['message' => 'Data akun siap diunduh']);
    }

    public function downloadExport(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $file = (string) $request->query('file');

        if (! str_starts_with($file, 'exports/') || str_contains($file, '..')) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($file)) {
            abort(404);
        }

        return response()->download($disk->path($file), basename($file), ['Content-Type' => 'application/json'])
            ->deleteFileAfterSend(true);
    }
}
