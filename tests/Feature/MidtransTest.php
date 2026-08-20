<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MidtransTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-TEST1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['services.midtrans.server_key' => self::SERVER_KEY]);
    }

    public function test_user_can_checkout_and_get_snap_token(): void
    {
        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('createSnapTransaction')
                ->once()
                ->andReturn((object) [
                    'token' => 'snap-token-123',
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/123',
                ]);
        });

        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions/checkout')
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'snap-token-123')
            ->assertJsonPath('data.subscription.plan_code', 'pro_monthly')
            ->assertJsonPath('data.subscription.status', 'pending')
            ->assertJsonPath('data.subscription.amount', 15000)
            ->assertJsonPath('data.subscription.currency', 'IDR');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'pending',
            'amount' => 15000,
        ]);
    }

    public function test_user_with_active_subscription_cannot_checkout_again(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions/checkout')
            ->assertStatus(422);

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_checkout_rolls_back_subscription_when_midtrans_fails(): void
    {
        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('createSnapTransaction')
                ->once()
                ->andThrow(new \RuntimeException('midtrans down'));
        });

        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions/checkout')
            ->assertStatus(502);

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_webhook_with_valid_signature_activates_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-ABC123',
        ]);

        $payload = [
            'order_id' => 'SKINCEK-ABC123',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'txn-001',
            'payment_type' => 'gopay',
            'signature_key' => hash('sha512', 'SKINCEK-ABC123'.'200'.'15000.00'.self::SERVER_KEY),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)
            ->assertOk()
            ->assertJsonPath('meta.message', 'ok');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
            'transaction_id' => 'txn-001',
            'payment_method' => 'gopay',
        ]);
    }

    public function test_webhook_signature_can_come_from_header(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-HEADER1',
        ]);

        $signature = hash('sha512', 'SKINCEK-HEADER1'.'200'.'15000.00'.self::SERVER_KEY);

        $this->postJson('/api/v1/webhooks/midtrans', [
            'order_id' => 'SKINCEK-HEADER1',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'txn-002',
            'payment_type' => 'bank_transfer',
        ], ['X-Signature-Key' => $signature])
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
        ]);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-XYZ',
        ]);

        $this->postJson('/api/v1/webhooks/midtrans', [
            'order_id' => 'SKINCEK-XYZ',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'forged-signature',
        ])->assertStatus(403);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_is_idempotent_on_retry(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-RETRY',
            'transaction_id' => 'txn-original',
            'paid_at' => now()->subDay(),
        ]);

        $payload = [
            'order_id' => 'SKINCEK-RETRY',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'txn-retry',
            'payment_type' => 'gopay',
            'signature_key' => hash('sha512', 'SKINCEK-RETRY'.'200'.'15000.00'.self::SERVER_KEY),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
            'transaction_id' => 'txn-original',
        ]);
    }

    public function test_webhook_for_unknown_order_is_noop(): void
    {
        $payload = [
            'order_id' => 'SKINCEK-NONEXISTENT',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'SKINCEK-NONEXISTENT'.'200'.'15000.00'.self::SERVER_KEY),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)
            ->assertOk();
    }

    public function test_webhook_settlement_sets_monthly_ends_at(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-MONTH1',
        ]);

        $payload = [
            'order_id' => 'SKINCEK-MONTH1',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'SKINCEK-MONTH1'.'200'.'15000.00'.self::SERVER_KEY),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status->value);
        $this->assertTrue($subscription->ends_at !== null);
        $this->assertTrue($subscription->ends_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertTrue($user->fresh()->hasActiveSubscription());
    }

    public function test_renewal_settlement_extends_existing_ends_at(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-MONTH2',
            'ends_at' => now()->addDays(10),
        ]);

        $payload = [
            'order_id' => 'SKINCEK-MONTH2',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', 'SKINCEK-MONTH2'.'200'.'15000.00'.self::SERVER_KEY),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();

        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->between(now()->addDays(39), now()->addDays(41)));
    }

    public function test_expired_monthly_subscription_no_longer_grants_benefits(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
            'ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($user->hasActiveSubscription());
    }

    public function test_expire_command_marks_past_due_subscriptions_as_expired(): void
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
            'ends_at' => now()->subDay(),
        ]);
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
            'ends_at' => now()->addDays(10),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(1, Subscription::where('status', 'expired')->count());
        $this->assertSame(1, Subscription::where('status', 'active')->count());
    }

    public function test_webhook_expire_marks_subscription_expired(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-EXP1',
        ]);

        $payload = [
            'order_id' => 'SKINCEK-EXP1',
            'status_code' => '202',
            'gross_amount' => '15000.00',
            'transaction_status' => 'expire',
            'transaction_id' => 'txn-003',
            'signature_key' => hash('sha512', 'SKINCEK-EXP1'.'202'.'15000.00'.self::SERVER_KEY),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'expired',
        ]);
    }

    public function test_webhook_does_not_downgrade_active_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
            'midtrans_order_id' => 'SKINCEK-DNG',
        ]);

        $payload = [
            'order_id' => 'SKINCEK-DNG',
            'status_code' => '202',
            'gross_amount' => '15000.00',
            'transaction_status' => 'expire',
            'signature_key' => hash('sha512', 'SKINCEK-DNG'.'202'.'15000.00'.self::SERVER_KEY),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
        ]);
    }
}
