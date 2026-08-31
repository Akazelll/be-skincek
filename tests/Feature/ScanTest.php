<?php

namespace Tests\Feature;

use App\Contracts\SkinPredictionServiceContract;
use App\Models\PredictionHistory;
use App\Models\User;
use App\Services\HttpSkinPredictionService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SkincareProductSeeder;
use Database\Seeders\SkinConcernSeeder;
use Database\Seeders\SkinRecommendationSeeder;
use Database\Seeders\SkinTypeSeeder;
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

        // Livecam menyimpan gambar request langsung ke collection scan-photo-cropped
        $this->assertNotNull($history->getFirstMedia('scan-photo-cropped'));
        $this->assertNull($history->getFirstMedia('scan-photo'));

        $this->getJson('/api/v1/scans?filter[scan_mode]=livecam')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/scans/{$history->uuid}")->assertNotFound();
    }

    public function test_upload_scan_with_face_crop_saves_yolo_crop(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        // Mock ML mengembalikan face_crop base64 (JPEG valid) seperti main.py v2.1.0
        $jpegBase64 = base64_encode(
            (string) file_get_contents(__DIR__.'/fixtures/face-crop.jpg')
        );
        $this->fakePredictionService('inflammatory acne', [
            'face_crop' => $jpegBase64,
            'face_crop_format' => 'jpeg',
            'face_detected' => true,
        ]);

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        $history = PredictionHistory::firstOrFail();

        // Face crop dari ML tersimpan ke scan-photo, BUKAN gambar asli dari request
        $media = $history->getFirstMedia('scan-photo');
        $this->assertNotNull($media);
        $this->assertEquals('face_crop.jpg', $media->file_name);
        $this->assertEquals('yolo_face_crop', $media->getCustomProperty('source'));

        // face_crop base64 tidak bocor ke raw_response
        $this->assertArrayNotHasKey('face_crop', $history->raw_response);
    }

    public function test_upload_scan_without_face_crop_falls_back_to_original_image(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        // Mock ML TANPA face_crop (wajah tidak terdeteksi / versi lama)
        $this->fakePredictionService('inflammatory acne');

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        $history = PredictionHistory::firstOrFail();

        // Fallback: gambar asli tersimpan
        $media = $history->getFirstMedia('scan-photo');
        $this->assertNotNull($media);
        $this->assertNotEquals('face_crop.jpg', $media->file_name);
    }

    public function test_upload_scan_with_invalid_base64_face_crop_falls_back(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        // base64 invalid → base64_decode strict mengembalikan false → fallback gambar asli
        $this->fakePredictionService('inflammatory acne', [
            'face_crop' => '!!!bukan-base64-valid!!!',
            'face_crop_format' => 'jpeg',
            'face_detected' => true,
        ]);

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        $history = PredictionHistory::firstOrFail();

        $media = $history->getFirstMedia('scan-photo');
        $this->assertNotNull($media);
        $this->assertNotEquals('face_crop.jpg', $media->file_name);
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

    public function test_scan_includes_treatment_and_skincare_recommendations(): void
    {
        $this->seedConcernContent();

        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService('inflammatory acne');

        $response = $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        // PERAWATAN = tips (skin_recommendations)
        $response->assertJsonCount(2, 'data.treatment_recommendations');
        $response->assertJsonPath('data.treatment_recommendations.0.title', 'Konsultasikan Bila Peradangan Memburuk');
        $response->assertJsonStructure([
            'data' => [
                'treatment_recommendations' => [
                    ['uuid', 'title', 'recommendation_text', 'priority_level'],
                ],
                'skincare_recommendations' => [
                    ['uuid', 'name', 'category', 'gender', 'key_ingredients', 'usage_instruction', 'warning', 'skin_type', 'doctor'],
                ],
            ],
        ]);

        // SKINCARE = produk (skincare_products)
        $response->assertJsonCount(2, 'data.skincare_recommendations');
        $response->assertJsonPath('data.skincare_recommendations.0.name', 'Cetaphil Gentle Skin Cleanser');
    }

    public function test_scan_unknown_class_returns_empty_recommendations(): void
    {
        $user = $this->makeUserWithProfile();
        Sanctum::actingAs($user);
        $this->fakePredictionService('unknown-class');

        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated()
            ->assertJsonCount(0, 'data.treatment_recommendations')
            ->assertJsonCount(0, 'data.skincare_recommendations');
    }

    public function test_scan_skincare_recommendations_respect_user_gender(): void
    {
        $this->seedConcernContent();

        // Concern 'wrinkles' punya 2 produk: Olay (perempuan) + L'Oreal (unisex)
        $maleUser = User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'laki_laki',
        ]);
        Sanctum::actingAs($maleUser);
        $this->fakePredictionService('wrinkles');

        $response = $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        // Laki-laki: hanya unisex, produk perempuan TIDAK muncul
        $response->assertJsonCount(1, 'data.skincare_recommendations');
        $response->assertJsonPath('data.skincare_recommendations.0.name', "L'Oreal Revitalift Filler Ampoule Serum");

        $femaleUser = User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ]);
        Sanctum::actingAs($femaleUser);

        $response = $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated();

        // Perempuan: produk perempuan + unisex (2 produk)
        $response->assertJsonCount(2, 'data.skincare_recommendations');
        $femaleProductNames = collect($response->json('data.skincare_recommendations'))->pluck('name');
        $this->assertTrue($femaleProductNames->contains('Olay Regenerist Micro-Sculpting Retinol 24 Night Cream'));
        $this->assertTrue($femaleProductNames->contains("L'Oreal Revitalift Filler Ampoule Serum"));
    }

    public function test_scan_skincare_recommendations_shows_all_genders_when_profile_missing(): void
    {
        $this->seedConcernContent();

        // User tanpa gender (tidak mungkin untuk scan pertama, tapi user lama bisa)
        $user = User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => null,
        ]);
        $user->predictionHistories()->create([
            'scan_mode' => 'upload',
            'predicted_class' => 'wrinkles',
            'confidence' => 0.9,
            'probabilities' => ['wrinkles' => 0.9],
            'severity_score' => 50,
            'severity_level' => 'medium',
            'model_used' => 'test',
        ]);
        Sanctum::actingAs($user);
        $this->fakePredictionService('wrinkles');

        // Gender null → tidak difilter, semua produk muncul
        $this->postJson('/api/v1/scans', [
            'image' => UploadedFile::fake()->image('face.jpg'),
        ])->assertCreated()
            ->assertJsonCount(2, 'data.skincare_recommendations');
    }

    private function seedConcernContent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(SkinConcernSeeder::class);
        $this->seed(SkinTypeSeeder::class);

        User::factory()->create(['email' => 'doctor@skincek.com'])->assignRole('doctor');

        $this->seed(SkinRecommendationSeeder::class);
        $this->seed(SkincareProductSeeder::class);
    }

    private function makeUserWithProfile(): User
    {
        return User::factory()->create([
            'date_of_birth' => '1995-05-15',
            'gender' => 'perempuan',
        ]);
    }

    private function fakePredictionService(?string $predictedClass = null, array $extraFields = []): void
    {
        $this->app->instance(SkinPredictionServiceContract::class, new class($predictedClass, $extraFields) implements SkinPredictionServiceContract
        {
            public function __construct(
                private ?string $predictedClass = null,
                private array $extraFields = [],
            ) {}

            public function predict(string $imagePath, bool $cropped = false, ?string $originalName = null): array
            {
                return array_merge([
                    'predicted_class' => $this->predictedClass ?? 'acne',
                    'confidence' => 0.91,
                    'probabilities' => [$this->predictedClass ?? 'acne' => 0.91],
                    'severity_score' => 0.73,
                    'severity_level' => 'high',
                    'model_used' => 'test-model',
                ], $this->extraFields);
            }
        });
    }
}
