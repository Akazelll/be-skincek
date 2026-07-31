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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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

    private function fakePredictionService(): void
    {
        $this->app->instance(SkinPredictionServiceContract::class, new class implements SkinPredictionServiceContract
        {
            public function predict(string $imagePath, bool $cropped = false): array
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
