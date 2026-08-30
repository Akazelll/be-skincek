<?php

namespace Database\Seeders;

use App\Enums\ProductGender;
use App\Models\SkincareProduct;
use App\Models\SkinConcern;
use App\Models\SkinType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder Skincare Products — SKINCARE = PRODUK (bukan tips).
 *
 * Perawatan/tips ada di SkinRecommendationSeeder (tabel skin_recommendations).
 * Setiap concern mendapat 2 produk aktif.
 */
class SkincareProductSeeder extends Seeder
{
    public function run(): void
    {
        $doctor = User::where('email', 'doctor@skincek.com')->first();

        if (! $doctor) {
            $this->command?->warn('Doctor seed tidak ditemukan — SkincareProductSeeder dilewati.');

            return;
        }

        $oily = SkinType::where('name', 'Berminyak')->first();
        $dry = SkinType::where('name', 'Kering')->first();
        $combination = SkinType::where('name', 'Kombinasi')->first();
        $sensitive = SkinType::where('name', 'Sensitif')->first();

        $products = [
            'inflammatory acne' => [
                [
                    'name' => 'Cetaphil Gentle Skin Cleanser',
                    'category' => 'cleanser',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $sensitive,
                    'key_ingredients' => 'Water, Cetyl Alcohol, Propylene Glycol, Sodium Lauryl Sulfate, Stearyl Alcohol',
                    'usage_instruction' => 'Basahi wajah dengan air suhu ruang, tuang sedikit cleanser ke telapak tangan, lalu pijat lembut ke seluruh wajah selama 30-60 detik. Bilas hingga bersih dan keringkan dengan handuk bersih secara menepuk. Gunakan pagi dan malam hari.',
                    'warning' => 'Hindari kontak langsung dengan mata. Bila iritasi terjadi, hentikan penggunaan dan konsultasikan ke dokter.',
                ],
                [
                    'name' => 'Evyaphea Acnederm Gel 10g (Adapalene 0.1%)',
                    'category' => 'treatment',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $oily,
                    'key_ingredients' => 'Adapalene 0.1%',
                    'usage_instruction' => 'Aplikasikan lapisan tipis ke area jerawat sekali sehari setelah wajah bersih dan kering, idealnya pada malam hari. Mulai dengan penggunaan 2-3 malam seminggu, lalu tingkatkan bertahap sesuai toleransi kulit.',
                    'warning' => 'Ibu hamil dan menyusui wajib konsultasi dokter terlebih dahulu. Pada minggu pertama bisa muncul sedikit iritasi dan pengelupasan — reaksi normal. Gunakan sunscreen di pagi hari. Hindari paparan matahari langsung.',
                ],
            ],
            'non inflammatory acne black heads' => [
                [
                    'name' => 'Hadabiyeh BHA Exfoliating Serum (Salicylic Acid 2%)',
                    'category' => 'serum',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $oily,
                    'key_ingredients' => 'Salicylic Acid 2%, Niacinamide, Panthenol',
                    'usage_instruction' => 'Setelah membersihkan wajah pada malam hari, teteskan 3-4 tetes serum ke wajah dan leher, lalu tepuk perlahan hingga meresap. Gunakan 2-3 kali seminggu, lalu tingkatkan ke setiap malam bila kulit sudah beradaptasi.',
                    'warning' => 'Jangan digunakan bersamaan dengan retinoid pada malam yang sama. Wajib memakai sunscreen SPF 30+ di pagi hari. Bila muncul iritasi hebat, kurangi frekuensi penggunaan.',
                ],
                [
                    'name' => 'Innisfree Jeju Volcanic Clay Mask',
                    'category' => 'mask',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $combination,
                    'key_ingredients' => 'Jeju Volcanic Clay, Kaolin, Bentonite',
                    'usage_instruction' => 'Setelah cleanser, oleskan masker merata ke wajah (hindari area mata dan bibir) dengan ketebalan sedang. Diamkan 10-15 menit hingga sedikit mengering, lalu bilas dengan air hangat. Gunakan 1-2 kali seminggu pada area T-zone yang berkomedo.',
                    'warning' => 'Jangan dibiarkan hingga benar-benar kering karena dapat menyerap kelembapan kulit. Tidak untuk pemakaian setiap hari.',
                ],
            ],
            'non inflammatory acne white heads' => [
                [
                    'name' => 'Somethinc 10% Niacinamide + HA Serum',
                    'category' => 'serum',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $combination,
                    'key_ingredients' => 'Niacinamide 10%, Hyaluronic Acid, Zinc PCA',
                    'usage_instruction' => 'Gunakan pagi dan/atau malam setelah cleanser dan sebelum pelembap. Teteskan 3-4 tetes, tepuk merata ke seluruh wajah. Aman dipakai berdampingan dengan bahan aktif lain.',
                    'warning' => 'Bila kulit belum terbiasa dengan niacinamide tinggi, mulai dari 2-3 kali seminggu. Hentikan bila muncul kemerahan berlebih.',
                ],
                [
                    'name' => 'Ovale Facial Foam Untuk Kulit Berjerawat',
                    'category' => 'cleanser',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $oily,
                    'key_ingredients' => 'Tea Tree Oil, Salicylic Acid, Aloe Vera',
                    'usage_instruction' => 'Gunakan 2 kali sehari (pagi dan malam). Basahi wajah, busakan sabun di telapak tangan, lalu pijat lembut 30 detik dan bilas tuntas.',
                    'warning' => 'Bersihkan makeup terlebih dahulu dengan micellar water agar hasil pembersihan maksimal. Bila kulit terasa tertarik setelahnya, gunakan pelembap langsung.',
                ],
            ],
            'Redness' => [
                [
                    'name' => 'Azarine Calm Down Soothing Serum (Centella)',
                    'category' => 'serum',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $sensitive,
                    'key_ingredients' => 'Centella Asiatica 70%, Madecassoside, Panthenol, Allantoin',
                    'usage_instruction' => 'Gunakan pagi dan malam setelah cleanser. Teteskan ke wajah dan tepuk lembut. Fokus pada area yang kemerahan. Lanjutkan dengan pelembap soothing.',
                    'warning' => 'Formulasi ringan untuk kulit sensitif. Tetap lakukan patch test di belakang telinga sebelum pemakaian pertama.',
                ],
                [
                    'name' => 'Sensatia Botanicals Calendula Comfort Cream',
                    'category' => 'moisturizer',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $sensitive,
                    'key_ingredients' => 'Calendula Extract, Shea Butter, Jojoba Oil, Chamomile',
                    'usage_instruction' => 'Aplikasikan ke wajah dan leher setelah serum, pagi dan malam hari. Pijat dengan gerakan melingkar lembut tanpa menarik kulit.',
                    'warning' => 'Produk mengandung minyak alami — pastikan kulit sudah bersih sebelum penggunaan. Simpan di tempat sejuk dan kering.',
                ],
            ],
            'dark spots' => [
                [
                    'name' => 'Wardah UV Shield Essence Sunscreen SPF 50',
                    'category' => 'sunscreen',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $combination,
                    'key_ingredients' => 'Ethylhexyl Methoxycinnamate, Vitamin E, Aloe Vera',
                    'usage_instruction' => 'Aplikasikan 15 menit sebelum aktivitas luar ruangan pada langkah terakhir rutinitas pagi. Aplikasikan ulang setiap 3-4 jam, terutama setelah berkeringat atau terkena air.',
                    'warning' => 'Takaran cukup 2 jari (2 finger rule) agar perlindungan optimal. Sunscreen adalah langkah wajib agar flek tidak makin gelap.',
                ],
                [
                    'name' => 'The Ordinary Alpha Arbutin 2% + HA',
                    'category' => 'serum',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $combination,
                    'key_ingredients' => 'Alpha Arbutin 2%, Hyaluronic Acid',
                    'usage_instruction' => 'Gunakan malam hari setelah cleanser, sebelum pelembap. Teteskan beberapa tetes ke wajah area flek. Bisa digunakan setiap malam secara konsisten.',
                    'warning' => 'Bila digunakan dengan eksfoliasi AHA, gunakan bergantian malam. Wajib sunscreen pagi hari. Hasil terlihat setelah 8-12 minggu pemakaian rutin.',
                ],
            ],
            'pigmentation' => [
                [
                    'name' => 'ElsheSkin 15% Vitamin C Brightening Serum',
                    'category' => 'serum',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $combination,
                    'key_ingredients' => 'Sodium Ascorbyl Phosphate 15%, Ferulic Acid, Vitamin E',
                    'usage_instruction' => 'Gunakan pada pagi hari setelah cleanser dan sebelum sunscreen. Teteskan 3-4 tetes dan tepuk merata. Mulai dari hari genap untuk kulit sensitif.',
                    'warning' => 'Simpan di tempat sejuk terhindar dari sinar matahari langsung. Bila warna serum berubah kecoklatan pekat, produk telah teroksidasi — hentikan penggunaan.',
                ],
                [
                    'name' => ' Naturium Azelaic Topical Acid 10%',
                    'category' => 'treatment',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $sensitive,
                    'key_ingredients' => 'Azelaic Acid 10%, Niacinamide, Squalane',
                    'usage_instruction' => 'Aplikasikan lapisan tipis ke seluruh wajah atau area pigmentasi pada malam hari setelah kulit bersih. Gunakan setiap malam bila sudah toleran.',
                    'warning' => 'Bisa dipakai hamil dan menyusui (konsultasi tetap disarankan). Kombinasi dengan retinoid sebaiknya dipisah jadwal pagi/malam.',
                ],
            ],
            'pores' => [
                [
                    'name' => 'Hadalabo Gokujyun Hyaluronic Acid Cleansing Foam',
                    'category' => 'cleanser',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $combination,
                    'key_ingredients' => 'Hyaluronic Acid, Acetyl Hyaluronate',
                    'usage_instruction' => 'Basahi wajah, ambil sedang busakan dengan sedikit air, lalu pijat lembut 30-60 detik dan bilas. Gunakan pagi dan malam. Jangan menggosok berlebihan pada area pori besar.',
                    'warning' => 'Untuk makeup tebal, lakukan double cleansing dengan micellar water terlebih dahulu.',
                ],
                [
                    'name' => 'Somethinc Pore-rection Brightening Clay Mask',
                    'category' => 'mask',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $oily,
                    'key_ingredients' => 'Kaolin, Salicylic Acid, Witch Hazel, Niacinamide',
                    'usage_instruction' => 'Oleskan ke wajah bersih khususnya area T-zone, diamkan 10 menit (jangan sampai benar-benar kering), lalu bilas. Gunakan maksimal 2 kali seminggu.',
                    'warning' => 'Hindari penggunaan bersamaan dengan eksfoliasi kimia lain pada hari yang sama agar barrier kulit tetap terjaga.',
                ],
            ],
            'wrinkles' => [
                [
                    'name' => 'Olay Regenerist Micro-Sculpting Retinol 24 Night Cream',
                    'category' => 'night cream',
                    'gender' => ProductGender::PEREMPUAN,
                    'skin_type' => $dry,
                    'key_ingredients' => 'Retinol, Niacinamide, Peptide Complex, Hyaluronic Acid',
                    'usage_instruction' => 'Gunakan malam hari pada langkah terakhir rutinitas. Ambil ukuran kacang, aplikasikan merata ke wajah dan leher. Awali 2-3 malam seminggu lalu tingkatkan.',
                    'warning' => 'Retinol meningkatkan sensitivitas matahari — sunscreen pagi wajib. Ibu hamil/menyusui tidak dianjurkan. Bila iritasi, hentikan sementara lalu lanjutkan dengan frekuensi lebih rendah.',
                ],
                [
                    'name' => "L'Oreal Revitalift Filler Ampoule Serum",
                    'category' => 'serum',
                    'gender' => ProductGender::UNISEX,
                    'skin_type' => $dry,
                    'key_ingredients' => 'Hyaluronic Acid 1.5%, Vitamin CG (Vitamin C Derivative)',
                    'usage_instruction' => 'Gunakan pagi dan malam setelah cleanser. Teteskan ke wajah dan leher, tepuk hingga meresap sebelum lanjut ke pelembap.',
                    'warning' => 'Aman untuk semua jenis kulit termasuk kulit sensitif. Tetap pasangkan dengan sunscreen di pagi hari untuk hasil optimal.',
                ],
            ],
        ];

        foreach ($products as $mlLabel => $items) {
            $concern = SkinConcern::where('ml_label', $mlLabel)->first();

            if (! $concern) {
                $this->command?->warn("Skin concern '{$mlLabel}' tidak ditemukan — produk dilemma.");

                continue;
            }

            foreach ($items as $item) {
                SkincareProduct::firstOrCreate(
                    ['concern_id' => $concern->id, 'name' => $item['name']],
                    [
                        'uuid' => (string) Str::uuid(),
                        'doctor_id' => $doctor->id,
                        'skin_type_id' => $item['skin_type']?->id,
                        'category' => $item['category'],
                        'gender' => $item['gender'],
                        'key_ingredients' => $item['key_ingredients'],
                        'usage_instruction' => $item['usage_instruction'],
                        'warning' => $item['warning'],
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command?->info('Skincare products seeded: '.SkincareProduct::count().' produk.');
    }
}
