<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use App\Support\MediaHelper;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, HasPublicUuid, HasRoles, InteractsWithMedia, Notifiable, SoftDeletes;

    protected $hidden = [
        'id',
        'password',
        'remember_token',
        'google_id',
        'media',
    ];

    protected $appends = ['avatar_url'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'privacy_consent_at' => 'datetime',
            'is_active' => 'boolean',
            'ai_bot' => 'boolean',
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'avatar_updated_at' => 'datetime',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatarUrl();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function aiChatConsents()
    {
        return $this->hasMany(AiChatConsent::class);
    }

    public function hasConsentedToAiChat(): bool
    {
        return $this->aiChatConsents()
            ->where('consent_version', config('ai.consent_version'))
            ->exists();
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where(fn ($query) => $query
                ->where('period', 'lifetime')
                ->orWhereNull('ends_at')
                ->orWhere('ends_at', '>=', now()))
            ->exists();
    }

    public function hasReachedFreeChatQuota(): bool
    {
        return $this->user_messages_count >= config('chat.free_message_limit', 3)
            && ! $this->hasActiveSubscription();
    }

    public function predictionHistories()
    {
        return $this->hasMany(PredictionHistory::class);
    }

    public function doctorVerification()
    {
        return $this->hasOne(DoctorVerification::class, 'doctor_id');
    }

    public function isVerifiedDoctor(): bool
    {
        return $this->doctorVerification?->verification_status === VerificationStatus::APPROVED;
    }

    public function skincareProducts()
    {
        return $this->hasMany(SkincareProduct::class, 'doctor_id');
    }

    public function skinRecommendations()
    {
        return $this->hasMany(SkinRecommendation::class, 'doctor_id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function doctorRatings()
    {
        return $this->hasMany(DoctorRating::class, 'doctor_id');
    }

    public function hasCompletedProfile(): bool
    {
        return $this->date_of_birth !== null && $this->gender !== null;
    }

    public function avatarUrl(): ?string
    {
        $media = $this->getFirstMedia('avatar');

        return $media ? MediaHelper::url($media) : $this->google_avatar_url;
    }

    public function canChangeAvatar(): bool
    {
        return $this->avatar_updated_at === null
            || $this->avatar_updated_at->lte(now()->subDay());
    }

    public function markAvatarChanged(): void
    {
        $this->forceFill(['avatar_updated_at' => now()])->save();
    }
}
