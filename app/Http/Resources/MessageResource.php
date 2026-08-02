<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'sender' => [
                'uuid' => $this->sender->uuid,
                'full_name' => $this->sender->full_name,
                'role' => $this->sender->roles->first()?->name,
            ],
            'content' => $this->content,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
