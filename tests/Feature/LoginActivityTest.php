<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_login_activity_and_identify_current_session(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $current->accessToken->forceFill([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
        ])->save();
        $user->createToken('other');

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/login-activity')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'uuid' => $current->accessToken->uuid,
                'device' => 'Chrome di Windows',
                'is_current' => true,
                'location' => null,
            ])
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.token');
    }

    public function test_user_can_revoke_specific_session_by_uuid(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $other = $user->createToken('other');

        $this->withToken($current->plainTextToken)
            ->deleteJson("/api/v1/login-activity/{$other->accessToken->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $foreign = User::factory()->create()->createToken('foreign');

        $this->withToken($current->plainTextToken)
            ->deleteJson("/api/v1/login-activity/{$foreign->accessToken->uuid}")
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }

    public function test_login_activity_includes_resolved_location_from_ip(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Indonesia',
                'city' => 'Jakarta',
                'regionName' => 'Jakarta',
            ]),
        ]);

        $user = User::factory()->create();
        $current = $user->createToken('current');
        $current->accessToken->forceFill([
            'ip_address' => '103.6.23.1',
            'user_agent' => 'Mozilla/5.0 (iPhone)',
        ])->save();

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/login-activity')
            ->assertOk()
            ->assertJsonFragment([
                'location' => [
                    'city' => 'Jakarta',
                    'region' => 'Jakarta',
                    'country' => 'Indonesia',
                ],
            ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'ip-api.com/json/103.6.23.1'));
    }

    public function test_login_activity_returns_null_location_when_ip_resolution_fails(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'fail', 'message' => 'reserved range'], 200),
        ]);

        $user = User::factory()->create();
        $current = $user->createToken('current');
        $current->accessToken->forceFill(['ip_address' => '127.0.0.1'])->save();

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/login-activity')
            ->assertOk()
            ->assertJsonFragment(['location' => null]);

        Http::assertNothingSent();
    }

    public function test_login_activity_keeps_last_known_location_when_global_budget_exhausted(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Indonesia',
                'city' => 'Jakarta',
                'regionName' => 'Jakarta',
            ]),
        ]);

        $user = User::factory()->create();
        $current = $user->createToken('current');
        $current->accessToken->forceFill(['ip_address' => '103.6.23.1'])->save();

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/login-activity')
            ->assertJsonFragment(['location' => ['city' => 'Jakarta', 'region' => 'Jakarta', 'country' => 'Indonesia']]);

        foreach (range(1, 45) as $i) {
            RateLimiter::hit('ip-location', 60);
        }
        Cache::forget('ip-location-fresh:103.6.23.1');

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/login-activity')
            ->assertOk()
            ->assertJsonFragment(['location' => ['city' => 'Jakarta', 'region' => 'Jakarta', 'country' => 'Indonesia']]);

        Http::assertSentCount(1);
    }

    public function test_login_activity_returns_null_location_when_budget_exhausted_and_no_previous_data(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'country' => 'Indonesia', 'city' => 'Jakarta', 'regionName' => 'Jakarta']),
        ]);

        foreach (range(1, 45) as $i) {
            RateLimiter::hit('ip-location', 60);
        }

        $user = User::factory()->create();
        $current = $user->createToken('current');
        $current->accessToken->forceFill(['ip_address' => '103.6.23.1'])->save();

        $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/login-activity')
            ->assertOk()
            ->assertJsonFragment(['location' => null]);

        Http::assertNothingSent();
    }
}
