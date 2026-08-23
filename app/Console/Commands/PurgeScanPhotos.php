<?php

namespace App\Console\Commands;

use App\Models\PredictionHistory;
use Illuminate\Console\Command;

class PurgeScanPhotos extends Command
{
    protected $signature = 'scan-photos:purge {--days= : Umur maksimal foto scan (default dari .env SCAN_PHOTO_RETENTION_DAYS)}';

    protected $description = 'Hapus foto scan yang lebih tua dari masa retensi (catatan prediksi dipertahankan)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('services.ml.scan_photo_retention_days', 90));
        $cutoff = now()->subDays($days);
        $count = 0;

        PredictionHistory::query()
            ->where('created_at', '<', $cutoff)
            ->with('media')
            ->cursor()
            ->each(function ($history) use (&$count) {
                $history->clearMediaCollection('scan-photo');
                $history->clearMediaCollection('scan-photo-cropped');
                $count++;
            });

        $this->info("Purged scan photos older than {$days} days for {$count} prediction(s).");

        return self::SUCCESS;
    }
}
