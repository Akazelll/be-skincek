<?php

namespace App\Events;

use App\Enums\NotificationType;
use App\Models\DoctorVerification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoctorVerificationReviewed implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public DoctorVerification $doctorVerification)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->doctorVerification->doctor->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return NotificationType::DOCTOR_VERIFICATION_REVIEWED->value;
    }

    public function broadcastWith(): array
    {
        return [
            'type' => NotificationType::DOCTOR_VERIFICATION_REVIEWED->value,
            'category' => NotificationType::DOCTOR_VERIFICATION_REVIEWED->category()->value,
            'verification_status' => $this->doctorVerification->verification_status->value,
            'rejection_reason' => $this->doctorVerification->rejection_reason,
            'revision_note' => $this->doctorVerification->revision_note,
            'reviewed_at' => $this->doctorVerification->reviewed_at?->toISOString(),
        ];
    }
}
