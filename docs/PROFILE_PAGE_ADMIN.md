# Dokumentasi Halaman Profil — Role: ADMIN

> Halaman profil akun untuk admin platform.
> Semua endpoint membutuhkan `Authorization: Bearer {token}` + role `admin`.

---

## Struktur Halaman

```
Halaman Profil Admin
├── 1. Profil Akun
│   ├── Data dasar (nama, email, avatar, role)
│   ├── Info login terakhir (IP, device, waktu)
│   ├── Ringkasan platform (total user, dokter, verifikasi pending)
│   └── Ubah data profil
├── 2. Login dan Keamanan
│   ├── Sesi aktif & riwayat login
│   ├── Ubah password
│   ├── Logout semua perangkat
│   └── (Admin tidak pakai device tokens / push notification)
└── 3. Privasi dan Data
    ├── Unduh data akun (export)
    └── Hapus akun
```

---

## 1. Profil Akun

### GET /api/v1/admin/profile

Endpoint **khusus admin** — profil + ringkasan platform + info sesi.

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
| `last_login.ip_address` | IP login terakhir | Tampilkan dengan lokasi jika ada |
| `last_login.user_agent` | Browser/device login terakhir | Parse jadi "Chrome di Windows" |
| `active_sessions` | Jumlah device yang sedang login | "Kamu login di 3 perangkat" |
| `summary.total_users` | Total user (role user) di platform | Statistic card |
| `summary.total_doctors` | Total dokter (exclude AI bot) | Statistic card |
| `summary.pending_doctor_verifications` | Verifikasi menunggu review | Badge merah jika > 0 + link ke halaman verifikasi |

### GET /api/v1/profile

Admin juga bisa pakai endpoint umum. Response-nya:
```json
{
  "data": {
    "uuid": "...",
    "full_name": "...",
    "email": "...",
    "role": "admin",
    "avatar_url": "...",
    "google_avatar_url": null,
    "date_of_birth": null,
    "gender": null,
    "profile_completed": false,
    "email_verified": true,

    "pending_doctor_verifications": 2
  }
}
```

> **Gunakan `GET /admin/profile` untuk halaman profil admin** karena datanya lebih lengkap (last_login, active_sessions, summary). `GET /profile` cukup untuk cek data dasar.

### PATCH /api/v1/profile

Sama dengan user (nama, password, avatar, DOB, gender). Lihat PROFILE_PAGE_USER.md bagian 1.

---

## 2. Login dan Keamanan

### 2.1 Sesi Aktif & Riwayat Login

**GET /api/v1/login-activity**

Daftar semua sesi admin (device yang punya token aktif).

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

**DELETE /api/v1/login-activity/{uuid}** — Cabut satu sesi.
```json
{ "meta": { "message": "Sesi berhasil dicabut" } }
```

> **Security note:** Admin sebaiknya rutin cek sesi tidak dikenal dan cabut langsung. Tampilkan warning di UI jika ada sesi dari device/IP tidak dikenal.

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

> **Rekomendasi UI:** Untuk akun admin, wajibkan password kuat (min 12 karakter, kombinasi). Tampilkan indikator kekuatan password.

### 2.3 Verifikasi Email

Admin biasanya sudah verified (seeded). Endpoint sama dengan user:
- **POST /api/v1/email/verify/send** — kirim OTP
- **POST /api/v1/email/verify** — verifikasi OTP

### 2.4 Logout Semua Perangkat

**POST /api/v1/logout-all** — Hapus semua token, semua device logout.

### 2.5 Device Tokens

Admin tidak menggunakan FCM push notification (tidak ada endpoint khusus). Endpoint `/device-tokens` tetap tersedia tapi biasanya tidak dipakai di web admin.

---

## 3. Privasi dan Data

### 3.1 Unduh Data Akun

**POST /api/v1/profile/export**

Export data akun admin sendiri ke JSON.

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

> Download URL **expired 30 menit**. Langsung download saat dapat response.

**Catatan:** Export hanya berisi data pribadi admin — BUKAN data user/dokter lain. Untuk data platform, admin punya endpoint sendiri (`/admin/users`, `/admin/activity-log`, dll).

### 3.2 Hapus Akun

**DELETE /api/v1/profile**

Soft delete + logout semua device + permanen setelah **30 hari**.

```json
{ "meta": { "message": "Akun berhasil dihapus" } }
```

> **PERINGATAN UI:** Penghapusan akun admin sangat sensitif. Pastikan minimal ada 1 admin aktif lain sebelum mengizinkan hapus. Tampilkan konfirmasi berlapis:
> 1. Warning "Akun admin akan dihapus permanen dalam 30 hari"
> 2. Input ketik "HAPUS" atau password
> 3. Jika admin terakhir, blokir dengan pesan error

### 3.3 Activity Log (Tambahan untuk Admin)

Bukan bagian profil pribadi, tapi relevan untuk audit:

**GET /api/v1/admin/activity-log** — Log aktivitas semua user di platform (role change, verification review, dll). Lihat API_DOCUMENTATION.md untuk detail.

---

## Perbandingan Endpoint per Role

| Fitur | USER | DOCTOR | ADMIN |
|---|---|---|---|
| Data profil dasar | `GET /profile` | `GET /profile` | `GET /profile` + `GET /admin/profile` |
| Field tambahan | subscription, scan, chat | verification, product, recommendation | last_login, sessions, summary |
| Login activity | ✅ | ✅ | ✅ |
| Ubah password | ✅ | ✅ | ✅ |
| Verifikasi email | ✅ | ✅ | ✅ (biasanya sudah verified) |
| Logout all | ✅ | ✅ | ✅ |
| Device tokens (FCM) | ✅ | ✅ | ⚪ Tidak dipakai |
| Export data | ✅ | ✅ (+ doctor_verification) | ✅ |
| AI Chat consent | ✅ | ✅ | ⚪ Tidak relevan |
| Data verifikasi dokter | ❌ | ✅ | ❌ (punya endpoint admin sendiri) |
| Hapus akun | ✅ | ✅ (+ warning data konten) | ✅ (+ warning admin terakhir) |

---

## Checklist Testing Frontend

### Profil Akun
- [ ] GET /admin/profile menampilkan last_login (at, ip, user_agent)
- [ ] `active_sessions` = jumlah sesi di login-activity
- [ ] Badge pending verifikasi > 0 → merah + link ke halaman verifikasi
- [ ] `avatar_url` expired 60 menit → auto re-fetch

### Login & Keamanan
- [ ] Riwayat login menampilkan device + lokasi
- [ ] Badge "Sesi ini" di sesi aktif
- [ ] Cabut sesi lain → device itu logout
- [ ] Ubah password salah → 422
- [ ] Logout all → semua device kembali ke login

### Privasi & Data
- [ ] Export data → download JSON (hanya data pribadi, bukan data platform)
- [ ] Download URL expired 30 menit → 403
- [ ] Hapus akun → konfirmasi berlapis
- [ ] (Optional) Blokir hapus jika admin terakhir
