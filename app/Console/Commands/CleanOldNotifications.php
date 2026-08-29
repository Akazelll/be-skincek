<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * Artisan Command — Auto-cleanup notifikasi lama.
 *
 * Menghapus (hard delete) semua notifikasi yang berusia lebih dari N bulan.
 * Dijadwalkan berjalan daily via routes/console.php.
 *
 * Retention policy: 6 bulan — cukup lama untuk audit trail,
 * cukup pendek untuk menjaga performa database.
 */
class CleanOldNotifications extends Command
{
    protected $signature = 'notifications:clean
                            {--months=6 : Usia maksimal notifikasi dalam bulan}';

    protected $description = 'Hapus notifikasi yang lebih tua dari N bulan (default: 6)';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $cutoff = now()->subMonths($months);

        $deleted = Notification::where('created_at', '<', $cutoff)->delete();

        $this->info("Berhasil menghapus {$deleted} notifikasi yang lebih tua dari {$months} bulan.");

        return self::SUCCESS;
    }
}
