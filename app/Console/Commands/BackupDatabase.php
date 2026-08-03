<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--retention= : Jumlah hari backup disimpan (default dari .env BACKUP_RETENTION_DAYS)}';

    protected $description = 'Backup harian MySQL (mysqldump), disimpan lokal + upload ke R2 bila disk backups dikonfigurasi';

    public function handle(): int
    {
        $connection = config('database.connections.mysql');
        $retention = (int) ($this->option('retention') ?? env('BACKUP_RETENTION_DAYS', 30));

        if ($connection['driver'] !== 'mysql') {
            $this->warn('Backup hanya didukung untuk koneksi MySQL. Lewati.');

            return self::SUCCESS;
        }

        $filename = 'skincek-'.now()->format('Y-m-d-His').'.sql.gz';
        $directory = storage_path('app/backups');
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $command = array_filter([
            'mysqldump',
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.$connection['username'],
            $connection['password'] !== null && $connection['password'] !== '' ? '--password='.$connection['password'] : null,
            '--single-transaction',
            $connection['database'],
        ], fn ($item) => $item !== null);

        $result = Process::run($command);

        if ($result->failed()) {
            $this->error('mysqldump gagal: '.$result->errorOutput());

            return self::FAILURE;
        }

        $raw = $result->output();

        $gzipped = function_exists('gzencode') ? gzencode($raw, 6) : null;

        if ($gzipped !== null) {
            file_put_contents($path, $gzipped);
        } else {
            file_put_contents($path, $raw);
        }

        $this->info("Backup tersimpan: {$path}");

        $backupsDisk = config('filesystems.disks.backups');

        if (is_array($backupsDisk)) {
            Storage::disk('backups')->put($filename, $gzipped ?? $raw);
            $this->info("Backup diunggah ke disk 'backups' (Cloudflare R2).");
        } else {
            $this->warn("Disk 'backups' belum dikonfigurasi - backup hanya lokal.");
        }

        $this->prune($directory, $retention);

        return self::SUCCESS;
    }

    private function prune(string $directory, int $retention): void
    {
        $cutoff = now()->subDays($retention)->getTimestamp();

        foreach (glob($directory.'/skincek-*.sql*') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }

        if (is_array(config('filesystems.disks.backups'))) {
            $cutoffPath = now()->subDays($retention)->toDateString();

            foreach (Storage::disk('backups')->files() as $file) {
                if (preg_match('/skincek-(\d{4}-\d{2}-\d{2})/', basename($file), $m) && $m[1] < $cutoffPath) {
                    Storage::disk('backups')->delete($file);
                }
            }
        }
    }
}
