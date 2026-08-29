<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event yang di-broadcast ke Laravel Reverb saat notifikasi baru dibuat.
 *
 * FE listen ke:
 *   Echo.private(`user.${uuid}`).listen('.notification.new', callback)
 *
 * Implements ShouldBroadcastNow agar dikirim secara instan (synchronous) ke Reverb,
 * tanpa delay polling antrean queue database.
 */
class NotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly Notification $notification
    ) {}

    /**
     * Channel tujuan broadcast — private per user (pakai UUID).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->notification->user->uuid),
        ];
    }

    /**
     * Nama event custom — FE listen ke '.notification.new'.
     * Pakai dot-prefix di Echo agar tidak di-prepend namespace.
     */
    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    /**
     * Data yang dikirim ke FE via WebSocket.
     * Format identik dengan NotificationResource untuk konsistensi.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type->value,
            'category' => $this->notification->category->value,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'action_url' => $this->notification->action_url,
            'is_read' => false,
            'created_at' => $this->notification->created_at->toISOString(),
        ];
    }
}
