<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
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
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'privacy_consent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
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
}
