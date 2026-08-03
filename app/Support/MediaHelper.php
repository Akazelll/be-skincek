<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaHelper
{
    public static function url(?Media $media): ?string
    {
        if (! $media) {
            return null;
        }

        return in_array($media->disk, ['local', 'public'], true)
            ? $media->getFullUrl()
            : $media->getTemporaryUrl(now()->addMinutes(60));
    }
}
