<?php

namespace Database\Seeders;

use App\Models\SkinConcern;
use Illuminate\Database\Seeder;

class SkinConcernSeeder extends Seeder
{
    public function run(): void
    {
        $concerns = [
            [
                'name' => 'Kemerahan',
                'ml_label' => 'Redness',
                'description' => 'Kemerahan pada kulit wajah dapat disebabkan oleh berbagai faktor seperti peradangan, iritasi, paparan sinar matahari berlebih, atau reaksi alergi. Kondisi ini ditandai dengan warna kemerahan yang tidak merata pada area kulit tertentu. Kemerahan yang persisten perlu dievaluasi lebih lanjut untuk mengidentifikasi penyebab pastinya.',
                'default_severity_score' => 60,
            ],
            [
                'name' => 'Flek Hitam',
                'ml_label' => 'dark spots',
                'description' => 'Flek hitam atau dark spots adalah area kulit yang mengalami hiperpigmentasi akibat produksi melanin berlebih. Kondisi ini bisa disebabkan oleh paparan sinar UV, bekas jerawat, perubahan hormonal, atau penuaan. Flek hitam umumnya tidak berbahaya tetapi dapat mempengaruhi penampilan.',
                'default_severity_score' => 40,
            ],
            [
                'name' => 'Jerawat Meradang',
                'ml_label' => 'inflammatory acne',
                'description' => 'Jerawat meradang adalah jenis jerawat yang disertai peradangan, ditandai dengan benjolan merah, bengkak, dan terasa nyeri. Kondisi ini terjadi ketika folikel rambut tersumbat oleh minyak dan sel kulit mati serta terinfeksi bakteri. Peradangan dapat mempengaruhi jaringan kulit yang lebih dalam dan berpotensi meninggalkan bekas luka jika tidak ditangani dengan tepat.',
                'default_severity_score' => 85,
            ],
            [
                'name' => 'Komedo Hitam',
                'ml_label' => 'non inflammatory acne black heads',
                'description' => 'Komedo hitam (blackhead) adalah jenis komedo terbuka yang muncul akibat penyumbatan pori-pori oleh sebum dan sel kulit mati. Ujung komedo berwarna hitam karena oksidasi melanin dengan udara, bukan karena kotoran. Kondisi ini termasuk jerawat non-inflamasi dan umumnya tidak disertai rasa nyeri.',
                'default_severity_score' => 50,
            ],
            [
                'name' => 'Komedo Putih',
                'ml_label' => 'non inflammatory acne white heads',
                'description' => 'Komedo putih (whitehead) adalah jenis komedo tertutup yang terbentuk ketika pori-pori tersumbat oleh sebum dan sel kulit mati namun tidak terpapar udara. Komedo putih muncul sebagai benjolan kecil berwarna putih atau cerah di bawah permukaan kulit dan termasuk kategori jerawat non-inflamasi.',
                'default_severity_score' => 50,
            ],
            [
                'name' => 'Pigmentasi',
                'ml_label' => 'pigmentation',
                'description' => 'Pigmentasi adalah kondisi di mana beberapa area kulit menjadi lebih gelap dibandingkan kulit di sekitarnya akibat produksi melanin yang berlebih. Kondisi ini bisa disebabkan oleh paparan sinar matahari, peradangan jerawat, atau perubahan hormonal. Pigmentasi umumnya tidak berbahaya tetapi dapat mempengaruhi penampilan.',
                'default_severity_score' => 40,
            ],
            [
                'name' => 'Pori-pori Besar',
                'ml_label' => 'pores',
                'description' => 'Pori-pori besar adalah kondisi di mana pori-pori kulit terlihat lebih melebar dari ukuran normal. Hal ini sering terjadi pada area T-zone (dahi, hidung, dagu) dan bisa dipengaruhi oleh faktor genetik, usia, paparan sinar matahari, atau produksi minyak berlebih. Pori-pori besar tidak berbahaya tetapi dapat mempengaruhi tekstur kulit.',
                'default_severity_score' => 30,
            ],
            [
                'name' => 'Kerutan',
                'ml_label' => 'wrinkles',
                'description' => 'Kerutan adalah garis-garis halus atau lipatan pada kulit yang muncul sebagai bagian dari proses penuaan alami. Faktor seperti paparan sinar UV, polusi, kebiasaan merokok, dan penurunan kolagen dapat mempercepat munculnya kerutan. Kondisi ini umumnya terjadi pada area wajah, leher, dan tangan.',
                'default_severity_score' => 30,
            ],
        ];

        foreach ($concerns as $concern) {
            SkinConcern::updateOrCreate(
                ['ml_label' => $concern['ml_label']],
                [
                    'name' => $concern['name'],
                    'description' => $concern['description'],
                    'default_severity_score' => $concern['default_severity_score'],
                    'is_active' => true,
                ]
            );
        }
    }
}
