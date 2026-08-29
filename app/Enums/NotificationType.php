<?php

namespace App\Enums;

/**
 * Tipe Notifikasi — menentukan WARNA & ICON di Frontend.
 *
 * FE mapping:
 * - success → Hijau (toast variant "success")
 * - warning → Kuning (toast variant "warning")
 * - error   → Merah (toast variant "error")
 * - info    → Biru (toast variant "info")
 */
enum NotificationType: string
{
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
    case INFO = 'info';
}
