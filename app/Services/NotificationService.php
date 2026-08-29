<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Events\NotificationSent;
use App\Models\Notification;
use App\Models\User;

/**
 * Service Layer — Notification Factory & Business Logic.
 *
 * SEMUA notifikasi dibuat melalui service ini (terpusat).
 * Tidak ada Notification::create() yang tersebar di controller/service lain.
 *
 * Tanggung jawab:
 * 1. Factory methods untuk setiap use case notifikasi
 * 2. Insert DB + dispatch broadcast event (ShouldBroadcastNow, synchronous)
 */
class NotificationService
{
    // ========================================================================
    // USER NOTIFICATIONS
    // ========================================================================

    /**
     * Notifikasi: User berhasil login.
     */
    public function welcome(User $user): void
    {
        $this->send(
            user: $user,
            type: NotificationType::INFO,
            category: NotificationCategory::WELCOME,
            title: 'Selamat datang di SkinCek!',
            message: "Halo {$user->full_name}, senang melihatmu kembali. Yuk, cek kondisi kulitmu hari ini!",
        );
    }

    /**
     * Notifikasi: Hasil scan selesai diproses.
     */
    public function scanComplete(User $user, string $predictedClass, int $confidence, string $predictionUuid): void
    {
        $this->send(
            user: $user,
            type: NotificationType::SUCCESS,
            category: NotificationCategory::SCAN_COMPLETE,
            title: 'Hasil scan kamu sudah ready!',
            message: "Prediksi {$predictedClass} dengan tingkat keyakinan {$confidence}%.",
            actionUrl: "/scan/history/{$predictionUuid}",
        );
    }

    /**
     * Notifikasi: Pesan chat baru masuk.
     */
    public function chatMessage(User $user, string $senderName, string $message, string $conversationUuid): void
    {
        $this->send(
            user: $user,
            type: NotificationType::INFO,
            category: NotificationCategory::CHAT_MESSAGE,
            title: "Pesan baru dari {$senderName}",
            message: mb_strimwidth(strip_tags($message), 0, 120, '…'),
            actionUrl: "/chat/{$conversationUuid}",
        );
    }

    /**
     * Notifikasi: User logout.
     */
    public function logout(User $user): void
    {
        $this->send(
            user: $user,
            type: NotificationType::INFO,
            category: NotificationCategory::LOGOUT,
            title: 'Kamu telah berhasil logout',
            message: 'Sesi kamu telah diakhiri dengan aman. Sampai jumpa lagi!',
        );
    }

    // ========================================================================
    // DOCTOR NOTIFICATIONS
    // ========================================================================

    /**
     * Notifikasi: Verifikasi dokter disetujui/ditolak/direvisi.
     */
    public function verificationStatus(User $user, string $status, string $message): void
    {
        $category = match ($status) {
            'approved' => NotificationCategory::VERIFICATION_APPROVED,
            'rejected' => NotificationCategory::VERIFICATION_REJECTED,
            'needs_revision' => NotificationCategory::VERIFICATION_REVISION,
            default => NotificationCategory::VERIFICATION_REVISION,
        };

        $type = match ($status) {
            'approved' => NotificationType::SUCCESS,
            'rejected' => NotificationType::ERROR,
            'needs_revision' => NotificationType::WARNING,
            default => NotificationType::WARNING,
        };

        $this->send(
            user: $user,
            type: $type,
            category: $category,
            title: 'Verifikasi Dokter',
            message: $message,
            actionUrl: '/doctor/verification',
        );
    }

    // ========================================================================
    // SUBSCRIPTION NOTIFICATIONS
    // ========================================================================

    /**
     * Notifikasi: Pembayaran langganan berhasil.
     */
    public function subscriptionActive(User $user, string $subscriptionUuid): void
    {
        $this->send(
            user: $user,
            type: NotificationType::SUCCESS,
            category: NotificationCategory::SUBSCRIPTION_ACTIVE,
            title: 'Pembayaran berhasil',
            message: 'Selamat, langganan SkinCek Pro kamu sudah aktif.',
            actionUrl: "/subscription/{$subscriptionUuid}",
        );
    }

    // ========================================================================
    // CORE SEND METHOD
    // ========================================================================

    /**
     * Insert notifikasi ke DB dan broadcast via Reverb.
     * Semua factory method di atas memanggil method ini.
     */
    private function send(
        User $user,
        NotificationType $type,
        NotificationCategory $category,
        string $title,
        string $message,
        ?string $actionUrl = null,
    ): Notification {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);

        // Broadcast ke Reverb (synchronous — ShouldBroadcastNow, tanpa queue)
        event(new NotificationSent($notification));

        return $notification;
    }
}
