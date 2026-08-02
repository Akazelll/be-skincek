<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = (string) config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnapTransaction(array $params): object
    {
        return Snap::createTransaction($params);
    }

    public function verifySignature(string $signature, array $payload): bool
    {
        $expected = hash(
            'sha512',
            ($payload['order_id'] ?? '')
            . ($payload['status_code'] ?? '')
            . ($payload['gross_amount'] ?? '')
            . (string) config('services.midtrans.server_key')
        );

        return hash_equals($expected, $signature);
    }
}
