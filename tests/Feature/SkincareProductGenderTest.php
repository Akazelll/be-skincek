<?php

namespace Tests\Feature;

use App\Models\SkincareProduct;
use App\Models\SkinConcern;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkincareProductGenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function approvedDoctor(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $doctor->doctorVerification()->create([
            'specialization' => 'Dermatology',
            'verification_status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $doctor;
    }

    public function test_doctor_can_create_product_with_gender(): void
    {
        $doctor = $this->approvedDoctor();
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);
        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/skincare-products', [
            'concern_id' => $concern->id,
            'name' => 'Men Cleanser',
            'category' => 'Cleanser',
            'gender' => 'laki_laki',
            'usage_instruction' => 'Pakai pagi dan malam.',
        ])->assertCreated()
            ->assertJsonPath('data.gender', 'laki_laki');
    }

    public function test_product_defaults_to_unisex_when_gender_not_specified(): void
    {
        $doctor = $this->approvedDoctor();
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);
        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/skincare-products', [
            'concern_id' => $concern->id,
            'name' => 'Universal Cream',
            'category' => 'Moisturizer',
            'usage_instruction' => 'Oles tipis.',
        ])->assertCreated()
            ->assertJsonPath('data.gender', 'unisex');
    }

    public function test_invalid_gender_is_rejected(): void
    {
        $doctor = $this->approvedDoctor();
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);
        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/skincare-products', [
            'concern_id' => $concern->id,
            'name' => 'Bad Product',
            'category' => 'Cream',
            'gender' => 'binatang',
            'usage_instruction' => 'Test.',
        ])->assertUnprocessable();
    }

    public function test_products_can_be_filtered_by_gender(): void
    {
        $doctor = $this->approvedDoctor();
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);

        SkincareProduct::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'name' => 'Men Product',
            'category' => 'Serum',
            'gender' => 'laki_laki',
            'usage_instruction' => 'Test.',
        ]);
        SkincareProduct::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'name' => 'Women Product',
            'category' => 'Serum',
            'gender' => 'perempuan',
            'usage_instruction' => 'Test.',
        ]);
        SkincareProduct::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'name' => 'Unisex Product',
            'category' => 'Cream',
            'gender' => 'unisex',
            'usage_instruction' => 'Test.',
        ]);

        $this->getJson('/api/v1/skincare-products?gender=laki_laki')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Men Product');

        $this->getJson('/api/v1/skincare-products?gender=perempuan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Women Product');

        $this->getJson('/api/v1/skincare-products?gender=unisex')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Unisex Product');
    }

    public function test_doctor_can_update_product_gender(): void
    {
        $doctor = $this->approvedDoctor();
        $concern = SkinConcern::create(['name' => 'Acne', 'ml_label' => 'acne']);
        $product = SkincareProduct::create([
            'doctor_id' => $doctor->id,
            'concern_id' => $concern->id,
            'name' => 'Unisex Cream',
            'category' => 'Cream',
            'gender' => 'unisex',
            'usage_instruction' => 'Test.',
        ]);
        Sanctum::actingAs($doctor);

        $this->patchJson("/api/v1/skincare-products/{$product->uuid}", [
            'gender' => 'perempuan',
        ])->assertOk()
            ->assertJsonPath('data.gender', 'perempuan');
    }
}
