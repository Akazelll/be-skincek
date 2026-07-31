<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = $request->user()
            ->subscriptions()
            ->latest()
            ->paginate(15);

        return SubscriptionResource::collection($subscriptions);
    }

    public function receipt(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        abort_unless($subscription->status === SubscriptionStatus::ACTIVE, 404);

        return new SubscriptionResource($subscription);
    }
}
