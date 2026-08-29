<?php

namespace App\Jobs;

use App\Contracts\AiChatServiceContract;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class GenerateAiReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public Message $userMessage,
    ) {}

    public function handle(AiChatServiceContract $ai): void
    {
        $bot = User::where('ai_bot', true)->first();

        if (! $bot) {
            return;
        }

        $history = $this->conversation->messages()
            ->where('id', '<', $this->userMessage->id)
            ->orderByDesc('id')
            ->limit((int) config('ai.max_history_messages', 8))
            ->get()
            ->reverse();

        $context = $history->map(fn (Message $message) => [
            'role' => $message->sender_id === $bot->id ? 'model' : 'user',
            'content' => (string) $message->content,
        ])->values()->all();

        $reply = $ai->answer((string) $this->userMessage->content, $context);

        $botMessage = $this->conversation->messages()->create([
            'sender_id' => $bot->id,
            'content' => $reply->answer,
            'type' => 'text',
        ]);

        MessageSent::dispatch($botMessage->load(['sender.roles', 'conversation']));

        Notification::send($this->conversation->user, new AppNotification(
            'Balasan dari '.config('ai.bot_name'),
            mb_strimwidth(strip_tags($reply->answer), 0, 120, '…'),
            ['conversation_id' => $this->conversation->uuid],
            notificationType: 'chat',
        ));
    }
}
