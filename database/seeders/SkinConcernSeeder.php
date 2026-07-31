<?php

namespace Database\Seeders;

use App\Models\SkinConcern;
use Illuminate\Database\Seeder;

class SkinConcernSeeder extends Seeder
{
    public function run(): void
    {
        $concerns = [
            ['name' => 'Jerawat Meradang', 'ml_label' => 'inflammatory acne', 'default_severity_score' => 85],
            ['name' => 'Komedo Hitam', 'ml_label' => 'non inflammatory acne black heads', 'default_severity_score' => 50],
            ['name' => 'Komedo Putih', 'ml_label' => 'non inflammatory acne white heads', 'default_severity_score' => 50],
            ['name' => 'Kemerahan', 'ml_label' => 'Redness', 'default_severity_score' => 60],
            ['name' => 'Flek Hitam', 'ml_label' => 'dark spots', 'default_severity_score' => 40],
            ['name' => 'Pigmentasi', 'ml_label' => 'pigmentation', 'default_severity_score' => 40],
            ['name' => 'Pori-pori Besar', 'ml_label' => 'pores', 'default_severity_score' => 30],
            ['name' => 'Kerutan', 'ml_label' => 'wrinkles', 'default_severity_score' => 30],
        ];

        foreach ($concerns as $concern) {
            SkinConcern::firstOrCreate(
                ['ml_label' => $concern['ml_label']],
                [
                    'name' => $concern['name'],
                    'default_severity_score' => $concern['default_severity_score'],
                    'is_active' => true,
                ]
            );
        }
    }
}
