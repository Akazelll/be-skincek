<?php

namespace App\Events;

use App\Enums\NotificationType;
use App\Models\Message;
use App\Support\MediaHelper;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return NotificationType::CHAT_MESSAGE_RECEIVED->value;
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        $media = $this->message->getFirstMedia('chat-media');

        return [
            'type' => NotificationType::CHAT_MESSAGE_RECEIVED->value,
            'category' => NotificationType::CHAT_MESSAGE_RECEIVED->category()->value,
            'conversation_uuid' => $this->message->conversation->uuid,
            'message' => [
                'uuid' => $this->message->uuid,
                'sender' => [
                    'uuid' => $sender->uuid,
                    'full_name' => $sender->full_name,
                    'role' => $sender->roles->first()?->name,
                ],
                'content' => $this->message->content,
                'message_type' => $this->message->type ?? 'text',
                'media_url' => MediaHelper::url($media),
                'created_at' => $this->message->created_at?->toISOString(),
            ],
        ];
    }
}
