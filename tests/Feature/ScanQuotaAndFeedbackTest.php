<?php

namespace Tests\Feature;

use App\Contracts\SkinPredictionServiceContract;
use App\Models\PredictionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScanQuotaAndFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
        config(['services.ml.free_scan_limit' => 3]);
    }

    private function makeUserWithProfile(): User
    {
        return User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ]);
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

    public function test_free_user_is_limited_to_three_scans_per_day(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/scans', [
                'image' => UploadedFile::fake()->image("face-{$i}.jpg"),
            ])->assertCreated();
        }

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face-4.jpg'),
        ])->assertStatus(429);

        $this->assertDatabaseCount('prediction_histories', 3);
    }

    public function test_pro_user_has_unlimited_scans(): void
    {
        $user = $this->makeUserWithProfile();
        $user->subscriptions()->create([
            'plan_code' => 'pro_lifetime',
            'status' => 'active',
            'amount' => 15000,
            'currency' => 'IDR',
        ]);
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/scans', [
                'image' => UploadedFile::fake()->image("face-{$i}.jpg"),
            ])->assertCreated();
        }

        $this->assertDatabaseCount('prediction_histories', 5);
    }

    public function test_scan_response_includes_disclaimer(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.disclaimer', config('services.ml.disclaimer'))
            ->assertJsonPath('data.notice', null);
    }

    public function test_low_confidence_scan_includes_notice(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        $this->app->instance(SkinPredictionServiceContract::class, new class implements SkinPredictionServiceContract
        {
            public function predict(string $imagePath, bool $cropped = false, ?string $originalName = null): array
            {
                return [
                    'predicted_class' => 'acne',
                    'confidence' => 0.34,
                    'probabilities' => ['acne' => 0.34],
                    'severity_score' => 0.30,
                    'severity_level' => 'low',
                    'model_used' => 'test-model',
                ];
            }
        });

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.notice', 'Hasil prediksi ini memiliki tingkat keyakinan rendah. Sebaiknya lakukan scan ulang dengan pencahayaan yang lebih baik atau konsultasikan langsung dengan dokter kulit.');
    }

    public function test_user_can_submit_prediction_feedback(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $response = $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        $history = PredictionHistory::firstOrFail();

        $this->postJson("/api/v1/scans/{$history->uuid}/feedback", ['is_accurate' => true])
            ->assertOk()
            ->assertJsonPath('data.is_accurate', true);

        $this->assertDatabaseHas('prediction_feedbacks', [
            'prediction_history_id' => $history->id,
            'user_id' => $user->id,
            'is_accurate' => 1,
        ]);
    }

    public function test_feedback_is_scoped_to_owner(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        $history = PredictionHistory::firstOrFail();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/scans/{$history->uuid}/feedback", ['is_accurate' => true])
            ->assertNotFound();
    }

    public function test_emergency_hotlines_are_public(): void
    {
        $this->getJson('/api/v1/emergency')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.phone', '118');
    }
}
