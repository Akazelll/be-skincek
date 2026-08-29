<?php

namespace App\Events;

use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Models\DoctorVerification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast saat verifikasi dokter di-review.
 *
 * Menggunakan custom event name 'doctor.verification.reviewed'.
 * Format payload konsisten dengan NotificationResource.
 */
class DoctorVerificationReviewed implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public DoctorVerification $doctorVerification) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->doctorVerification->doctor->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'doctor.verification.reviewed';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => NotificationType::SUCCESS->value,
            'category' => NotificationCategory::VERIFICATION_APPROVED->value,
            'verification_status' => $this->doctorVerification->verification_status->value,
            'rejection_reason' => $this->doctorVerification->rejection_reason,
            'revision_note' => $this->doctorVerification->revision_note,
            'reviewed_at' => $this->doctorVerification->reviewed_at?->toISOString(),
        ];
    }
}
