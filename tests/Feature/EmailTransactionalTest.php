<?php

namespace Tests\Feature;

use App\Contracts\SkinPredictionServiceContract;
use App\Mail\MessageNotificationMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentSuccessMail;
use App\Mail\ScanCompleteMail;
use App\Models\Conversation;
use App\Models\DoctorVerification;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailTransactionalTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-TEST1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
        config(['services.midtrans.server_key' => self::SERVER_KEY]);
    }

    private function webhookPayload(Subscription $subscription, string $status): array
    {
        $orderId = $subscription->midtrans_order_id;
        $gross = number_format($subscription->amount, 2, '.', '');

        return [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'transaction_id' => 'txn-'.Str::random(8),
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $orderId.'200'.$gross.self::SERVER_KEY),
        ];
    }

    public function test_payment_success_email_is_sent_on_settlement(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-TXN-SUCCESS',
        ]);

        $this->postJson('/api/v1/webhooks/midtrans', $this->webhookPayload($subscription, 'settlement'))
            ->assertOk();

        Mail::assertQueued(PaymentSuccessMail::class, function (PaymentSuccessMail $mail) use ($user, $subscription) {
            return $mail->hasTo($user->email)
                && $mail->subscription->is($subscription);
        });
    }

    public function test_payment_failed_email_is_sent_on_expire(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-TXN-EXPIRE',
        ]);

        $this->postJson('/api/v1/webhooks/midtrans', $this->webhookPayload($subscription, 'expire'))
            ->assertOk();

        Mail::assertQueued(PaymentFailedMail::class, function (PaymentFailedMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && str_contains($mail->reason, 'kedaluwarsa');
        });

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'expired']);
    }

    public function test_refund_of_active_subscription_cancels_and_emails(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-TXN-REFUND',
            'paid_at' => now(),
            'starts_at' => now(),
        ]);

        $this->postJson('/api/v1/webhooks/midtrans', $this->webhookPayload($subscription, 'refund'))
            ->assertOk();

        Mail::assertQueued(PaymentFailedMail::class, function (PaymentFailedMail $mail) use ($user) {
            return $mail->hasTo($user->email) && str_contains($mail->reason, 'refund');
        });

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'cancelled',
        ]);
        $this->assertNotNull($subscription->fresh()->ends_at);
    }

    public function test_scan_complete_email_is_sent_once_per_day(): void
    {
        $user = User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ]);
        Sanctum::actingAs($user);

        $this->app->instance(SkinPredictionServiceContract::class, new class implements SkinPredictionServiceContract
        {
            public function predict(string $imagePath, bool $cropped = false, ?string $originalName = null): array
            {
                return [
                    'predicted_class' => 'acne',
                    'confidence' => 0.91,
                    'probabilities' => ['acne' => 0.91],
                    'severity_score' => 0.73,
                    'severity_level' => 'high',
                    'model_used' => 'test-model',
                ];
            }
        });

        $this->postJson('/api/v1/scans', ['image' => UploadedFile::fake()->image('face.jpg')])->assertCreated();
        $this->postJson('/api/v1/scans', ['image' => UploadedFile::fake()->image('face-2.jpg')])->assertCreated();

        Mail::assertQueued(ScanCompleteMail::class, 1);
        Mail::assertQueued(ScanCompleteMail::class, function (ScanCompleteMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->history->predicted_class === 'acne';
        });
    }

    public function test_chat_message_email_is_sent_when_recipient_offline(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => 'approved',
        ]);
        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Halo dokter'])
            ->assertCreated();

        Mail::assertQueued(MessageNotificationMail::class, function (MessageNotificationMail $mail) use ($doctor, $conversation, $user) {
            return $mail->hasTo($doctor->email)
                && $mail->conversationId === $conversation->uuid
                && $mail->senderName === $user->full_name;
        });
    }

    public function test_chat_message_email_is_not_sent_when_recipient_is_active(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $doctor->createToken('auth_token')->accessToken->forceFill(['last_used_at' => now()])->save();
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatology',
            'verification_status' => 'approved',
        ]);
        $conversation = Conversation::create(['user_id' => $user->id, 'doctor_id' => $doctor->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['content' => 'Halo dokter'])
            ->assertCreated();

        Mail::assertNothingQueued(MessageNotificationMail::class);
    }
}
