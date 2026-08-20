<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($notifications->items())->map(fn ($n) => $this->transform($n)),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, DatabaseNotification $notification)
    {
        $this->assertOwner($request->user(), $notification);
        $notification->markAsRead();

        return $this->successResponse(['notification' => $this->transform($notification->fresh())]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse(null, ['message' => 'Semua notifikasi telah dibaca']);
    }

    public function destroy(Request $request, DatabaseNotification $notification)
    {
        $this->assertOwner($request->user(), $notification);
        $notification->delete();

        return $this->successResponse(null, ['message' => 'Notifikasi dihapus']);
    }

    private function assertOwner($user, DatabaseNotification $notification): void
    {
        abort_unless(
            $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->getKey(),
            404
        );
    }

    private function transform(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $data['title'] ?? 'Notifikasi',
            'body' => $data['body'] ?? null,
            'data' => $data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
