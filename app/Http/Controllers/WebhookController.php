<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentSuccessMail;
use App\Models\Subscription;
use App\Notifications\AppNotification;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

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

        $changed = false;

        DB::transaction(function () use ($subscription, $target, $payload, &$changed) {
            $locked = Subscription::whereKey($subscription->getKey())->lockForUpdate()->first();

            if ($locked->status === $target) {
                return;
            }

            $isRefund = $locked->status === SubscriptionStatus::ACTIVE
                && ($payload['transaction_status'] ?? '') === 'refund';

            if ($locked->status === SubscriptionStatus::ACTIVE && ! $isRefund) {
                return;
            }

            $changed = true;

            $locked->status = $target;
            $locked->transaction_id = $payload['transaction_id'] ?? $locked->transaction_id;
            $locked->payment_method = $payload['payment_type'] ?? $locked->payment_method;

            if ($target === SubscriptionStatus::ACTIVE) {
                $locked->starts_at = now();
                $locked->paid_at = now();

                $durationDays = (int) config("plans.{$locked->plan_code}.duration_days", 30);

                $locked->ends_at = $locked->period === 'lifetime'
                    ? null
                    : (($locked->ends_at?->isFuture() ? $locked->ends_at : now()))->copy()->addDays($durationDays);
            }

            if ($isRefund) {
                $locked->ends_at = now();
            }

            $locked->save();
        });

        if ($changed) {
            if ($target === SubscriptionStatus::ACTIVE) {
                Notification::send($subscription->user, new AppNotification(
                    'Pembayaran berhasil',
                    'Selamat, langganan SkinCek Pro kamu sudah aktif.',
                    ['subscription_id' => $subscription->uuid],
                    notificationType: 'subscription',
                ));

                Mail::to($subscription->user)->send(new PaymentSuccessMail($subscription->fresh(['user'])));
            } elseif ($target === SubscriptionStatus::CANCELLED || $target === SubscriptionStatus::EXPIRED) {
                $reason = match (true) {
                    ($payload['transaction_status'] ?? '') === 'expire' => 'Pembayaran kedaluwarsa sebelum diselesaikan.',
                    ($payload['transaction_status'] ?? '') === 'deny' => 'Pembayaran ditolak oleh bank atau penyedia pembayaran.',
                    ($payload['transaction_status'] ?? '') === 'refund' => 'Pembayaran telah dikembalikan (refund).',
                    default => 'Pembayaran dibatalkan sebelum diselesaikan.',
                };

                Mail::to($subscription->user)->send(new PaymentFailedMail($subscription->fresh(['user']), $reason));
            }
        }

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
            $status === 'cancel', $status === 'deny', $status === 'refund' => SubscriptionStatus::CANCELLED,
            default => SubscriptionStatus::PENDING,
        };
    }
}
