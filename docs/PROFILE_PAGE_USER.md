# Dokumentasi Halaman Profil — Role: USER

> Halaman profil akun untuk user (pengguna biasa / pasien).
> Semua endpoint membutuhkan `Authorization: Bearer {token}`.

---

## Struktur Halaman

```
Halaman Profil User
├── 1. Profil Akun
│   ├── Data dasar (nama, email, avatar, DOB, gender)
│   ├── Status langganan (Pro / Free)
│   └── Statistik penggunaan (scan, sisa chat gratis)
├── 2. Login dan Keamanan
│   ├── Riwayat login & sesi aktif
│   ├── Ubah password
│   ├── Verifikasi email
│   ├── Logout semua perangkat
│   └── Device tokens (push notification)
└── 3. Privasi dan Data
    ├── Unduh data akun (export)
    ├── Persetujuan AI Chat (UU PDP)
    ├── Hapus avatar
    └── Hapus akun
```

---

## 1. Profil Akun

### GET /api/v1/profile

Mengambil data profil user yang sedang login.

**Response:**
```json
{
  "data": {
    "uuid": "user-uuid",
    "full_name": "Budi Santoso",
    "email": "budi@gmail.com",
    "role": "user",
    "avatar_url": "https://signed-url-60min",
    "google_avatar_url": "https://lh3.google...",
    "date_of_birth": "1998-05-12",
    "gender": "laki_laki",
    "profile_completed": true,
    "email_verified": true,

    "subscription_status": "Free",
    "scan_count": 12,
    "user_messages_count": 3,
    "remaining_free_messages": 0
  }
}
```

**Penjelasan field khusus user:**

| Field | Keterangan | UI Suggestion |
|---|---|---|
| `subscription_status` | `"Pro"` jika ada subscription active, `"Free"` jika tidak | Badge hijau "Pro" / abu-abu "Free" |
| `scan_count` | Total scan wajah yang pernah dilakukan | Statistik card "Total Scan" |
| `user_messages_count` | Total pesan AI chat (Aura Skin) yang dikirim | Statistik card |
| `remaining_free_messages` | Sisa pesan AI gratis (max 3, unlimited jika Pro) | Progress bar / counter "Sisa X pesan" |

> `avatar_url` adalah signed URL yang **expired dalam 60 menit**. Re-fetch profile saat expired.

### PATCH /api/v1/profile

Update profil. Semua field optional (`sometimes`).

**Request (multipart/form-data jika ada avatar):**
```json
{
  "full_name": "Budi Santoso S.Kom",
  "password": "passwordBaru123",
  "date_of_birth": "1998-05-12",
  "gender": "laki_laki",
  "avatar": "[file image, max 2MB]"
}
```

**Validasi:**
- `full_name`: string, max 255
- `password`: string, min 8
- `avatar`: image, max 2048 KB — **max 1x ganti per 24 jam** (422 jika lebih)
- `date_of_birth`: date, harus sebelum hari ini
- `gender`: `laki_laki` atau `perempuan`

**Response:**
```json
{
  "data": {
    "uuid": "...",
    "full_name": "Budi Santoso S.Kom",
    "avatar_url": "...",
    "google_avatar_url": "...",
    "date_of_birth": "1998-05-12",
    "gender": "laki_laki",
    "profile_completed": true
  },
  "meta": { "message": "Profil berhasil diperbarui" }
}
```

---

## 2. Login dan Keamanan

### 2.1 Riwayat Login & Sesi Aktif

**GET /api/v1/login-activity**

Daftar semua sesi login (device yang punya token aktif).

**Response:**
```json
{
  "data": [
    {
      "uuid": "token-uuid",
      "device": "Chrome di Windows",
      "ip_address": "192.168.1.10",
      "location": { "city": "Jakarta", "country": "Indonesia" },
      "is_current": true,
      "last_used_at": "2026-08-29T10:00:00.000000Z",
      "created_at": "2026-08-28T09:00:00.000000Z"
    }
  ]
}
```

| Field | Keterangan | UI |
|---|---|---|
| `device` | Nama device + browser (dari user_agent) | "Chrome di Windows" |
| `location` | Lokasi dari IP (nullable) | "Jakarta, Indonesia" |
| `is_current` | Sesi yang sedang dipakai sekarang | Badge "Sesi ini" |

**DELETE /api/v1/login-activity/{uuid}** — Cabut satu sesi (logout paksa device lain).
```json
{ "meta": { "message": "Sesi berhasil dicabut" } }
```

### 2.2 Ubah Password

**POST /api/v1/profile/change-password**

```json
{
  "current_password": "passwordLama123",
  "password": "passwordBaru123",
  "password_confirmation": "passwordBaru123"
}
```

**Response error (422)** jika password lama salah:
```json
{ "success": false, "message": "Password lama tidak sesuai" }
```

### 2.3 Verifikasi Email

**POST /api/v1/email/verify/send** — Kirim OTP 6 digit ke email (berlaku 10 menit, max 5 attempt).
**POST /api/v1/email/verify** — Verifikasi dengan OTP.
```json
{ "otp": "123456" }
```

### 2.4 Logout Semua Perangkat

**POST /api/v1/logout-all** — Hapus semua token, semua device logout.

### 2.5 Device Tokens (Push Notification)

Untuk mobile app (Flutter) yang pakai FCM.

**GET /api/v1/device-tokens** — Daftar device terdaftar:
```json
{
  "data": [
    { "uuid": "...", "fcm_token": "...", "platform": "android", "created_at": "..." }
  ]
}
```

**POST /api/v1/device-tokens** — Daftarkan device:
```json
{ "fcm_token": "fcm-token-x", "platform": "android" }
```
`platform`: `android` atau `ios`.

**DELETE /api/v1/device-tokens/{uuid}** — Hapus device (matikan notifikasi di device itu).

---

## 3. Privasi dan Data

### 3.1 Unduh Data Akun (Right to Data Portability)

**POST /api/v1/profile/export**

Export semua data user ke JSON (profil, subscriptions, scan histories, chat consents).

**Response:**
```json
{
  "data": {
    "download_url": "https://be-skincek.test/api/v1/profile/exports/download?file=...&expires=...",
    "expires_in_minutes": 30
  },
  "meta": { "message": "Data akun siap diunduh" }
}
```

> Download URL adalah **signed URL yang expired 30 menit**. Langsung download saat dapat response.

**Isi file export:** profile, subscriptions, scan_histories (dengan image_url), doctor_verification (null untuk user), messages_count, device_tokens_count, ai_chat_consents.

### 3.2 Persetujuan AI Chat (UU PDP)

**GET /api/v1/ai-chat/consent** — Status persetujuan:
```json
{
  "data": {
    "accepted": true,
    "version": "1.0",
    "text": "Dengan menyetujui, kamu... [isi consent text]",
    "accepted_at": "2026-08-29T10:00:00.000000Z"
  }
}
```

**POST /api/v1/ai-chat/consent** — Set/cabut persetujuan:
```json
{ "accepted": true }
```
```json
{ "accepted": false }
```
> Jika dicabut (`accepted: false`), user tidak bisa chat dengan Aura Skin sampai setuju lagi (403).

### 3.3 Hapus Foto Avatar

**DELETE /api/v1/profile/avatar** — Hapus foto profil (fallback ke Google avatar jika ada).

### 3.4 Hapus Akun (Right to Erasure)

**DELETE /api/v1/profile**

Soft delete akun + logout semua device. Data dihapus permanen setelah **30 hari grace period**.

```json
{ "meta": { "message": "Akun berhasil dihapus" } }
```

> **WAJIB tampilkan konfirmasi** di frontend: "Akun akan dihapus permanen dalam 30 hari. Tindakan ini tidak bisa dibatalkan." — Button merah + ketik konfirmasi.

---

## Checklist Testing Frontend

### Profil Akun
- [ ] GET /profile menampilkan semua field termasuk `subscription_status`, `scan_count`, `remaining_free_messages`
- [ ] PATCH /profile sukses update nama
- [ ] Upload avatar kedua kali dalam 24 jam → 422
- [ ] `avatar_url` expired setelah 60 menit → auto re-fetch

### Login & Keamanan
- [ ] Riwayat login menampilkan device + lokasi
- [ ] Badge "Sesi ini" muncul di sesi aktif
- [ ] Cabut sesi device lain → device itu logout
- [ ] Ubah password salah → 422 "Password lama tidak sesuai"
- [ ] Kirim OTP → email masuk → verifikasi sukses
- [ ] Logout all → semua device kembali ke halaman login

### Privasi & Data
- [ ] Export data → download file JSON
- [ ] Download URL expired setelah 30 menit → 403
- [ ] Cabut consent AI → chat Aura Skin terblokir (403)
- [ ] Setujui lagi → chat bisa dilanjut
- [ ] Hapus akun → konfirmasi → logout → tidak bisa login
