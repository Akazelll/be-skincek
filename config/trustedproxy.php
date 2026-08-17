<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | IP/CIDR proxy yang dipercaya (ngrok, Cloudflare, load balancer, dll).
    | Isi '*' untuk mempercayai semua proxy (praktis untuk development),
    | atau daftar IP/CIDR dipisah koma untuk produksi.
    | Contoh: '10.0.0.0/8,100.64.0.0/10'
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
