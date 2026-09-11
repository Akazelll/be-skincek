<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_list_subscription_history(): void
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
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/subscriptions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_receipt_only_for_active_subscription(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $active = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
            'paid_at' => now(),
        ]);
        $pending = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/subscriptions/{$active->uuid}/receipt")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->getJson("/api/v1/subscriptions/{$pending->uuid}/receipt")
            ->assertNotFound();
    }

    public function test_user_cannot_view_other_users_receipt(): void
    {
        $other = User::factory()->create();
        $sub = Subscription::create([
            'user_id' => $other->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);

        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/subscriptions/{$sub->uuid}/receipt")
            ->assertNotFound();
    }

    public function test_checkout_blocked_while_pending_payment_exists(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions/checkout')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Ada pembayaran yang belum diselesaikan. Selesaikan pembayaran tersebut atau tunggu sampai kedaluwarsa sebelum membuat transaksi baru.']);

        $this->assertSame(1, $user->subscriptions()->count());
    }

    public function test_checkout_expires_stale_pending_and_creates_new(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('user');
        $stale = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
            'created_at' => now()->subDay(),
        ]);

        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('createSnapTransaction')->once()->andReturn(
                (object) ['token' => 'snap-token', 'redirect_url' => 'https://snap.test/pay']
            );
        });

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions/checkout')
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'snap-token');

        $this->assertSame('expired', $stale->fresh()->status->value);
        $this->assertSame(2, $user->subscriptions()->count());
    }
}
