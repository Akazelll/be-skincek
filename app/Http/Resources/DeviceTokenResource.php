<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'fcm_token' => $this->fcm_token,
            'platform' => $this->platform->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
