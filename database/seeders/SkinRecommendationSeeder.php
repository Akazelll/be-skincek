<?php

namespace Database\Seeders;

use App\Enums\PriorityLevel;
use App\Models\SkinConcern;
use App\Models\SkinRecommendation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkinRecommendationSeeder extends Seeder
{
    public function run(): void
    {
        $doctor = User::where('email', 'doctor@skincek.com')->first();

        if (! $doctor) {
            $this->command?->warn('Doctor seed tidak ditemukan — SkinRecommendationSeeder dilewati.');

            return;
        }

        $recommendations = [
            'inflammatory acne' => [
                [
                    'title' => 'Rutinitas Pembersihan Ganda yang Lembut',
                    'text' => 'Bersihkan wajah dua kali sehari dengan pembersih berbahan dasar air (gentle cleanser) yang mengandung salicylic acid rendah atau tanpa bahan iritasi. Hindari mencuci wajah berlebihan dan jangan pernah memencet jerawat meradang karena dapat memperberat peradangan dan meninggalkan bekas luka. Keringkan wajah dengan handuk bersih secara menepuk, bukan digosok.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
                [
                    'title' => 'Konsultasikan Bila Peradangan Memburuk',
                    'text' => 'Jerawat meradang yang besar, nyeri, atau terus bertambah dalam jumlah bisa memerlukan penanganan medis seperti benzoyl peroxide, adapalene, atau antibiotik topikal dari dokter kulit. Jika tidak membaik setelah 4-6 minggu perawatan mandiri, segera lakukan konsultasi untuk mencegah bekas luka permanen.',
                    'priority' => PriorityLevel::HIGH,
                ],
            ],
            'non inflammatory acne black heads' => [
                [
                    'title' => 'Eksfoliasi Rutin dengan BHA',
                    'text' => 'Gunakan produk dengan salicylic acid (BHA) 1-2% sebanyak 2-3 kali seminggu di malam hari. BHA mampu menembus minyak di dalam pori dan melarutkan penyumbatan penyebab komedo hitam. Selalu gunakan pelembap setelahnya dan jangan lupa sunscreen di pagi hari karena kulit menjadi lebih sensitif terhadap matahari.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
                [
                    'title' => 'Hentikan Kebiasaan Pemicu Komedo',
                    'text' => 'Hindari memencet atau mengambil komedo secara paksa karena dapat merusak pori dan memicu peradangan. Bersihkan wajah tuntas setelah menggunakan makeup, rutin mengganti sarung bantal, dan pilih produk skincare serta kosmetik berlabel non-comedogenic agar pori-pori tidak semakin tersumbat.',
                    'priority' => PriorityLevel::LOW,
                ],
            ],
            'non inflammatory acne white heads' => [
                [
                    'title' => 'Pilih Produk Non-Komedogenik',
                    'text' => 'Komedo putih terbentuk dari pori yang tertutup rapat, jadi pastikan seluruh produk yang kamu pakai (pelembap, sunscreen, hingga makeup) berlabel non-comedogenic dan oil-free. Bersihkan wajah dua kali sehari dengan gentle cleanser dan hindari pemakaian produk terlalu berlapis yang dapat menutup pori.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
                [
                    'title' => 'Retinoid untuk Cegah Penyumbatan Pori',
                    'text' => 'Retinoid topikal seperti adapalene 0,1% membantu mempercepat regenerasi sel kulit sehingga pori tidak mudah tersumbat. Mulai gunakan 2-3 malam seminggu pada kulit kering, lalu tingkatkan bertahap. Ibu hamil dan menyusui harus berkonsultasi dengan dokter sebelum menggunakan retinoid.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
            ],
            'Redness' => [
                [
                    'title' => 'Tenangkan Kulit dengan Bahan Soothing',
                    'text' => 'Gunakan pelembap yang mengandung bahan menenangkan seperti centella asiatica, niacinamide, panthenol, atau allantoin untuk meredakan kemerahan dan memperkuat barrier kulit. Hindari produk dengan alkohol denat, pewangi, dan exfoliant keras sampai kondisi kulit membaik.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
                [
                    'title' => 'Kenali dan Hindari Pemicu Iritasi',
                    'text' => 'Cuci wajah dengan air hangar suhu ruang (bukan panas), hindari scrub fisik, dan lindungi kulit dengan sunscreen mineral berbahan zinc oxide atau titanium dioxide yang lebih lembut bagi kulit sensitif. Bila kemerahan disertai rasa panas hebat, gatal parah, atau muncul lepas sinar matahari, konsultasikan ke dokter kulit karena bisa menandakan kondisi seperti rosacea atau dermatitis.',
                    'priority' => PriorityLevel::HIGH,
                ],
            ],
            'dark spots' => [
                [
                    'title' => 'Sunscreen Adalah Kunci Utama',
                    'text' => 'Flek hitam akan semakin gelap jika kulit terus terpapar sinar UV. Gunakan sunscreen SPF 30+ setiap pagi dan aplikasikan ulang setiap 3-4 jam saat beraktivitas di luar ruangan. Tanpa proteksi matahari yang konsisten, semua produk pencerah akan sia-sia.',
                    'priority' => PriorityLevel::HIGH,
                ],
                [
                    'title' => 'Rutinitas Pencerah Malam Hari',
                    'text' => 'Di malam hari, gunakan produk dengan bahan terbukti mencerahkan seperti vitamin C, niacinamide, alpha arbutin, atau azelaic acid. Kombinasikan dengan eksfoliasi lembut AHA 1-2 kali seminggu untuk membantu mengangkat sel kulit yang mengandung melanin berlebih. Hasil terlihat konsisten setelah 8-12 minggu penggunaan rutin.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
            ],
            'pigmentation' => [
                [
                    'title' => 'Proteksi Matahari Menyeluruh',
                    'text' => 'Pigmentasi dipicu oleh produksi melanin berlebih yang diperparah paparan UV. Selain sunscreen SPF 30-50, gunakan pelindung tambahan seperti topi atau payung saat berada di luar. Pagi hari, antioksidan seperti vitamin C 10-15% dapat meningkatkan perlindungan sekaligus meratakan warna kulit.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
                [
                    'title' => 'Bahan Aktif Perata Warna Kulit',
                    'text' => 'Niacinamide 5%, azelaic acid 10%, dan tranexamic acid adalah pilihan aman untuk mencerahkan hiperpigmentasi tanpa iritasi berlebihan. Gunakan satu bahan aktif baru pada satu waktu agar kulit beradaptasi, dan bersabarlah — pigmentasi butuh konsistensi berbulan-bulan untuk pudar secara alami.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
            ],
            'pores' => [
                [
                    'title' => 'Jaga Produksi Minyak Tetap Seimbang',
                    'text' => 'Pori-pori tampak besar ketika tersumbat sebum. Gunakan cleanser lembut dua kali sehari, eksfoliasi BHA 1-2 kali seminggu, dan pelembap oil-free berbahan gel. Jangan mencuci wajah terlalu sering karena kulit yang terlalu kering justru memproduksi lebih banyak minyak.',
                    'priority' => PriorityLevel::LOW,
                ],
                [
                    'title' => 'Perhatian Ekstra pada Area T-Zone',
                    'text' => 'Area dahi, hidung, dan dagu biasanya paling berminyak sehingga pori-porinya paling menonjol. Gunakan masker clay sekali seminggu pada area tersebut dan pastikan makeup dibersihkan tuntas setiap malam dengan double cleansing. Ukuran pori bersifat genetik, namun pori yang bersih dan tidak tersumbat akan tampak jauh lebih halus.',
                    'priority' => PriorityLevel::LOW,
                ],
            ],
            'wrinkles' => [
                [
                    'title' => 'Mulai Retinol Secara Bertahap',
                    'text' => 'Retinol terbukti ilmiah merangsang produksi kolagen dan menghaluskan garis halus. Mulailah dengan konsentrasi rendah (0,025%-0,3%) dua malam seminggu, lalu tingkatkan perlahan. Selalu pasangkan dengan pelembap dan jadikan sunscreen sebagai langkah wajib di pagi hari.',
                    'priority' => PriorityLevel::MEDIUM,
                ],
                [
                    'title' => 'Antioksidan & Gaya Hidup Sehat',
                    'text' => 'Vitamin C di pagi hari melindungi kulit dari radikal bebas penyebab penuaan dini. Dukung dari dalam: tidur cukup 7-8 jam, minum air yang cukup, kelola stres, dan berhenti merokok. Kolagen mulai menurun sejak usia 25-an, jadi pencegahan dini jauh lebih efektif daripada perawatan belakangan.',
                    'priority' => PriorityLevel::LOW,
                ],
            ],
        ];

        foreach ($recommendations as $mlLabel => $items) {
            $concern = SkinConcern::where('ml_label', $mlLabel)->first();

            if (! $concern) {
                $this->command?->warn("Skin concern '{$mlLabel}' tidak ditemukan — dilewati.");

                continue;
            }

            foreach ($items as $item) {
                SkinRecommendation::firstOrCreate(
                    ['concern_id' => $concern->id, 'title' => $item['title']],
                    [
                        'uuid' => (string) Str::uuid(),
                        'doctor_id' => $doctor->id,
                        'product_id' => null,
                        'recommendation_text' => $item['text'],
                        'priority_level' => $item['priority'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
