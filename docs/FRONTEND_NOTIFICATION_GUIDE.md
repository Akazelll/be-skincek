# Notifikasi Realtime — Panduan Frontend (Next.js + Flutter)

> Dokumen ini menjelaskan cara **fetching**, **listening**, dan **menampilkan** notifikasi realtime dari backend SkinCek.

---

## Arsitektur

```
Backend (Laravel)                     Frontend (Next.js / Flutter)
┌─────────────────────┐              ┌─────────────────────────┐
│ NotificationService │              │                         │
│  → Notification::   │  WebSocket   │  Laravel Echo / Reverb  │
│    create()         │ ──────────→  │  .listen('.notif...')   │
│  → event(           │  (Reverb)    │                         │
│    NotificationSent)│              │  → toast() / UI update  │
└─────────────────────┘              └─────────────────────────┘
         │                                    │
         │ REST API                           │ REST API
         ▼                                    ▼
┌─────────────────────┐              ┌─────────────────────────┐
│ GET /notifications  │  ←──────────  │ GET /notifications      │
│ GET /unread-count   │   polling     │ GET /unread-count       │
└─────────────────────┘              └─────────────────────────┘
```

**Dua channel pengiriman:**

| Channel | Cara | Latensi | Keterangan |
|---------|------|---------|------------|
| **Database** | REST API `GET /notifications` | Polling | Notifikasi tersimpan permanen, untuk bell icon + history |
| **Broadcast** | WebSocket Reverb | ~1 detik | Realtime push, untuk toast notification |

> **Penting:** Backend menggunakan `ShouldBroadcastNow` (synchronous, tanpa queue). Broadcast dikirim langsung tanpa delay queue worker.

---

## 1. Struktur Database

Tabel `notifications` menggunakan kolom eksplisit (bukan JSON `data`):

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | UUID | Primary key |
| `user_id` | bigint (FK) | Pemilik notifikasi |
| `type` | string(20) | Visual: `success`, `warning`, `error`, `info` |
| `category` | string(50) | Bisnis: `welcome`, `scan_complete`, `chat_message`, dll |
| `title` | string | Judul notifikasi |
| `message` | text | Isi pesan |
| `action_url` | string(500) | URL tujuan saat notifikasi diklik (nullable) |
| `read_at` | timestamp | Waktu dibaca (nullable) |
| `created_at` | timestamp | Waktu dibuat |

---

## 2. Daftar Notifikasi

### NotificationType (Warna Toast)

| Value | Warna | Icon | Contoh Kapan |
|-------|-------|------|-------------|
| `success` | Hijau | ✅ | Scan selesai, pembayaran berhasil, verifikasi disetujui |
| `warning` | Kuning | ⚠️ | Verifikasi perlu revisi |
| `error` | Merah | ❌ | Verifikasi ditolak |
| `info` | Biru | ℹ️ | Selamat datang, pesan chat, logout |

### NotificationCategory (Konteks Bisnis)

| Category | Type | Trigger | Action URL |
|----------|------|---------|------------|
| `welcome` | info | Login sukses | - |
| `scan_complete` | success | Hasil scan ready | `/scan/history/{uuid}` |
| `chat_message` | info | Pesan chat baru | `/chat/{conversation_uuid}` |
| `logout` | info | Logout sukses | - |
| `verification_approved` | success | Verifikasi dokter disetujui | `/doctor/verification` |
| `verification_rejected` | error | Verifikasi dokter ditolak | `/doctor/verification` |
| `verification_revision` | warning | Verifikasi perlu revisi | `/doctor/verification` |
| `subscription_active` | success | Pembayaran langganan berhasil | `/subscription/{uuid}` |

---

## 3. REST API Endpoints

### GET /api/v1/notifications

Daftar notifikasi user (paginated).

**Query Parameters:**
- `unread_only` (boolean) — filter hanya yang belum dibaca
- `limit` (int, max 50, default 15) — jumlah per halaman
- `page` (int) — nomor halaman

**Response:**
```json
{
  "data": [
    {
      "id": "uuid-notifikasi",
      "type": "success",
      "category": "scan_complete",
      "title": "Hasil scan kamu sudah ready!",
      "message": "Prediksi acne dengan tingkat keyakinan 91%.",
      "action_url": "/scan/history/prediction-uuid",
      "is_read": false,
      "read_at": null,
      "created_at": "2026-08-29T10:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42,
    "unread_count": 5
  }
}
```

### GET /api/v1/notifications/unread-count

Jumlah notifikasi belum dibaca (untuk badge bell icon).

**Response:**
```json
{
  "unread_count": 5
}
```

### PATCH /api/v1/notifications/{id}/read

Tandai satu notifikasi sebagai sudah dibaca.

**Response:**
```json
{
  "message": "Notifikasi ditandai sebagai dibaca.",
  "data": {
    "id": "...",
    "type": "success",
    "category": "scan_complete",
    "title": "Hasil scan kamu sudah ready!",
    "message": "...",
    "action_url": "/scan/history/...",
    "is_read": true,
    "read_at": "2026-08-29T10:05:00.000000Z",
    "created_at": "2026-08-29T10:00:00.000000Z"
  }
}
```

### PATCH /api/v1/notifications/read-all

Tandai semua notifikasi sebagai sudah dibaca.

**Response:**
```json
{
  "message": "Semua notifikasi ditandai sebagai dibaca.",
  "updated": 5
}
```

### DELETE /api/v1/notifications/{id}

Hapus permanen satu notifikasi.

**Response:**
```json
{
  "message": "Notifikasi berhasil dihapus."
}
```

---

## 4. WebSocket Broadcast (Realtime)

### Event Name

```
.notification.new
```

> Frontend harus listen ke `.notification.new` (pakai dot-prefix, bukan full class name).

### Channel

```
private-user.{user-uuid}
```

Contoh: `private-user.550e8400-e29b-41d4-a716-446655440000`

### Broadcast Auth Endpoint

```
POST /api/v1/broadcasting/auth
```

**Request:**
```json
{
  "channel_name": "private-user.{user-uuid}",
  "socket_id": "xxxxxxxx.xxxxxxxx"
}
```

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "auth": "aiofaz9nhwgzaipxjgg1:signature-hash"
}
```

### Broadcast Payload

Sama persis dengan REST API response (field `id`, `type`, `category`, `title`, `message`, `action_url`, `is_read`, `created_at`).

```json
{
  "id": "uuid-notifikasi",
  "type": "success",
  "category": "scan_complete",
  "title": "Hasil scan kamu sudah ready!",
  "message": "Prediksi acne dengan tingkat keyakinan 91%.",
  "action_url": "/scan/history/prediction-uuid",
  "is_read": false,
  "created_at": "2026-08-29T10:00:00.000000Z"
}
```

---

## 5. Implementasi Frontend (Next.js)

### 5.1 Echo Config

```typescript
// lib/echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echo: Echo | null = null;

export function getEcho(token: string): Echo {
  if (echo) return echo;

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.NEXT_PUBLIC_REVERB_APP_KEY,
    wsHost: import.meta.env.NEXT_PUBLIC_REVERB_HOST,
    wsPort: Number(import.meta.env.NEXT_PUBLIC_REVERB_PORT) ?? 8080,
    wssPort: Number(import.meta.env.NEXT_PUBLIC_REVERB_PORT) ?? 443,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    },
  });

  return echo;
}
```

### 5.2 Next.js API Route (Broadcasting Auth Proxy)

```typescript
// app/api/broadcasting/auth/route.ts
import { NextRequest, NextResponse } from 'next/server';

export async function POST(req: NextRequest) {
  const body = await req.json();
  const token = req.cookies.get('auth_token')?.value;

  if (!token) {
    return NextResponse.json({ message: 'No auth token' }, { status: 401 });
  }

  const backendUrl = process.env.BACKEND_URL ?? 'http://be-skincek.test';

  const response = await fetch(`${backendUrl}/api/v1/broadcasting/auth/`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(body),
  });

  const data = await response.json();
  return NextResponse.json(data, { status: response.status });
}
```

> **Penting:** URL backend harus ada trailing `/` (`/auth/`) untuk mencegah redirect 301 yang mengubah POST → GET.

### 5.3 Subscribe & Listen Notifikasi

```typescript
// hooks/useRealtimeNotifications.ts
'use client';

import { useEffect, useCallback } from 'react';
import { toast } from 'sonner';
import { getEcho } from '@/lib/echo';

type NotificationPayload = {
  id: string;
  type: 'success' | 'warning' | 'error' | 'info';
  category: string;
  title: string;
  message: string;
  action_url?: string;
  is_read: boolean;
  created_at: string;
};

const TOAST_VARIANT: Record<string, 'success' | 'warning' | 'error' | 'info'> = {
  success: 'success',
  warning: 'warning',
  error: 'error',
  info: 'info',
};

export function useRealtimeNotifications(token: string, userUuid: string) {
  const handleNotification = useCallback((notification: NotificationPayload) => {
    const variant = TOAST_VARIANT[notification.type] ?? 'info';

    toast[variant](notification.title, {
      description: notification.message,
      action: notification.action_url
        ? { label: 'Lihat', onClick: () => window.location.href = notification.action_url! }
        : undefined,
    });

    // Dispatch custom event agar komponen lain bisa listen
    window.dispatchEvent(new CustomEvent('notification:new', { detail: notification }));
  }, []);

  useEffect(() => {
    if (!token || !userUuid) return;

    const echo = getEcho(token);

    echo.private(`user.${userUuid}`)
      .listen('.notification.new', handleNotification);

    return () => {
      echo.leave(`private-user.${userUuid}`);
    };
  }, [token, userUuid, handleNotification]);
}
```

### 5.4 Notification Bell (Bell Icon + Badge)

```typescript
// components/NotificationBell.tsx
'use client';

import { useEffect, useState } from 'react';
import { Bell } from 'lucide-react';
import { useRealtimeNotifications } from '@/hooks/useRealtimeNotifications';

export function NotificationBell({ token, userUuid }: { token: string; userUuid: string }) {
  const [unreadCount, setUnreadCount] = useState(0);

  // Listen realtime
  useRealtimeNotifications(token, userUuid);

  // Listen custom event dari hook
  useEffect(() => {
    const handler = () => setUnreadCount((prev) => prev + 1);
    window.addEventListener('notification:new', handler);
    return () => window.removeEventListener('notification:new', handler);
  }, []);

  // Initial load
  useEffect(() => {
    fetch('/api/v1/notifications/unread-count', {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then((res) => res.json())
      .then((data) => setUnreadCount(data.unread_count));
  }, [token]);

  return (
    <button className="relative">
      <Bell />
      {unreadCount > 0 && (
        <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
          {unreadCount > 99 ? '99+' : unreadCount}
        </span>
      )}
    </button>
  );
}
```

### 5.5 Fetch Notifikasi (Bell Dropdown)

```typescript
// components/NotificationDropdown.tsx
'use client';

import { useEffect, useState } from 'react';

type Notification = {
  id: string;
  type: string;
  category: string;
  title: string;
  message: string;
  action_url?: string;
  is_read: boolean;
  created_at: string;
};

export function NotificationDropdown({ token }: { token: string }) {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [page, setPage] = useState(1);

  useEffect(() => {
    fetch(`/api/v1/notifications?page=${page}&limit=15`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then((res) => res.json())
      .then((data) => setNotifications(data.data));
  }, [token, page]);

  const markAsRead = async (id: string) => {
    await fetch(`/api/v1/notifications/${id}/read`, {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${token}` },
    });
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
    );
  };

  const markAllRead = async () => {
    await fetch('/api/v1/notifications/read-all', {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${token}` },
    });
    setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
  };

  return (
    <div>
      <button onClick={markAllRead}>Tandai semua dibaca</button>
      {notifications.map((n) => (
        <div
          key={n.id}
          onClick={() => {
            markAsRead(n.id);
            if (n.action_url) window.location.href = n.action_url;
          }}
          className={n.is_read ? 'opacity-50' : 'font-bold'}
        >
          <p>{n.title}</p>
          <p className="text-sm">{n.message}</p>
        </div>
      ))}
    </div>
  );
}
```

### 5.6 Env Variables (Next.js)

```env
# .env.local
NEXT_PUBLIC_REVERB_APP_KEY=aiofaz9nhwgzaipxjgg1
NEXT_PUBLIC_REVERB_HOST=localhost
NEXT_PUBLIC_REVERB_PORT=8080
NEXT_PUBLIC_REVERB_SCHEME=http
BACKEND_URL=http://be-skincek.test
```

---

## 6. Implementasi Frontend (Flutter)

### 6.1 WebSocket Connection

```dart
// services/notification_service.dart
import 'dart:async';
import 'dart:convert';
import 'package:web_socket_channel/web_socket_channel.dart';

class RealtimeNotificationService {
  WebSocketChannel? _channel;
  final _controller = StreamController<Map<String, dynamic>>.broadcast();

  Stream<Map<String, dynamic>> get onNotification => _controller.stream;

  void connect(String userUuid, String token) {
    // Reverb WebSocket URL
    final url = 'ws://localhost:8080/app/aiofaz9nhwgzaipxjgg1';

    _channel = WebSocketChannel.connect(Uri.parse(url));

    // 1. Wait for connection_established
    _channel!.stream.listen((data) {
      final event = jsonDecode(data);

      if (event['event'] == 'pusher_internal:connection_established') {
        final socketId = event['data']['socket_id'];

        // 2. Authenticate channel
        _authenticateChannel('private-user.$userUuid', socketId, token);

        // 3. Subscribe to channel
        _channel!.sink.add(jsonEncode({
          'event': 'pusher:subscribe',
          'data': {
            'channel': 'private-user.$userUuid',
          },
        }));
      }

      // 4. Listen for notification events
      if (event['event'] == '.notification.new') {
        final notification = jsonDecode(event['data']);
        _controller.add(notification);
      }
    });
  }

  void _authenticateChannel(String channel, String socketId, String token) async {
    // POST to /api/v1/broadcasting/auth
    // Then send pusher:subscribe with auth signature
  }

  void disconnect() {
    _channel?.sink.close();
    _controller.close();
  }
}
```

### 6.2 Usage in Widget

```dart
// Listen to notifications
final notificationService = RealtimeNotificationService();
notificationService.connect(userUuid, token);

notificationService.onNotification.listen((notification) {
  // Show snackbar/toast
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(notification['title']),
      subtitle: Text(notification['message']),
      backgroundColor: _getColor(notification['type']),
    ),
  );
});
```

---

## 7. Sinkronisasi Bell Icon

```
┌──────────────────────────────────────────────────────┐
│                    NOTIFICATION BELL                   │
├──────────────────────────────────────────────────────┤
│                                                       │
│  1. Initial Load                                      │
│     GET /api/v1/notifications/unread-count            │
│     → Set badge = unread_count                        │
│                                                       │
│  2. Realtime Update                                   │
│     WebSocket .notification.new                       │
│     → Increment badge +1                              │
│     → Add notification to dropdown list               │
│                                                       │
│  3. Mark as Read                                      │
│     PATCH /api/v1/notifications/{id}/read             │
│     → Decrement badge -1                              │
│     → Update notification.is_read = true              │
│                                                       │
│  4. Mark All Read                                     │
│     PATCH /api/v1/notifications/read-all              │
│     → Set badge = 0                                   │
│     → Update all notifications.is_read = true         │
│                                                       │
└──────────────────────────────────────────────────────┘
```

---

## 8. Troubleshooting

### Notifikasi tidak muncul realtime (harus refresh)

| Checklist | Keterangan |
|-----------|------------|
| `<Toaster />` dipasang di root layout | Sonner butuh DOM node untuk render toast |
| `toast()` dipanggil dari Client Component | Pakai `'use client'` di file yang import `toast` |
| Echo connected (Network → WS → status 101) | Cek `ws://localhost:8080/app/aiofaz9nhwgzaipxjgg1` |
| Channel auth sukses (200) | POST `/api/v1/broadcasting/auth` dengan token valid |
| `composer dev:api` berjalan | Reverb + queue worker harus aktif |
| `REVERB_APP_KEY` sama di frontend & backend | `aiofaz9nhwgzaipxjgg1` |

### Debug Steps

```typescript
// 1. Cek console browser — apakah data masuk?
echo.private(`user.${userUuid}`)
  .listen('.notification.new', (notification) => {
    console.log('🔔 REALTIME MASUK:', notification);
  });

// 2. Cek WebSocket connection
// DevTools → Network → WS → klik connection → Messages

// 3. Test auth endpoint langsung
const resp = await fetch('/api/v1/broadcasting/auth', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer <token>',
  },
  body: JSON.stringify({
    channel_name: `private-user.${userUuid}`,
    socket_id: '12345.67890',
  }),
});
console.log('Auth:', await resp.json());
// 200 = OK, 401 = token expired, 403 = UUID salah

// 4. Test REST API langsung
const notifs = await fetch('/api/v1/notifications?limit=5', {
  headers: { Authorization: 'Bearer <token>' },
});
console.log('Notifications:', await notifs.json());
```

### Common Errors

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `403 AccessDeniedHttpException` di broadcasting/auth | Token expired / UUID tidak cocok | Login ulang, cek UUID |
| Toast tidak tampil tapi console ada log | `<Toaster />` belum dipasang | Tambahkan di root layout |
| Toast muncul sekali lalu hilang | Sonner dedup / duration terlalu pendek | Set `duration: 5000` di toast config |
| WebSocket tidak connect (status bukan 101) | Reverb belum jalan | Jalankan `composer dev:api` |
| Notification muncul setelah refresh tapi bukan realtime | Queue worker belum jalan | Jalankan `composer dev:api` |

---

## 9. Backend Notes (untuk Developer)

### Event Flow

```
NotificationService::send()
  → Notification::create()        [insert ke DB]
  → event(NotificationSent)       [broadcast ke Reverb, synchronous]
```

### Key Files

| File | Fungsi |
|------|--------|
| `app/Services/NotificationService.php` | Factory terpusat untuk semua notifikasi |
| `app/Models/Notification.php` | Custom model (bukan Laravel default) |
| `app/Events/NotificationSent.php` | Broadcast event (ShouldBroadcastNow) |
| `app/Enums/NotificationType.php` | Enum warna: success/warning/error/info |
| `app/Enums/NotificationCategory.php` | Enum bisnis: welcome/scan_complete/dll |
| `app/Http/Resources/NotificationResource.php` | REST API resource |
| `app/Http/Controllers/NotificationController.php` | REST API endpoints |
| `app/Console/Commands/CleanOldNotifications.php` | Cleanup notifikasi > 6 bulan |
| `routes/channels.php` | Channel auth: `user.{uuid}` |

### Adding New Notification Type

1. Tambah case di `NotificationCategory` enum
2. Tambah method factory di `NotificationService`
3. Panggil dari controller/service yang sesuai
4. Update dokumen ini

```php
// Contoh menambah notifikasi baru
// 1. Di NotificationCategory enum:
case PRODUCT_REVIEW = 'product_review';

// 2. Di NotificationService:
public function productReview(User $user, string $productName): void
{
    $this->send(
        user: $user,
        type: NotificationType::INFO,
        category: NotificationCategory::PRODUCT_REVIEW,
        title: 'Review produk diterima',
        message: "Review kamu untuk {$productName} sudah ditampilkan.",
        actionUrl: "/products/{$productUuid}",
    );
}

// 3. Di controller:
app(NotificationService::class)->productReview($user, $productName);
```

---

## 10. Payload Reference

### Semua Category

| Category | Type | Title | Message | Action URL |
|----------|------|-------|---------|------------|
| `welcome` | info | Selamat datang di SkinCek! | Halo {name}, senang melihatmu kembali... | - |
| `scan_complete` | success | Hasil scan kamu sudah ready! | Prediksi {class} dengan tingkat keyakinan {confidence}%. | `/scan/history/{uuid}` |
| `chat_message` | info | Pesan baru dari {name} | {message preview 120 char} | `/chat/{uuid}` |
| `logout` | info | Kamu telah berhasil logout | Sesi kamu telah diakhiri dengan aman... | - |
| `verification_approved` | success | Verifikasi Dokter | Selamat! Kamu kini terverifikasi... | `/doctor/verification` |
| `verification_rejected` | error | Verifikasi Dokter | {alasan penolakan} | `/doctor/verification` |
| `verification_revision` | warning | Verifikasi Dokter | {catatan revisi} | `/doctor/verification` |
| `subscription_active` | success | Pembayaran berhasil | Selamat, langganan SkinCek Pro... | `/subscription/{uuid}` |
