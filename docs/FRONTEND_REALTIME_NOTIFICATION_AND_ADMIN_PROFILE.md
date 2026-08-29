# Notifikasi Real-Time & Profil Admin — Panduan Frontend

> Dokumen ini mencakup **2 fitur baru** yang sudah siap dipakai frontend:
> 1. **Notifikasi Real-Time** (4 jenis, broadcast via Reverb WebSocket)
> 2. **Endpoint Profil Admin** (`GET /api/v1/admin/profile`)

---

## Fitur 1: Notifikasi Real-Time

### Konsep

Backend mengirim notifikasi ke **2 channel sekaligus**:

| Channel | Kegunaan |
|---|---|
| **Database** (`database`) | Notifikasi tersimpan permanen, bisa dilihat lewat `GET /api/v1/notifications` (bell icon) |
| **Broadcast** (`broadcast`) | Real-time push via WebSocket Reverb → langsung tampil toast tanpa polling |

Semua notifikasi dikirim ke **private channel** `private-user.{uuid}` dengan event `Illuminate\Notifications\Events\BroadcastNotificationCreated`.

### Field `notification_type` (BARU)

Setiap notifikasi sekarang punya field `notification_type` di payload. Frontend menggunakan field ini untuk menentukan **varian toast** dan **routing** saat notifikasi diklik.

### Daftar Jenis Notifikasi

| notification_type | Trigger | Varian Toast | Saran Routing |
|---|---|---|---|
| `welcome` | Login sukses (email/password & Google) | `info` | Tidak perlu navigasi (toast saja) |
| `chat` | Pesan chat baru masuk (dokter↔user, balasan Aura Skin) | `info` | `/chat/{conversation_id}` |
| `logout` | Logout / logout-all sukses | `info` | Tidak perlu navigasi |
| `scan` | Hasil scan selesai diproses | `success` | `/scan/history/{prediction_id}` |
| `verification` | Status verifikasi dokter berubah | `success` / `error` sesuai status | `/doctor/verification` |
| `subscription` | Pembayaran langganan berhasil | `success` | `/subscription` |
| `general` | Default (fallback) | `info` | Tidak perlu navigasi |

### Struktur Payload Broadcast

Event yang diterima via WebSocket:

```json
{
  "id": "notification-uuid",
  "title": "Hasil scan kamu sudah ready!",
  "body": "Prediksi acne dengan tingkat keyakinan 91%.",
  "notification_type": "scan",
  "prediction_id": "abc-123",
  "read_at": null,
  "created_at": "2026-08-29T10:00:00.000000Z"
}
```

> **Penting:** Struktur payload broadcast = struktur yang tersimpan di database. Field tambahan (seperti `prediction_id`, `conversation_id`) berada di level root payload, BUKAN nested di dalam `data`.

### Payload per Jenis Notifikasi

#### `welcome`
```json
{
  "title": "Selamat datang di SkinCek!",
  "body": "Halo Budi, senang melihatmu kembali. Yuk, cek kondisi kulitmu hari ini!",
  "notification_type": "welcome"
}
```

#### `chat`
```json
{
  "title": "Pesan baru dari dr. Sari",
  "body": "Halo, hasil scan kamu sudah saya lihat...",
  "notification_type": "chat",
  "conversation_id": "conv-uuid-123"
}
```

#### `logout`
```json
{
  "title": "Kamu telah berhasil logout",
  "body": "Sesi kamu telah diakhiri dengan aman. Sampai jumpa lagi!",
  "notification_type": "logout"
}
```

#### `scan`
```json
{
  "title": "Hasil scan kamu sudah ready!",
  "body": "Prediksi acne dengan tingkat keyakinan 91%.",
  "notification_type": "scan",
  "prediction_id": "prediction-uuid-123"
}
```

#### `verification`
```json
{
  "title": "Verifikasi dokter disetujui",
  "body": "Selamat! Kamu kini terverifikasi sebagai dokter di SkinCek.",
  "notification_type": "verification",
  "verification_status": "approved"
}
```

#### `subscription`
```json
{
  "title": "Pembayaran berhasil",
  "body": "Selamat, langganan SkinCek Pro kamu sudah aktif.",
  "notification_type": "subscription",
  "subscription_id": "sub-uuid-123"
}
```

### Cara Listen (Laravel Echo)

```typescript
import Echo from 'laravel-echo';

// Setelah user login, subscribe ke private channel user
echo.private(`user.${user.uuid}`)
  .notification((notification: any) => {
    // notification = payload di atas (dengan notification_type)
    showToast(notification);
    incrementUnreadCount();
  });

function showToast(notification: any) {
  const variant = toastVariant(notification.notification_type);

  const label: Record<string, string> = {
    welcome: 'info',
    chat: 'info',
    logout: 'info',
    scan: 'success',
    verification: notification.verification_status === 'rejected' ? 'error' : 'success',
    subscription: 'success',
    general: 'info',
  };

  // Sonner
  toast[variant]?.(notification.title, {
    description: notification.body,
  });
}
```

### Sinkronisasi dengan Notification Bell

Notifikasi realtime dan notification bell **sumbernya sama** (table `notifications`). Yang perlu dilakukan frontend:

1. **Initial load** → `GET /api/v1/notifications` (dengan `unread_count` di meta)
2. **Realtime** → listen private channel → increment `unread_count` + tampilkan toast
3. **Mark as read** → `POST /api/v1/notifications/{id}/read` → decrement `unread_count`
4. **Mark all** → `POST /api/v1/notifications/read-all`

### Catatan Penting

- **Logout notification** dikirim ke semua device yang masih subscribe (karena token baru dihapus, device yang logout mungkin tidak menerima, tapi device LAIN milik user yang sama masih menerima)
- **Scan notification** dikirim setiap scan sukses (tidak di-dedup), email scan tetap 1x/hari (rate-limit existing)
- **Welcome notification** dikirim setiap login sukses — bukan hanya sekali

---

## Fitur 2: Endpoint Profil Admin

### `GET /api/v1/admin/profile`

Menampilkan data profil admin yang sedang login + ringkasan platform + info session.

**Auth:** Bearer Token + role `admin`

#### Contoh Request

```bash
curl -X GET http://be-skincek.test/api/v1/admin/profile \
  -H "Authorization: Bearer <admin-token>"
```

#### Response Sukses (200)

```json
{
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "full_name": "Admin SkinCek",
    "email": "admin@skincek.com",
    "role": "admin",
    "avatar_url": "https://... atau null",
    "email_verified": true,
    "account_created_at": "2026-01-15T08:30:00.000000Z",

    "last_login": {
      "at": "2026-08-29T10:15:30.000000Z",
      "ip_address": "192.168.1.10",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) ..."
    },
    "active_sessions": 3,

    "summary": {
      "total_users": 152,
      "total_doctors": 8,
      "pending_doctor_verifications": 2
    }
  },
  "meta": {}
}
```

#### Response Error

| Status | Kondisi |
|---|---|
| `401` | Token tidak valid / tidak ada |
| `403` | Bukan role admin (user / doctor) |

#### Penjelasan Field

| Field | Tipe | Keterangan |
|---|---|---|
| `uuid` | string | UUID admin (dipakai untuk broadcast channel `private-user.{uuid}`) |
| `full_name` / `email` | string | Data pribadi admin |
| `role` | string | Selalu `admin` untuk endpoint ini |
| `avatar_url` | string\|null | URL avatar (signed URL 60 menit) atau Google avatar |
| `email_verified` | boolean | Status verifikasi email |
| `account_created_at` | ISO string | Kapan akun admin dibuat |
| `last_login.at` | ISO string\|null | Timestamp session/token terbaru |
| `last_login.ip_address` | string\|null | IP saat login terakhir |
| `last_login.user_agent` | string\|null | Browser/device login terakhir |
| `active_sessions` | int | Jumlah token aktif (device yang sedang login) |
| `summary.total_users` | int | Total user (role `user`) di platform |
| `summary.total_doctors` | int | Total dokter (role `doctor`, exclude AI bot) |
| `summary.pending_doctor_verifications` | int | Pengajuan verifikasi dokter menunggu review |

#### Saran Penggunaan di Dashboard Admin (Next.js)

```
Admin Dashboard Page
├── Sidebar / Navbar
│   ├── Avatar (avatar_url) + full_name + email
│   ├── Badge email_verified
│   └── "Login terakhir: {last_login.at} dari {last_login.ip_address}"
├── Widget Ringkasan (summary)
│   ├── Total Pengguna → total_users
│   ├── Total Dokter → total_doctors
│   └── Verifikasi Menunggu → pending_doctor_verifications (badge merah jika > 0)
│       └── Link → /admin/verifications
└── Info Sesi
    └── "Kamu login di {active_sessions} perangkat"
```

---

## Troubleshooting: Notifikasi Tidak Muncul Realtime (Harus Refresh)

### Diagnosis Cepat

```typescript
// Tambahkan sementara di listener notifikasi:
echo.private(`user.${user.uuid}`)
  .notification((notification) => {
    console.log('🔔 REALTIME DITERIMA:', notification);  // ← cek ini muncul di console
    toast(notification.title, { description: notification.body });
  });
```

| Hasil console | Penyebab | Solusi |
|---|---|---|
| `🔔 REALTIME DITERIMA` muncul tapi toast tidak tampil | Sonner tidak ter-mount / `Toaster` belum dipasang | Cek point 1 |
| `🔔 REALTIME DITERIMA` muncul + toast tampil | Berhasil! | Selesai |
| Tidak ada output sama sekali | Echo tidak terhubung / channel tidak subscribe | Cek point 2-3 |

### Point 1: `<Toaster />` Belum Dipasang di Root Layout

```tsx
// app/layout.tsx
import { Toaster } from 'sonner';

export default function RootLayout({ children }) {
  return (
    <html>
      <body>
        {children}
        <Toaster position="top-right" richColors />
      </body>
    </html>
  );
}
```

Tanpa `<Toaster />`, Sonner tidak punya DOM node untuk render toast.

### Point 2: `toast()` Dipanggil dari Server Component

`toast()` hanya bisa dipakai di **Client Component**. Pastikan file yang memanggil `toast()` punya directive `'use client'` di baris pertama:

```tsx
'use client';  // ← WAJIB di baris pertama

import { toast } from 'sonner';

export function NotificationListener({ user }) {
  // echo listener + toast() bisa dipanggil di sini
}
```

### Point 3: Echo Tidak Terhubung ke Reverb

Cek di DevTools → **Network** → tab **WS** (WebSocket):

1. Harus ada connection ke `ws://localhost:8080/app/aiofaz9nhwgzaipxjgg1`
2. Status harus `101` (HTTP Switching Protocols) — artinya terhubung
3. Di tab **Messages**, harus ada `pusher_internal:subscription_succeeded` untuk channel `private-user.{uuid}`

Jika tidak ada WebSocket connection atau status bukan `101`, Echo belum terhubung. Cek:
- `wsHost` = `localhost`
- `wsPort` = `8080`
- `key` = `aiofaz9nhwgzaipxjgg1` (sama dengan backend)
- `auth.headers.Authorization` = `Bearer <token>` (token tidak expired)
- Backend `composer dev:api` sudah jalan (Reverb + Queue worker)

### Point 4: Channel Subscribe Gagal (403 di Auth)

Jika `pusher_internal:subscription_succeeded` tidak muncul, channel subscribe gagal. Cek:

```typescript
// Di browser console:
const resp = await fetch('/api/broadcasting/auth', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer <token>',
  },
  body: JSON.stringify({
    channel_name: `private-user.${user.uuid}`,
    socket_id: '12345.67890',
  }),
});
console.log('Auth response:', await resp.json());
```

- `200 { "auth": "..." }` → Auth backend OK, masalah di Echo config
- `401` → Token expired, harus login ulang
- `403` → UUID tidak cocok / token tidak valid

### Point 5: Queue Worker Tidak Jalan

Notifikasi dikirim via `ShouldQueue` (async). Queue worker harus berjalan:

```bash
composer dev:api
# atau manual:
php artisan queue:listen
```

Tanpa queue worker, notifikasi **tidak masuk database** (tidak muncul di GET /notifications) **dan tidak broadcast** ke WebSocket.

### Point 6: Cek WebSocket Connection di DevTools

```
DevTools → Network → WS
  └─ ws://localhost:8080/app/aiofaz9nhwgzaipxjgg1 → Status: 101
       └─ Messages
            ├─ pusher_internal:connection_established    ← socket_id
            ├─ pusher:subscribe                         ← ke channel
            ├─ pusher_internal:subscription_succeeded   ← SUKSES
            └─ Illuminate\Notifications\Events\...      ← NOTIFIKASI MASUK
```

Jika tidak ada message sama sekali setelah subscribe → masalah di auth atau Echo config.

---

## Testing Checklist Frontend

### Notifikasi Real-Time

- [ ] Login → toast "Selamat datang di SkinCek!" muncul (setelah Echo connected)
- [ ] Kirim chat dari akun dokter → toast muncul di akun user (realtime)
- [ ] Scan wajah → toast "Hasil scan kamu sudah ready!" + bell count bertambah
- [ ] Logout di device A → toast logout muncul di device B (jika multi-device)
- [ ] Bell count = `unread_count` dari `GET /notifications` + realtime events
- [ ] Klik toast `scan` → navigasi ke halaman detail scan
- [ ] Klik toast `chat` → navigasi ke halaman chat terkait

### Profil Admin

- [ ] Login admin → `GET /api/v1/admin/profile` → render profil di dashboard
- [ ] Badge pending verifikasi muncul jika `pending_doctor_verifications > 0`
- [ ] Login sebagai user biasa → akses endpoint → 403
- [ ] `avatar_url` expired setelah 60 menit → re-fetch profile

---

## Referensi Endpoint Terkait

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/api/v1/notifications` | List notifikasi + `unread_count` |
| `POST` | `/api/v1/notifications/{id}/read` | Tandai satu notifikasi dibaca |
| `POST` | `/api/v1/notifications/read-all` | Tandai semua dibaca |
| `DELETE` | `/api/v1/notifications/{id}` | Hapus notifikasi |
| `POST` | `/api/v1/broadcasting/auth` | Auth private channel (lihat FRONTEND_BROADCASTING_GUIDE.md) |
| `GET` | `/api/v1/admin/profile` | **BARU** — Profil admin |
| `GET` | `/api/v1/admin/dashboard` | Statistik dashboard admin |
