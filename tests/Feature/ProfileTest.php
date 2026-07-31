<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.full_name', $user->full_name)
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.subscription_status', 'Free');

        $this->patchJson('/api/v1/profile', ['full_name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'full_name' => 'Updated Name']);
    }

    public function test_user_can_soft_delete_account_and_revoke_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/v1/profile')
            ->assertOk();

        $this->assertSoftDeleted($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
