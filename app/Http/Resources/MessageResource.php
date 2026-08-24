<?php

namespace App\Http\Resources;

use App\Support\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = $this->getFirstMedia('chat-media');

        $data = [
            'uuid' => $this->uuid,
            'sender' => $this->sender ? [
                'uuid' => $this->sender->uuid,
                'full_name' => $this->sender->full_name,
                'role' => $this->sender->roles->first()?->name,
            ] : null,
            'content' => $this->content,
            'type' => $this->type ?? 'text',
            'media_url' => MediaHelper::url($media),
            'created_at' => $this->created_at?->toISOString(),
        ];

        if ($this->type === 'scan_result' && $this->relationLoaded('predictionHistory') && $this->predictionHistory) {
            $ph = $this->predictionHistory;
            $data['prediction_history'] = [
                'uuid' => $ph->uuid,
                'predicted_class' => $ph->predicted_class,
                'confidence' => (float) $ph->confidence,
                'severity_level' => $ph->severity_level->value,
                'scan_mode' => $ph->scan_mode->value,
                'created_at' => $ph->created_at?->toISOString(),
            ];
        }

        return $data;
    }
}
