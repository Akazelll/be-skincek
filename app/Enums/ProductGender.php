<?php

namespace App\Enums;

enum ProductGender: string
{
    case LAKI_LAKI = 'laki_laki';
    case PEREMPUAN = 'perempuan';
    case UNISEX = 'unisex';

    public function label(): string
    {
        return match ($this) {
            self::LAKI_LAKI => 'Laki-laki',
            self::PEREMPUAN => 'Perempuan',
            self::UNISEX => 'Unisex',
        };
    }
}
