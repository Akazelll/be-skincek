<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkincareProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'gender' => $this->gender?->value ?? 'unisex',
            'key_ingredients' => $this->key_ingredients,
            'usage_instruction' => $this->usage_instruction,
            'warning' => $this->warning,
            'is_active' => $this->is_active,
            'concern' => SkinConcernResource::make($this->whenLoaded('concern')),
            'skin_type' => SkinTypeResource::make($this->whenLoaded('skinType')),
            'doctor' => UserResource::make($this->whenLoaded('doctor')),
        ];
    }
}
