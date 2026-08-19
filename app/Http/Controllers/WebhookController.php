<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function handleMidtrans(Request $request, MidtransService $midtrans)
    {
        if ($request->isMethod('get')) {
            return $this->successResponse(null, ['message' => 'ok']);
        }

        $request->validate([
            'order_id' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'fraud_status' => ['sometimes', 'string'],
            'transaction_id' => ['sometimes', 'string'],
            'payment_type' => ['sometimes', 'string'],
            'signature_key' => ['sometimes', 'string'],
        ]);

        $payload = $request->json()->all();

        $signature = $request->header('X-Signature-Key') ?? ($payload['signature_key'] ?? '');

        if ($signature === '' || ! $midtrans->verifySignature($signature, $payload)) {
            return $this->errorResponse('Invalid signature', 403);
        }

        $subscription = Subscription::where('midtrans_order_id', $payload['order_id'] ?? null)->first();

        if (! $subscription) {
            return $this->successResponse(null, ['message' => 'ok']);
        }

        $target = $this->mapTransactionStatus($payload);

        DB::transaction(function () use ($subscription, $target, $payload) {
            $locked = Subscription::whereKey($subscription->getKey())->lockForUpdate()->first();

            if ($locked->status === $target || $locked->status === SubscriptionStatus::ACTIVE) {
                return;
            }

            $locked->status = $target;
            $locked->transaction_id = $payload['transaction_id'] ?? $locked->transaction_id;
            $locked->payment_method = $payload['payment_type'] ?? $locked->payment_method;

            if ($target === SubscriptionStatus::ACTIVE) {
                $locked->starts_at = now();
                $locked->ends_at = null;
                $locked->paid_at = now();
            }

            $locked->save();
        });

        return $this->successResponse(null, ['message' => 'ok']);
    }

    private function mapTransactionStatus(array $payload): SubscriptionStatus
    {
        $status = $payload['transaction_status'] ?? 'pending';
        $fraud = $payload['fraud_status'] ?? null;

        return match (true) {
            $status === 'settlement' => SubscriptionStatus::ACTIVE,
            $status === 'capture' && $fraud === 'accept' => SubscriptionStatus::ACTIVE,
            $status === 'expire' => SubscriptionStatus::EXPIRED,
            $status === 'cancel', $status === 'deny' => SubscriptionStatus::CANCELLED,
            default => SubscriptionStatus::PENDING,
        };
    }
}
