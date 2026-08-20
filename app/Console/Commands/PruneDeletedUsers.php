<?php

namespace App\Console\Commands;

use App\Models\DoctorRating;
use App\Models\Message;
use App\Models\PredictionFeedback;
use App\Models\PredictionHistory;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                $this->purgeMedia($user);
                $this->purgeChat($user);
                $this->purgeSubscriptions($user);
                $this->purgeExports($user);

                PredictionHistory::where('user_id', $user->id)->forceDelete();
                DoctorRating::where('user_id', $user->id)->orWhere('doctor_id', $user->id)->delete();
                PredictionFeedback::where('user_id', $user->id)->delete();

                $user->notifications()->delete();
                $user->tokens()->delete();
                $user->deviceTokens()->delete();
                $user->forceDelete();
            });
            $count++;
        });

        $this->info("Permanently deleted {$count} user(s) past the {$days}-day grace period.");

        return self::SUCCESS;
    }

    private function purgeMedia(User $user): void
    {
        $user->clearMediaCollection('avatar');

        $user->predictionHistories()->with('media')->each(function ($history) {
            $history->clearMediaCollection('scan-photo');
            $history->clearMediaCollection('scan-photo-cropped');
        });

        Message::where('sender_id', $user->id)->with('media')->get()->each(function ($message) {
            $message->clearMediaCollection('chat-media');
        });

        $verification = $user->doctorVerification;
        $verification?->clearMediaCollection('verification-document');
    }

    private function purgeChat(User $user): void
    {
        DB::table('conversations')
            ->where('user_id', $user->id)
            ->orWhere('doctor_id', $user->id)
            ->delete();
    }

    private function purgeSubscriptions(User $user): void
    {
        Subscription::where('user_id', $user->id)->delete();
    }

    private function purgeExports(User $user): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files("exports/{$user->uuid}") as $file) {
            $disk->delete($file);
        }

        $disk->deleteDirectory("exports/{$user->uuid}");
    }
}
