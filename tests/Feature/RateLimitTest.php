<?php

namespace Tests\Feature;

use App\Contracts\SkinPredictionServiceContract;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create(['email' => 'throttle-login@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/login', [
                'email' => 'throttle-login@example.com',
                'password' => 'password',
            ])->assertOk();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'throttle-login@example.com',
            'password' => 'password',
        ])->assertStatus(429);
    }

    public function test_forgot_password_is_throttled_three_per_fifteen_minutes(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/v1/forgot-password', ['email' => 'throttle-forgot@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/forgot-password', ['email' => 'throttle-forgot@example.com'])->assertStatus(429);
    }

    public function test_reset_password_is_throttled_after_ten_attempts(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/v1/reset-password', [
                'email' => 'throttle-reset@example.com',
                'otp' => '000000',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/reset-password', [
            'email' => 'throttle-reset@example.com',
            'otp' => '000000',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(429);
    }

    public function test_scan_uploads_are_throttled(): void
    {
        $user = User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ]);
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        foreach (range(1, 30) as $attempt) {
            $this->postJson('/api/v1/scans', [
                'image' => UploadedFile::fake()->image('face.jpg'),
            ])->assertCreated();
        }

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertStatus(429);
    }

    private function fakePredictionService(): void
    {
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
    }
}
