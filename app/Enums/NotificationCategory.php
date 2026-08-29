<?php

namespace App\Enums;

/**
 * Kategori Notifikasi — menentukan KONTEKS BISNIS.
 *
 * Berbeda dengan NotificationType (warna), enum ini menjelaskan
 * "apa yang terjadi" secara spesifik. FE bisa pakai untuk icon
 * atau routing saat notifikasi diklik.
 */
enum NotificationCategory: string
{
    // === User ===
    case WELCOME = 'welcome';
    case SCAN_COMPLETE = 'scan_complete';
    case CHAT_MESSAGE = 'chat_message';
    case LOGOUT = 'logout';

    // === Doctor ===
    case VERIFICATION_APPROVED = 'verification_approved';
    case VERIFICATION_REJECTED = 'verification_rejected';
    case VERIFICATION_REVISION = 'verification_revision';

    // === Subscription ===
    case SUBSCRIPTION_ACTIVE = 'subscription_active';

    public function label(): string
    {
        return match ($this) {
            self::WELCOME => 'Selamat Datang',
            self::SCAN_COMPLETE => 'Scan Selesai',
            self::CHAT_MESSAGE => 'Pesan Chat',
            self::LOGOUT => 'Logout',
            self::VERIFICATION_APPROVED => 'Verifikasi Disetujui',
            self::VERIFICATION_REJECTED => 'Verifikasi Ditolak',
            self::VERIFICATION_REVISION => 'Perlu Revisi',
            self::SUBSCRIPTION_ACTIVE => 'Langganan Aktif',
        };
    }
}
