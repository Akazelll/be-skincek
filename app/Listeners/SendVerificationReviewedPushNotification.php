<?php

namespace App\Listeners;

use App\Contracts\PushNotificationServiceContract;
use App\Events\DoctorVerificationReviewed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendVerificationReviewedPushNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PushNotificationServiceContract $push) {}

    public function handle(DoctorVerificationReviewed $event): void
    {
        $verification = $event->doctorVerification;

        if (! $verification->doctor) {
            return;
        }

        $status = $verification->verification_status?->value;

        $this->push->sendToUser($verification->doctor, 'Status verifikasi dokter', $this->statusText($status), [
            'type' => 'doctor_verification_reviewed',
            'verification_status' => (string) $status,
            'doctor_verification_uuid' => $verification->uuid,
        ]);
    }

    private function statusText(?string $status): string
    {
        return match ($status) {
            'approved' => 'Verifikasi Anda telah disetujui.',
            'rejected' => 'Verifikasi Anda ditolak.',
            'needs_revision' => 'Verifikasi Anda membutuhkan revisi.',
            default => 'Status verifikasi Anda berubah.',
        };
    }
}
