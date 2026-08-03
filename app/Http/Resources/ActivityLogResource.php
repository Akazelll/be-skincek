<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'properties' => $this->properties,
            'causer' => $this->whenLoaded('causer', fn () => $this->causer ? [
                'uuid' => $this->causer->uuid,
                'full_name' => $this->causer->full_name,
                'email' => $this->causer->email,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
