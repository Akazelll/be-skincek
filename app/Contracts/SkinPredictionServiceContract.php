<?php

namespace App\Contracts;

interface SkinPredictionServiceContract
{
    public function predict(string $imagePath, bool $cropped = false, ?string $originalName = null): array;
}
