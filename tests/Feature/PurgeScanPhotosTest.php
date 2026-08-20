<?php

namespace Tests\Feature;

use App\Models\PredictionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeScanPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_purge_removes_old_photos_but_keeps_records(): void
    {
        $user = User::factory()->create();

        $old = PredictionHistory::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'scan_mode' => 'upload',
            'predicted_class' => 'acne',
            'confidence' => 0.9,
            'probabilities' => ['acne' => 0.9],
            'severity_score' => 50,
            'severity_level' => 'medium',
            'model_used' => 'test-model',
            'created_at' => now()->subDays(100),
        ]);
        $old->addMediaFromString('fake-image-bytes')->usingFileName('old.jpg')->toMediaCollection('scan-photo');

        $recent = PredictionHistory::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'scan_mode' => 'upload',
            'predicted_class' => 'acne',
            'confidence' => 0.9,
            'probabilities' => ['acne' => 0.9],
            'severity_score' => 50,
            'severity_level' => 'medium',
            'model_used' => 'test-model',
            'created_at' => now()->subDays(10),
        ]);
        $recent->addMediaFromString('fake-image-bytes')->usingFileName('recent.jpg')->toMediaCollection('scan-photo');

        $this->artisan('scan-photos:purge')->assertSuccessful();

        $this->assertEmpty($old->refresh()->getMedia('scan-photo'));
        $this->assertNotEmpty($recent->refresh()->getMedia('scan-photo'));
        $this->assertDatabaseCount('prediction_histories', 2);
    }
}
