# Broadcasting Auth - Panduan Frontend (Next.js)

> **Status backend: TERVERIFIKASI BERFUNGSI.** Error 403 berasal dari sisi frontend.

## Alur Request

```
Echo/Reverb Client
  → POST /api/broadcasting/auth          (Next.js API route)
    → POST /api/v1/broadcasting/auth     (Laravel backend)
      → auth:sanctum middleware          (validasi Bearer token)
      → Broadcast::auth()                (validasi channel + user)
      → 200 OK { "auth": "..." }
```

## Endpoint

| Environment | URL |
|---|---|
| Local (Herd) | `http://be-skincek.test/api/v1/broadcasting/auth` |
| Production | `https://<domain>/api/v1/broadcasting/auth` |

---

## 1. Next.js API Route (`app/api/broadcasting/auth/route.ts`)

Route ini berfungsi sebagai **proxy** dari frontend ke Laravel backend. Route ini **WAJIB** meneruskan 3 hal ke backend:

### Request yang dikirim ke backend

```
POST http://be-skincek.test/api/v1/broadcasting/auth

Headers:
  Content-Type: application/json
  Authorization: Bearer <token dari cookie>

Body (JSON):
  {
    "channel_name": "private-user.{user-uuid}",
    "socket_id": "xxxxxxxx.xxxxxxxx"
  }
```

### Implementasi yang benar

```typescript
// app/api/broadcasting/auth/route.ts
import { NextRequest, NextResponse } from 'next/server';

export async function POST(req: NextRequest) {
  try {
    // 1. Parse body dari request asli (Echo/Reverb mengirim ini)
    const body = await req.json();
    const { channel_name, socket_id } = body;

    // 2. Ambil Bearer token dari cookie
    const token = req.cookies.get('auth_token')?.value;

    if (!token) {
      return NextResponse.json(
        { message: 'No auth token' },
        { status: 401 }
      );
    }

    // 3. Forward ke Laravel backend
    const backendUrl = process.env.BACKEND_URL ?? 'http://be-skincek.test';

    const response = await fetch(`${backendUrl}/api/v1/broadcasting/auth`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({ channel_name, socket_id }),
    });

    const data = await response.json();

    return NextResponse.json(data, { status: response.status });
  } catch (error) {
    return NextResponse.json(
      { message: 'Broadcasting auth failed' },
      { status: 500 }
    );
  }
}
```

### Yang harus dicek di route ini

| Checklist | Penjelasan |
|---|---|
| `Content-Type: application/json` | Laravel hanya parse body jika content-type JSON |
| `Authorization: Bearer ${token}` | Tanpa ini, Sanctum tidak bisa identifikasi user |
| `JSON.stringify({ channel_name, socket_id })` | Body harus berisi kedua field ini |
| Token dari cookie `auth_token` | Baca dari cookie yang di-set saat login |

---

## 2. Laravel Echo Config

```typescript
// lib/echo.ts (atau sejenisnya)
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.NEXT_PUBLIC_REVERB_APP_KEY,        // aiofaz9nhwgzaipxjgg1
  wsHost: import.meta.env.NEXT_PUBLIC_REVERB_HOST,        // localhost
  wsPort: Number(import.meta.env.NEXT_PUBLIC_REVERB_PORT) ?? 8080,
  wssPort: Number(import.meta.env.NEXT_PUBLIC_REVERB_PORT) ?? 443,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],

  // KRUSIAL: auth config untuk private channel
  authEndpoint: '/api/broadcasting/auth',   // Next.js proxy route
  auth: {
    headers: {
      Authorization: `Bearer ${token}`,     // ← Harus ada
    },
  },
});
```

### Env variables (Next.js)

```env
NEXT_PUBLIC_REVERB_APP_KEY=aiofaz9nhwgzaipxjgg1
NEXT_PUBLIC_REVERB_HOST=localhost
NEXT_PUBLIC_REVERB_PORT=8080
NEXT_PUBLIC_REVERB_SCHEME=http

BACKEND_URL=http://be-skincek.test
```

---

## 3. Subscribe ke Private Channel

```typescript
// Setelah user login dan Echo terhubung
const uuid = user.uuid; // dari response login/profile

echo.private(`user.${uuid}`)
  .notification((notification) => {
    console.log('Notifikasi baru:', notification);
  });
```

**Format channel:** `private-user.{user-uuid}`

Contoh: `private-user.550e8400-e29b-41d4-a716-446655440000`

---

## 4. Error 403 - Troubleshooting

### Penyebab

Error `403 AccessDeniedHttpException` dari backend terjadi jika salah satu kondisi ini terpenuhi:

```
1. channel_name kosong / tidak terkirim    → 403
2. Authorization header tidak ada           → 403
3. Token expired / revoked                  → 403
4. UUID di channel_name tidak cocok        → 403
```

### Langkah debug

**Step 1 — Test langsung ke backend (bypass Next.js)**

```bash
TOKEN="<bearer-token-dari-login-response>"
UUID="<user-uuid>"

curl -X POST http://be-skincek.test/api/v1/broadcasting/auth \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"channel_name\":\"private-user.$UUID\",\"socket_id\":\"87654.321098\"}"
```

| Response | Arti |
|---|---|
| `200 { "auth": "..." }` | Backend OK, masalah di Next.js proxy |
| `401 { "message": "Unauthenticated" }` | Token tidak valid / expired |
| `403` | Token valid tapi channel_name kosong / UUID salah |

**Step 2 — Cek apakah Next.js route meneruskan body**

Tambahkan log di `app/api/broadcasting/auth/route.ts`:

```typescript
export async function POST(req: NextRequest) {
  const body = await req.json();
  console.log('Broadcasting auth body:', body);  // ← cek apakah ada channel_name
  console.log('Token:', req.cookies.get('auth_token')?.value ? 'ADA' : 'KOSONG');
  // ...
}
```

**Step 3 — Cek backend log**

```bash
tail -50 storage/logs/laravel.log | grep "Channel auth attempt"
```

Jika log **tidak muncul sama sekali** = request tidak sampai ke backend.

---

## 5. Environment Variables

### Frontend (.env.local / .env)

```env
# Reverb WebSocket
NEXT_PUBLIC_REVERB_APP_KEY=aiofaz9nhwgzaipxjgg1
NEXT_PUBLIC_REVERB_HOST=localhost
NEXT_PUBLIC_REVERB_PORT=8080
NEXT_PUBLIC_REVERB_SCHEME=http

# Backend URL (untuk proxy)
BACKEND_URL=http://be-skincek.test
```

### Backend (.env) — sudah diatur, jangan diubah

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=723644
REVERB_APP_KEY=aiofaz9nhwgzaipxjgg1
REVERB_APP_SECRET=ms3aadko84wu5meyh7bk
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**REVERB_APP_KEY harus sama di frontend dan backend.**

---

## 6. Prerequisites

Sebelum test broadcasting:

1. Backend running (`composer dev:api` atau `php artisan reverb:start`)
2. User sudah login dan punya Sanctum Bearer token
3. User sudah email-verified dan profile-completed
4. Channel name menggunakan **UUID**, bukan ID: `private-user.{uuid}`

---

## Checklist Singkat

- [ ] `app/api/broadcasting/auth/route.ts` forward body `{ channel_name, socket_id }` sebagai JSON
- [ ] Request ke backend pakai `Content-Type: application/json`
- [ ] Request ke backend pakai `Authorization: Bearer <token>`
- [ ] Echo config punya `auth.headers.Authorization: Bearer <token>`
- [ ] Echo config punya `authEndpoint: '/api/broadcasting/auth'`
- [ ] `REVERB_APP_KEY` = `aiofaz9nhwgzaipxjgg1` (sama di frontend & backend)
- [ ] Channel name format: `private-user.{uuid}` (bukan `private-user.{id}`)
- [ ] Token tidak expired / revoked
