<?php

namespace App\Services;

use App\Contracts\SkinPredictionServiceContract;
use Illuminate\Support\Facades\Http;

class HttpSkinPredictionService implements SkinPredictionServiceContract
{
    public function predict(string $imagePath, bool $cropped = false): array
    {
        $endpoint = $cropped ? '/predict-crop' : '/predict';

        return Http::baseUrl(config('services.ml.url'))
            ->timeout(config('services.ml.timeout'))
            ->retry(config('services.ml.retries'), 200)
            ->attach('file', fopen($imagePath, 'r'), basename($imagePath))
            ->post($endpoint)
            ->throw()
            ->json();
    }
}
