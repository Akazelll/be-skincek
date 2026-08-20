<?php

namespace Tests\Feature;

use App\Contracts\SkinPredictionServiceContract;
use App\Models\PredictionHistory;
use App\Models\User;
use App\Services\HttpSkinPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_user_can_scan_and_severity_is_normalized(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.scan_mode', 'upload')
            ->assertJsonPath('data.predicted_class', 'acne')
            ->assertJsonPath('data.severity_score', 73);

        $this->assertDatabaseHas('prediction_histories', [
            'user_id' => $user->id,
            'severity_score' => 73,
        ]);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_livecam_scan_and_history_are_private(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $this->postJson('/api/v1/scans/livecam', [
            'image' => UploadedFile::fake()->image('crop.png'),
        ])->assertCreated()->assertJsonPath('data.scan_mode', 'livecam');

        $history = PredictionHistory::firstOrFail();

        $this->getJson('/api/v1/scans?filter[scan_mode]=livecam')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/scans/{$history->uuid}")->assertNotFound();
    }

    public function test_user_without_profile_cannot_scan_for_first_time(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('prediction_histories', 0);
    }

    public function test_user_with_partial_profile_cannot_scan_for_first_time(): void
    {
        $user = User::factory()->create([
            'date_of_birth' => '1995-05-15',
        ]);
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $this->postJson('/api/v1/scans/livecam', [
            'image' => UploadedFile::fake()->image('crop.png'),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('prediction_histories', 0);
    }

    public function test_user_can_scan_after_completing_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->fakePredictionService();

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertUnprocessable();

        $this->patchJson('/api/v1/profile', [
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ])->assertOk()
            ->assertJsonPath('data.profile_completed', true);

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();
    }

    public function test_scan_without_face_returns_ml_error_message(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        config(['services.ml.url' => 'http://ml.test']);
        $this->app->instance(SkinPredictionServiceContract::class, new HttpSkinPredictionService);

        Http::fake([
            'http://ml.test/predict' => Http::response([
                'detail' => 'Wajah tidak terdeteksi. Silakan upload foto wajah yang jelas dan menghadap kamera.',
            ], 422),
        ]);

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Wajah tidak terdeteksi. Silakan upload foto wajah yang jelas dan menghadap kamera.');

        $this->assertDatabaseCount('prediction_histories', 0);
    }

    public function test_scan_when_ml_service_down_returns_502(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        config(['services.ml.url' => 'http://ml.test']);
        $this->app->instance(SkinPredictionServiceContract::class, new HttpSkinPredictionService);

        Http::fake([
            'http://ml.test/predict' => Http::response(null, 503),
        ]);

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertStatus(503);

        $this->assertDatabaseCount('prediction_histories', 0);
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
}
