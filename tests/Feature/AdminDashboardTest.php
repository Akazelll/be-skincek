<?php

namespace Tests\Feature;

use App\Models\DoctorVerification;
use App\Models\PredictionHistory;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_dashboard_returns_stats_charts_and_verifications(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        DoctorVerification::create([
            'doctor_id' => $doctor->id,
            'specialization' => 'Dermatologi',
            'verification_status' => 'pending',
        ]);

        PredictionHistory::create([
            'user_id' => $user->id,
            'scan_mode' => 'upload',
            'predicted_class' => 'Redness',
            'confidence' => 0.9,
            'probabilities' => [],
            'severity_score' => 50,
            'severity_level' => 'medium',
            'model_used' => 'EfficientNet-B2',
        ]);

        Subscription::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'plan_code' => 'pro_monthly',
            'period' => 'monthly',
            'status' => 'active',
            'amount' => 15000,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_users',
                        'total_doctors',
                        'new_users_this_week',
                        'total_scans',
                        'scans_today',
                        'active_pro_subscriptions',
                        'monthly_revenue',
                    ],
                    'pending_actions' => ['doctor_verifications'],
                    'charts' => [
                        'scans_last_14_days',
                        'registrations_last_14_days',
                    ],
                    'recent_verifications',
                ],
            ]);

        $this->assertEquals(1, $response->json('data.stats.total_users'));
        $this->assertEquals(1, $response->json('data.stats.total_doctors'));
        $this->assertEquals(1, $response->json('data.stats.total_scans'));
        $this->assertEquals(1, $response->json('data.stats.scans_today'));
        $this->assertEquals(1, $response->json('data.stats.active_pro_subscriptions'));
        $this->assertEquals(15000, $response->json('data.stats.monthly_revenue'));
        $this->assertEquals(1, $response->json('data.pending_actions.doctor_verifications'));

        $this->assertCount(14, $response->json('data.charts.scans_last_14_days'));
        $this->assertCount(14, $response->json('data.charts.registrations_last_14_days'));

        $todayScans = collect($response->json('data.charts.scans_last_14_days'))->last();
        $this->assertEquals(today()->toDateString(), $todayScans['date']);
        $this->assertEquals(1, $todayScans['count']);
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }
}
