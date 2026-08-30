# Dokumentasi Halaman Profil — Role: DOCTOR

> Halaman profil akun untuk dokter.
> Semua endpoint membutuhkan `Authorization: Bearer {token}` + role `doctor`.

---

## Struktur Halaman

```
Halaman Profil Dokter
├── 1. Profil Akun
│   ├── Data dasar (nama, email, avatar, DOB, gender)
│   ├── Status verifikasi dokter
│   └── Statistik konten (produk, rekomendasi)
├── 2. Login dan Keamanan
│   ├── Riwayat login & sesi aktif
│   ├── Ubah password
│   ├── Verifikasi email
│   ├── Logout semua perangkat
│   └── Device tokens (push notification)
└── 3. Privasi dan Data
    ├── Unduh data akun (export)
    ├── Data verifikasi dokter (STR, dokumen)
    ├── Persetujuan AI Chat (UU PDP)
    ├── Hapus avatar
    └── Hapus akun
```

---

## 1. Profil Akun

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

**Penjelasan field khusus doctor:**

| Field | Keterangan | UI Suggestion |
|---|---|---|
| `verification_status` | `pending`, `approved`, `rejected`, `needs_revision`, atau `unverified` | Badge status verifikasi |
| `product_count` | Total skincare products yang dibuat dokter | Statistik card |
| `recommendation_count` | Total skin recommendations yang dibuat | Statistik card |

**UI untuk `verification_status`:**

| Status | Badge | Aksi UI |
|---|---|---|
| `unverified` | Abu-abu "Belum Terverifikasi" | Tampilkan tombol "Ajukan Verifikasi" |
| `pending` | Kuning "Menunggu Review" | Info "Pengajuan sedang diproses admin" |
| `approved` | Hijau "Terverifikasi" | Tampilkan detail verifikasi (lihat 3.2) |
| `rejected` | Merah "Ditolak" | Tampilkan `rejection_reason` + tombol "Ajukan Ulang" |
| `needs_revision` | Oranye "Perlu Revisi" | Tampilkan `revision_note` + tombol "Perbaiki Data" |

### PATCH /api/v1/profile

Sama dengan user (nama, password, avatar, DOB, gender). Lihat PROFILE_PAGE_USER.md.

### Data Verifikasi Dokter

**GET /api/v1/doctor-verifications** — Status verifikasi lengkap:

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
      { "uuid": "doc-uuid", "url": "https://...", "file_name": "str.pdf" }
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

**POST /api/v1/doctor-verifications** — Ajukan verifikasi baru (jika belum ada / sudah rejected):

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

**POST /api/v1/doctor-verifications/{uuid}/resubmit** — Perbaiki data (hanya jika `needs_revision`), format sama dengan submit, semua field `sometimes`.

---

## 2. Login dan Keamanan

### 2.1 Riwayat Login & Sesi Aktif

**GET /api/v1/login-activity** — Sama dengan user. Lihat PROFILE_PAGE_USER.md bagian 2.1.

**DELETE /api/v1/login-activity/{uuid}** — Cabut satu sesi.

### 2.2 Ubah Password

**POST /api/v1/profile/change-password** — Sama dengan user.

### 2.3 Verifikasi Email

**POST /api/v1/email/verify/send** + **POST /api/v1/email/verify** — Sama dengan user.

### 2.4 Logout Semua Perangkat

**POST /api/v1/logout-all** — Sama dengan user.

### 2.5 Device Tokens (Push Notification)

**GET / POST /api/v1/device-tokens**, **DELETE /api/v1/device-tokens/{uuid}** — Sama dengan user.

---

## 3. Privasi dan Data

### 3.1 Unduh Data Akun

**POST /api/v1/profile/export** — Sama dengan user.

**Isi tambahan khusus dokter** — field `doctor_verification`:
```json
{
  "doctor_verification": {
    "specialization": "Dermatologi Klinik",
    "verification_status": "approved",
    "submitted_at": "2026-08-27T10:00:00.000000Z"
  }
}
```

### 3.2 Data Verifikasi Dokter (Akses Dokumen)

Dokumen verifikasi (STR, sertifikat) hanya bisa dilihat lewat:
- **GET /api/v1/doctor-verifications** (milik sendiri)

URL dokumen di `documents[].url` adalah **signed URL yang expired 60 menit**.

> Hanya dokter pemilik dan admin yang bisa lihat dokumen. Dokter TIDAK bisa lihat dokumen dokter lain.

### 3.3 Persetujuan AI Chat (UU PDP)

**GET /api/v1/ai-chat/consent**, **POST /api/v1/ai-chat/consent** — Sama dengan user.

> Catatan: Consent AI Chat wajib untuk chat dengan bot Aura Skin. Dokter chat dengan user biasa tidak perlu consent ini.

### 3.4 Hapus Foto Avatar

**DELETE /api/v1/profile/avatar** — Sama dengan user.

### 3.5 Hapus Akun

**DELETE /api/v1/profile** — Sama dengan user (soft delete + 30 hari grace period).

> **Perhatian khusus dokter:** Jika dokter punya artikel/rekomendasi/produk yang sudah dipakai user, data terkait (skincare products, skin recommendations, conversations) akan ikut terhapus permanen setelah grace period. Tampilkan warning tambahan di UI.

---

## Checklist Testing Frontend

### Profil Akun
- [ ] GET /profile menampilkan `verification_status`, `product_count`, `recommendation_count`
- [ ] Badge verifikasi sesuai status (5 varian)
- [ ] Status `unverified` → tombol "Ajukan Verifikasi"
- [ ] Status `rejected` → tampilkan alasan + tombol ajukan ulang
- [ ] Status `needs_revision` → tampilkan catatan + tombol perbaiki
- [ ] Submit verifikasi sukses → status jadi `pending`
- [ ] Upload dokumen invalid (bukan pdf/gambar) → 422

### Login & Keamanan
- [ ] Semua fitur sama dengan user (lihat PROFILE_PAGE_USER.md)

### Privasi & Data
- [ ] Export data include `doctor_verification`
- [ ] URL dokumen expired 60 menit → re-fetch
- [ ] Hapus akun → warning konsekuensi data konten
