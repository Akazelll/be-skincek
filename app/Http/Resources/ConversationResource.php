<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'doctor' => $this->whenLoaded('doctor', fn () => new UserResource($this->doctor)),
            'message_count' => $this->message_count,
            'last_message' => $this->whenLoaded('messages', fn () => new MessageResource($this->messages->first())),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
