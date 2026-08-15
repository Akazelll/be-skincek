<?php

namespace App\Listeners;

use App\Contracts\PushNotificationServiceContract;
use App\Events\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendChatMessagePushNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PushNotificationServiceContract $push) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;

        if (! $conversation || ! $conversation->user || ! $conversation->doctor) {
            return;
        }

        $recipient = $conversation->user->id === $message->sender_id
            ? $conversation->doctor
            : $conversation->user;

        $this->push->sendToUser($recipient, 'Pesan baru', $message->content, [
            'type' => 'chat_message_received',
            'conversation_uuid' => $conversation->uuid,
            'message_uuid' => $message->uuid,
        ]);
    }
}
