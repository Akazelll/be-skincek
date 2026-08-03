<?php

return [
    'grace_period_days' => (int) env('ACCOUNT_DELETION_GRACE_DAYS', 30),

    'pro_lifetime' => [
        'name' => 'Pro',
        'price' => 15000,
        'currency' => 'IDR',
        'duration_label' => 'Lifetime',
        'benefits' => [
            'Chat tanpa batas dengan dokter',
            'Prioritas verifikasi dokter',
            'Semua fitur SkinCek Pro',
        ],
    ],
];
