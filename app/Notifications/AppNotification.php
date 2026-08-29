<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly array $data = [],
        public readonly string $notificationType = 'general',
    ) {}

    public function via(object $notifiable): array
    {
        // 'broadcast' mengirim event BroadcastNotificationCreated ke channel
        // privat user (lihat User::receivesBroadcastNotificationsOn) agar
        // notification bell frontend menerima notifikasi realtime via Reverb.
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'notification_type' => $this->notificationType,
            ...$this->data,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
