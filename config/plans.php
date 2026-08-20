<?php

return [
    'grace_period_days' => (int) env('ACCOUNT_DELETION_GRACE_DAYS', 30),

    'pro_monthly' => [
        'name' => 'Pro',
        'price' => 15000,
        'currency' => 'IDR',
        'period' => 'monthly',
        'duration_days' => 30,
        'duration_label' => '1 Bulan',
        'benefits' => [
            'Chat tanpa batas dengan dokter',
            'Aura Skin (AI) tanpa batas',
            'Prioritas verifikasi dokter',
            'Semua fitur SkinCek Pro',
        ],
    ],
];
