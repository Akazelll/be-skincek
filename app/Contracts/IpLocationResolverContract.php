<?php

namespace App\Contracts;

interface IpLocationResolverContract
{
    /**
     * Resolve perkiraan lokasi (kota/region/negara) dari sebuah IP address.
     *
     * @return array{city: string, region: ?string, country: string}|null
     */
    public function resolve(?string $ip): ?array;
}
