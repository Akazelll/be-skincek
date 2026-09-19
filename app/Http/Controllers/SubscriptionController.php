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
            ->paginate($this->perPage($request));

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

        abort_unless($user->hasVerifiedEmail(), 403, 'Verifikasi email terlebih dahulu sebelum berlangganan.');

        if ($user->hasActiveSubscription()) {
            return $this->errorResponse('Kamu sudah berlangganan SkinCek Pro', 422);
        }

        $expiryMinutes = (int) config('services.midtrans.expiry_duration', 1440);

        $hasOpenPayment = $user->subscriptions()
            ->where('status', SubscriptionStatus::PENDING)
            ->where('created_at', '>', now()->subMinutes($expiryMinutes))
            ->exists();

        if ($hasOpenPayment) {
            return $this->errorResponse('Ada pembayaran yang belum diselesaikan. Selesaikan pembayaran tersebut atau tunggu sampai kedaluwarsa sebelum membuat transaksi baru.', 422);
        }

        $user->subscriptions()
            ->where('status', SubscriptionStatus::PENDING)
            ->where('created_at', '<=', now()->subMinutes($expiryMinutes))
            ->update(['status' => SubscriptionStatus::EXPIRED]);

        $planCode = 'pro_monthly';
        $plan = config("plans.$planCode");

        $subscription = $user->subscriptions()->create([
            'plan_code' => $planCode,
            'period' => $plan['period'],
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
                    'name' => 'SkinCek Pro (Bulanan)',
                ]],
                'customer_details' => [
                    'first_name' => $user->full_name,
                    'email' => $user->email,
                ],
                'expiry' => [
                    'start_time' => now()->format('Y-m-d H:i:s O'),
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

    /**
     * POST /subscriptions/{subscription}/pay
     * Lanjutkan pembayaran untuk subscription PENDING — Snap token di-generate
     * ulang untuk midtrans_order_id yang sama (Midtrans mengembalikan token
     * yang sama selama transaksi belum dibayar).
     */
    public function pay(Request $request, Subscription $subscription, MidtransService $midtrans)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        if ($subscription->status !== SubscriptionStatus::PENDING) {
            return $this->errorResponse('Transaksi ini tidak dapat dilanjutkan', 422);
        }

        $expiryMinutes = (int) config('services.midtrans.expiry_duration', 1440);

        // Pending yang sudah lewat batas expiry tidak bisa dilanjutkan lagi —
        // tandai expired supaya user bisa membuat transaksi baru.
        if ($subscription->created_at->lte(now()->subMinutes($expiryMinutes))) {
            $subscription->update(['status' => SubscriptionStatus::EXPIRED]);

            return $this->errorResponse('Batas waktu pembayaran telah lewat. Silakan buat pembayaran baru.', 422);
        }

        try {
            $response = $midtrans->createSnapTransaction([
                'transaction_details' => [
                    'order_id' => $subscription->midtrans_order_id,
                    'gross_amount' => $subscription->amount,
                ],
                'item_details' => [[
                    'id' => $subscription->plan_code,
                    'price' => $subscription->amount,
                    'quantity' => 1,
                    'name' => 'SkinCek Pro',
                ]],
                'customer_details' => [
                    'first_name' => $request->user()->full_name,
                    'email' => $request->user()->email,
                ],
                // Anchor expiry ke waktu transaksi DIBUAT, bukan ke "now" —
                // tanpa ini, Midtrans memberi window expiry baru (reset 24 jam)
                // setiap kali token di-generate ulang.
                'expiry' => [
                    'start_time' => $subscription->created_at->format('Y-m-d H:i:s O'),
                    'unit' => 'minutes',
                    'duration' => $expiryMinutes,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuka halaman pembayaran, coba lagi nanti', 502);
        }

        return $this->successResponse([
            'snap_token' => $response->token,
            'redirect_url' => $response->redirect_url,
            'subscription' => new SubscriptionResource($subscription),
        ], ['message' => 'Lanjutkan pembayaran Anda']);
    }

    public function cancel(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        if ($subscription->status !== SubscriptionStatus::ACTIVE) {
            return $this->errorResponse('Langganan tidak dapat dibatalkan', 422);
        }

        $subscription->update(['status' => SubscriptionStatus::CANCELLED]);

        activity()
            ->useLog('subscription_cancelled')
            ->performedOn($subscription)
            ->causedBy($request->user())
            ->log('Subscription cancelled by user');

        return $this->successResponse(
            new SubscriptionResource($subscription),
            ['message' => 'Langganan berhasil dibatalkan']
        );
    }
}
