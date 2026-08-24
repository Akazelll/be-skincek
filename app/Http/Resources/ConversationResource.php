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
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor ? new UserResource($this->doctor) : null),
            'message_count' => $this->message_count,
            'last_message' => $this->whenLoaded('messages', fn () => $this->messages->first() ? new MessageResource($this->messages->first()) : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
