<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Str;

class UuidPathGenerator extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        $modelKey = $media->model?->uuid ?? $media->model?->getKey() ?? 'unknown';
        $mediaKey = $media->uuid ?? $media->getKey();

        return Str::snake(class_basename($media->model_type)).'/'.$modelKey.'/'.$mediaKey;
    }
}
