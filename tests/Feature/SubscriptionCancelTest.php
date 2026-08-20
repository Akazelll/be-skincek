<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_cancel_active_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscriptions/{$subscription->uuid}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
            'description' => 'Subscription cancelled by user',
        ]);
    }

    public function test_user_cannot_cancel_others_subscription(): void
    {
        $owner = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/subscriptions/{$subscription->uuid}/cancel")->assertNotFound();

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'active']);
    }

    public function test_user_cannot_cancel_non_active_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_code' => 'pro_lifetime',
            'status' => 'pending',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/subscriptions/{$subscription->uuid}/cancel")
            ->assertUnprocessable();

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'pending']);
    }
}
