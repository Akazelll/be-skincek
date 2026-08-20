<?php

namespace App\Http\Resources;

use App\Support\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $threshold = (float) config('services.ml.confidence_threshold', 0.50);

        return [
            'uuid' => $this->uuid,
            'scan_mode' => $this->scan_mode->value,
            'predicted_class' => $this->predicted_class,
            'confidence' => (float) $this->confidence,
            'probabilities' => $this->probabilities,
            'severity_score' => $this->severity_score,
            'severity_level' => $this->severity_level->value,
            'model_used' => $this->model_used,
            'image_url' => MediaHelper::url(
                $this->getFirstMedia('scan-photo') ?? $this->getFirstMedia('scan-photo-cropped')
            ),
            'disclaimer' => config('services.ml.disclaimer'),
            'notice' => (float) $this->confidence < $threshold
                ? 'Hasil prediksi ini memiliki tingkat keyakinan rendah. Sebaiknya lakukan scan ulang dengan pencahayaan yang lebih baik atau konsultasikan langsung dengan dokter kulit.'
                : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
