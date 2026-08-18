<?php

namespace App\Enums;

enum Gender: string
{
    case LAKI_LAKI = 'laki_laki';
    case PEREMPUAN = 'perempuan';

    public function label(): string
    {
        return match ($this) {
            self::LAKI_LAKI => 'Laki-laki',
            self::PEREMPUAN => 'Perempuan',
        };
    }
}
