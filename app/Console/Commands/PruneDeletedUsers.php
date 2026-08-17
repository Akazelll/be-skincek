<?php

namespace App\Console\Commands;

use App\Models\PredictionHistory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneDeletedUsers extends Command
{
    protected $signature = 'users:prune {--days= : Masa tenggang penghapusan permanen (default dari config/plans.php)}';

    protected $description = 'Hapus permanen user yang soft-deleted melewati masa tenggang (right to erasure, FR-33)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('plans.grace_period_days', 30));
        $cutoff = now()->subDays($days);
        $count = 0;

        User::onlyTrashed()->where('deleted_at', '<', $cutoff)->cursor()->each(function ($user) use (&$count) {
            DB::transaction(function () use ($user) {
                PredictionHistory::where('user_id', $user->id)->forceDelete();

                $user->notifications()->delete();
                $user->tokens()->delete();
                $user->forceDelete();
            });
            $count++;
        });

        $this->info("Permanently deleted {$count} user(s) past the {$days}-day grace period.");

        return self::SUCCESS;
    }
}
