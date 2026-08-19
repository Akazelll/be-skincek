<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class UuidPathGenerator extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        $modelKey = $media->model?->uuid ?? $media->model?->getKey() ?? 'unknown';
        $mediaKey = $media->uuid ?? $media->getKey();

        return Str::snake(class_basename($media->model_type)).'/'.$modelKey.'/'.$mediaKey;
    }
}
