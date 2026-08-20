<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark monthly subscriptions that have passed their end date as expired';

    public function handle(): int
    {
        $expired = Subscription::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('period', '!=', 'lifetime')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::EXPIRED]);

        $this->info("Expired {$expired} subscription(s).");

        return self::SUCCESS;
    }
}
