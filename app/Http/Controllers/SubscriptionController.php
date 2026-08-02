<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

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

    public function checkout(Request $request, MidtransService $midtrans)
    {
        $user = $request->user();

        if ($user->hasActiveSubscription()) {
            return $this->errorResponse('Kamu sudah berlangganan SkinCek Pro', 422);
        }

        $planCode = 'pro_lifetime';
        $plan = config("plans.$planCode");

        $subscription = $user->subscriptions()->create([
            'plan_code' => $planCode,
            'status' => SubscriptionStatus::PENDING,
            'amount' => $plan['price'],
            'currency' => $plan['currency'],
        ]);

        $orderId = 'SKINCEK-'.Str::upper($subscription->uuid);
        $subscription->midtrans_order_id = $orderId;
        $subscription->save();

        try {
            $response = $midtrans->createSnapTransaction([
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $plan['price'],
                ],
                'item_details' => [[
                    'id' => $planCode,
                    'price' => $plan['price'],
                    'quantity' => 1,
                    'name' => 'SkinCek Pro (Lifetime)',
                ]],
                'customer_details' => [
                    'first_name' => $user->full_name,
                    'email' => $user->email,
                ],
                'expiry' => [
                    'start_time' => now()->toISOString(),
                    'unit' => 'minutes',
                    'duration' => config('services.midtrans.expiry_duration'),
                ],
            ]);
        } catch (Throwable $e) {
            report($e);
            $subscription->delete();

            return $this->errorResponse('Gagal membuat transaksi pembayaran, coba lagi nanti', 502);
        }

        return $this->successResponse([
            'snap_token' => $response->token,
            'redirect_url' => $response->redirect_url,
            'subscription' => new SubscriptionResource($subscription),
        ], ['message' => 'Transaksi pembayaran berhasil dibuat'], 201);
    }
}
