<?php

namespace Database\Seeders;

use App\Models\SkinType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkinTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Berminyak', 'description' => 'Kulit menghasilkan minyak berlebih, pori-pori cenderung besar, rentan berjerawat.'],
            ['name' => 'Kering', 'description' => 'Kulit terasa kencang, kusam, dan kadang mengelupas. Minyak alami kulit rendah.'],
            ['name' => 'Kombinasi', 'description' => 'T-zone (dahi, hidung, dagu) berminyak, pipi kering atau normal.'],
            ['name' => 'Normal', 'description' => 'Kulit seimbang, tidak terlalu berminyak atau kering. Pori-pori kecil, tekstur halus.'],
            ['name' => 'Sensitif', 'description' => 'Kulit mudah iritasi, merah, atau gatal akibat produk atau perubahan lingkungan.'],
        ];

        foreach ($types as $type) {
            SkinType::firstOrCreate(
                ['name' => $type['name']],
                [
                    'uuid' => Str::uuid(),
                    'description' => $type['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
