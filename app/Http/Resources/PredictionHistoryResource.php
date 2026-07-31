<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'scan_mode' => $this->scan_mode->value,
            'predicted_class' => $this->predicted_class,
            'confidence' => (float) $this->confidence,
            'probabilities' => $this->probabilities,
            'severity_score' => $this->severity_score,
            'severity_level' => $this->severity_level->value,
            'model_used' => $this->model_used,
            'image_url' => $this->getFirstMediaUrl('image'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
