<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkinRecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'recommendation_text' => $this->recommendation_text,
            'priority_level' => $this->priority_level->value,
            'is_active' => $this->is_active,
            'concern' => SkinConcernResource::make($this->whenLoaded('concern')),
            'product' => SkincareProductResource::make($this->whenLoaded('product')),
            'doctor' => UserResource::make($this->whenLoaded('doctor')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
