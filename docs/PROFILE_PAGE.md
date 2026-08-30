# Dokumentasi Halaman Profil Akun (Pengaturan Akun) — Semua Role

> Halaman "Profil" di frontend ini adalah **pengaturan akun** (account settings) milik sendiri — BUKAN halaman stalking dokter (untuk itu, lihat CONSULTATION_FLOW.md).
>
> Struktur halaman sama untuk semua role: **1. Profil Akun → 2. Login dan Keamanan → 3. Privasi dan Data**. Yang berbeda hanya konten section 1 per role.
>
> Semua endpoint membutuhkan `Authorization: Bearer {token}`.

---

## Struktur Halaman (Semua Role)

```
Halaman Pengaturan Akun
├── 1. Profil Akun (data role)
├── 2. Login dan Keamanan
└── 3. Privasi dan Data
```

| Role | Section 1 berisi |
|---|---|
| **USER** | Data dasar + subscription (Pro/Free) + statistik scan & chat |
| **DOCTOR** | Data dasar + status verifikasi + statistik konten |
| **ADMIN** | Data dasar + login terakhir + ringkasan platform |

---

# BAGIAN 1: PROFIL AKUN

## A. Role USER

### GET /api/v1/profile

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

**Field khusus user:**

| Field | Keterangan | UI Suggestion |
|---|---|---|
| `subscription_status` | `"Pro"` jika subscription active, `"Free"` jika tidak | Badge hijau "Pro" / abu-abu "Free" |
| `scan_count` | Total scan wajah yang pernah dilakukan | Statistik card "Total Scan" |
| `user_messages_count` | Total pesan AI chat (Aura Skin) yang dikirim | Statistik card |
| `remaining_free_messages` | Sisa pesan AI gratis (max 3, unlimited jika Pro) | Progress bar / counter "Sisa X pesan" |

> `avatar_url` adalah signed URL yang **expired dalam 60 menit**. Re-fetch profile saat expired.

## B. Role DOCTOR

### GET /api/v1/profile

**Response:**
```json
{
  "data": {
    "uuid": "doctor-uuid",
    "full_name": "dr. Sari Dewi",
    "email": "sari@skincek.com",
    "role": "doctor",
    "avatar_url": "https://signed-url-60min",
    "google_avatar_url": null,
    "date_of_birth": "1990-03-20",
    "gender": "perempuan",
    "profile_completed": true,
    "email_verified": true,

    "verification_status": "approved",
    "product_count": 5,
    "recommendation_count": 12
  }
}
```

**Field khusus doctor:**

| Field | Keterangan | UI Suggestion |
|---|---|---|
| `verification_status` | `pending`, `approved`, `rejected`, `needs_revision`, `unverified` | Badge status verifikasi |
| `product_count` | Total skincare products buatan dokter | Statistik card |
| `recommendation_count` | Total skin recommendations buatan dokter | Statistik card |

**UI untuk `verification_status`:**

| Status | Badge | Aksi UI |
|---|---|---|
| `unverified` | Abu-abu "Belum Terverifikasi" | Tombol "Ajukan Verifikasi" |
| `pending` | Kuning "Menunggu Review" | Info "Pengajuan sedang diproses admin" |
| `approved` | Hijau "Terverifikasi" | Tampilkan detail verifikasi |
| `rejected` | Merah "Ditolak" | Tampilkan `rejection_reason` + tombol "Ajukan Ulang" |
| `needs_revision` | Oranye "Perlu Revisi" | Tampilkan `revision_note` + tombol "Perbaiki Data" |

### Data Verifikasi Dokter (Milik Sendiri)

**GET /api/v1/doctor-verifications** — Status verifikasi lengkap (khusus role doctor):

**Response (200):**
```json
{
  "data": {
    "uuid": "verification-uuid",
    "str_number": "STR-123456",
    "title": "dr. Sp.D",
    "specialization": "Dermatologi Klinik",
    "sub_specialization": "Aesthetics",
    "experience_years": 8,
    "alma_mater": "Universitas Indonesia",
    "practice_locations": ["RS Pondok Indah", "Klinik Kulit Sehat"],
    "professional_organizations": ["PERDOSKI"],
    "verification_status": "approved",
    "rejection_reason": null,
    "revision_note": null,
    "documents": [
      { "uuid": "doc-uuid", "url": "https://signed-url", "file_name": "str.pdf" }
    ],
    "reviewed_at": "2026-08-28T14:00:00.000000Z",
    "created_at": "2026-08-27T10:00:00.000000Z"
  }
}
```

**Response (404)** — belum pernah mengajukan:
```json
{ "success": false, "message": "Belum ada pengajuan verifikasi" }
```

**POST /api/v1/doctor-verifications** — Ajukan verifikasi (jika belum ada / sudah rejected):

**Request (multipart/form-data):**
```json
{
  "str_number": "STR-123456",
  "specialization": "Dermatologi Klinik",
  "title": "dr. Sp.D",
  "sub_specialization": "Aesthetics",
  "experience_years": 8,
  "alma_mater": "Universitas Indonesia",
  "practice_locations": ["RS Pondok Indah"],
  "professional_organizations": ["PERDOSKI"],
  "documents": ["[file jpeg/jpg/png/pdf, max 5MB]"]
}
```

**Validasi:**
- `specialization`: **required**
- `documents`: **required** (min 1 file), mimes `jpeg,jpg,png,pdf`, max 5120 KB
- `practice_locations` / `professional_organizations`: array max 20 item

**POST /api/v1/doctor-verifications/{uuid}/resubmit** — Perbaiki data (hanya jika `needs_revision`), format sama, semua field `sometimes`.

## C. Role ADMIN

### GET /api/v1/admin/profile

Endpoint **khusus admin** — profil + info sesi + ringkasan platform.

**Response:**
```json
{
  "data": {
    "uuid": "admin-uuid",
    "full_name": "Admin SkinCek",
    "email": "admin@skincek.com",
    "role": "admin",
    "avatar_url": "https://signed-url-60min",
    "email_verified": true,
    "account_created_at": "2026-01-15T08:30:00.000000Z",

    "last_login": {
      "at": "2026-08-29T10:15:30.000000Z",
      "ip_address": "192.168.1.10",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0...)"
    },
    "active_sessions": 3,

    "summary": {
      "total_users": 152,
      "total_doctors": 8,
      "pending_doctor_verifications": 2
    }
  }
}
```

**Penjelasan field:**

| Field | Keterangan | UI Suggestion |
|---|---|---|
| `account_created_at` | Kapan akun admin dibuat | "Bergabung Jan 2026" |
| `last_login.at` | Login terakhir (token terbaru) | "Login terakhir: 29 Agu, 17:15 WIB" |
| `last_login.ip_address` | IP login terakhir | Tampilkan dengan lokasi |
| `last_login.user_agent` | Browser/device login terakhir | Parse jadi "Chrome di Windows" |
| `active_sessions` | Jumlah device yang sedang login | "Kamu login di 3 perangkat" |
| `summary.total_users` | Total user di platform | Statistic card |
| `summary.total_doctors` | Total dokter (exclude AI bot) | Statistic card |
| `summary.pending_doctor_verifications` | Verifikasi menunggu review | Badge merah jika > 0 + link ke halaman verifikasi |

Admin juga bisa pakai `GET /api/v1/profile` (data dasar + `pending_doctor_verifications`), tapi **gunakan `GET /admin/profile` untuk halaman profil admin** karena datanya lebih lengkap.

## D. Update Profil (Semua Role)

### PATCH /api/v1/profile

Semua field optional (`sometimes`).

**Request (multipart/form-data jika ada avatar):**
```json
{
  "full_name": "Nama Baru",
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
    "full_name": "Nama Baru",
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

# BAGIAN 2: LOGIN DAN KEAMANAN (Semua Role)

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

> **Security note (khusus admin):** Admin sebaiknya rutin cek sesi tidak dikenal dan cabut langsung. Tampilkan warning di UI jika ada sesi dari device/IP tidak dikenal.

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

> **Rekomendasi UI untuk admin:** wajibkan password kuat (min 12 karakter) + indikator kekuatan password.

### 2.3 Verifikasi Email (OTP)

**POST /api/v1/email/verify/send** — Kirim OTP 6 digit ke email (berlaku 10 menit, max 5 attempt salah).
**POST /api/v1/email/verify** — Verifikasi dengan OTP:
```json
{ "otp": "123456" }
```

### 2.4 Logout Semua Perangkat

**POST /api/v1/logout-all** — Hapus semua token, semua device logout.

### 2.5 Device Tokens (Push Notification FCM — User & Doctor)

Untuk mobile app (Flutter). Admin tidak memakainya.

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

# BAGIAN 3: PRIVASI DAN DATA

### 3.1 Unduh Data Akun (Right to Data Portability)

**POST /api/v1/profile/export**

Export semua data user ke JSON (profil, subscriptions, scan histories, consents).

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

**Isi file export:** profile, subscriptions, scan_histories (dengan image_url), doctor_verification (khusus doctor), messages_count, device_tokens_count, ai_chat_consents.

> **Catatan admin:** Export hanya berisi data pribadi sendiri — BUKAN data user/dokter lain. Untuk data platform, admin punya `/admin/users`, `/admin/activity-log`, dll.

### 3.2 Persetujuan AI Chat (UU PDP — User & Doctor)

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

> Jika dicabut (`accepted: false`), user tidak bisa chat dengan Aura Skin sampai setuju lagi (403). Consent AI Chat hanya untuk bot Aura Skin — chat dengan dokter biasa tidak perlu consent ini.

### 3.3 Hapus Foto Avatar

**DELETE /api/v1/profile/avatar** — Hapus foto profil (fallback ke Google avatar jika ada).
```json
{ "meta": { "message": "Foto profil berhasil dihapus" } }
```

### 3.4 Hapus Akun (Right to Erasure)

**DELETE /api/v1/profile**

Soft delete akun + logout semua device. Data dihapus permanen setelah **30 hari grace period**.

```json
{ "meta": { "message": "Akun berhasil dihapus" } }
```

**Konfirmasi WAJIB di frontend:** "Akun akan dihapus permanen dalam 30 hari. Tindakan ini tidak bisa dibatalkan." — Button merah + ketik konfirmasi.

**Warning tambahan per role:**
- **Doctor:** Data konten (skincare products, skin recommendations, conversations) ikut terhapus permanen setelah grace period. Tampilkan warning tambahan.
- **Admin:** Sangat sensitif. Pastikan minimal ada 1 admin aktif lain sebelum mengizinkan hapus. Jika admin terakhir → blokir dengan pesan error. Konfirmasi berlapis (warning + input ketik "HAPUS" + cek admin terakhir).

---

## Perbandingan Fitung per Role

| Fitur | USER | DOCTOR | ADMIN |
|---|---|---|---|
| Data profil dasar | `GET /profile` | `GET /profile` | `GET /profile` + `GET /admin/profile` |
| Field tambahan section 1 | subscription, scan, chat | verification, product, recommendation | last_login, sessions, summary |
| Login activity | ✅ | ✅ | ✅ |
| Ubah password | ✅ | ✅ | ✅ (+ rekomendasi password kuat) |
| Verifikasi email | ✅ | ✅ | ✅ (biasanya sudah verified) |
| Logout all | ✅ | ✅ | ✅ |
| Device tokens (FCM) | ✅ | ✅ | ⚪ Tidak dipakai |
| Export data | ✅ | ✅ (+ doctor_verification) | ✅ (hanya data pribadi) |
| AI Chat consent | ✅ | ✅ | ⚪ Tidak relevan |
| Data verifikasi dokter | ❌ | ✅ (milik sendiri) | ❌ (punya endpoint admin sendiri) |
| Hapus akun | ✅ | ✅ (+ warning data konten) | ✅ (+ warning admin terakhir) |

---

## Checklist Testing Frontend

### Profil Akun
- [ ] Semua role: GET /profile menampilkan field sesuai role (tabel di atas)
- [ ] PATCH /profile sukses update nama
- [ ] Upload avatar kedua kali dalam 24 jam → 422
- [ ] `avatar_url` expired setelah 60 menit → auto re-fetch
- [ ] USER: badge Pro/Free, sisa pesan gratis tampil
- [ ] DOCTOR: 5 varian badge verifikasi + aksi sesuai status
- [ ] DOCTOR: submit/resubmit verifikasi sukses → status `pending`
- [ ] DOCTOR: dokumen invalid (bukan pdf/gambar) → 422
- [ ] ADMIN: last_login (at, ip, user_agent) + active_sessions + summary tampil
- [ ] ADMIN: badge pending verifikasi > 0 → merah + link ke halaman verifikasi

### Login & Keamanan
- [ ] Riwayat login menampilkan device + lokasi
- [ ] Badge "Sesi ini" muncul di sesi aktif
- [ ] Cabut sesi device lain → device itu logout
- [ ] Ubah password salah → 422 "Password lama tidak sesuai"
- [ ] Kirim OTP → email masuk → verifikasi sukses
- [ ] Logout all → semua device kembali ke halaman login
- [ ] (Mobile) register/unregister FCM device token jalan

### Privasi & Data
- [ ] Export data → download file JSON
- [ ] Download URL expired setelah 30 menit → 403
- [ ] Cabut consent AI → chat Aura Skin terblokir (403)
- [ ] Setujui lagi → chat bisa dilanjut
- [ ] Hapus akun → konfirmasi → logout → tidak bisa login
- [ ] DOCTOR: warning data konten muncul di konfirmasi hapus
- [ ] ADMIN: konfirmasi berlapis + blokir jika admin terakhir
