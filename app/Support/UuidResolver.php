<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolver identifier publik (uuid) menjadi ID internal bigint.
 * Dipakai controller yang menerima relasi via field *_id dari klien,
 * karena semua Resource hanya mengekspos uuid (bukan id).
 */
class UuidResolver
{
    /**
     * @param  class-string<Model>  $modelClass  Model dengan trait HasPublicUuid.
     * @param  mixed  $identifier  Bisa berupa uuid, id internal numerik, atau null.
     * @return int|null ID internal atau null jika tidak ditemukan/kosong.
     */
    public static function resolve(string $modelClass, mixed $identifier): ?int
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $identifier = (string) $identifier;

        // Sudah berupa ID internal numerik — biarkan validasi exists memeriksa.
        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        /** @var Model|null $found */
        $found = $modelClass::query()->where('uuid', $identifier)->first();

        return $found?->getKey();
    }
}
