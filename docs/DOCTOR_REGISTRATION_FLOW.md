# Alur Registrasi Dokter — Panduan Frontend

> Dokumen ini menjelaskan alur lengkap registrasi akun dokter:
> dari form registrasi → submit → status pending → verifikasi admin → dokter aktif.
>
> Endpoint: `POST /api/v1/register-doctor` (public, throttle 5/menit)

---

## Gambaran Alur

```
┌─────────────────────────────────────────────────────────────────┐
│ ALUR REGISTRASI DOKTER                                          │
│                                                                 │
│  1. FORM REGISTRASI                                             │
│     ├── Data akun (nama, email, password)                       │
│     ├── Data profesional (spesialisasi, STR, dll)               │
│     ├── Upload dokumen (STR/sertifikat)                          │
│     └── Centang persetujuan privasi (UU PDP)                    │
│                         │                                       │
│                         ▼                                       │
│  2. POST /register-doctor                                       │
│     └── 201: Akun dibuat + role "doctor"                        │
│         + DoctorVerification status PENDING                     │
│                         │                                       │
│                         ▼                                       │
│  3. LOGIN SUKSES (token langsung aktif)                         │
│     └── TAPI belum bisa: chat konsultasi, muncul di list dokter │
│                         │                                       │
│                         ▼                                       │
│  4. VERIFIKASI EMAIL (OTP) — wajib sebelum pakai fitur lain     │
│                         │                                       │
│                         ▼                                       │
│  5. MENUNGU REVIEW ADMIN                                        │
│     ├── approved       → dokter aktif penuh ✅                  │
│     ├── rejected       → ajukan ulang dengan data benar         │
│     └── needs_revision → perbaiki data → resubmit              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 1. Form Registrasi (Data yang Dibutuhkan)

### Required (wajib diisi)

| Field | Tipe | Validasi | Contoh | UI |
|---|---|---|---|---|
| `full_name` | string | max 255 | `dr. Sari Dewi` | Input text |
| `email` | string | format email, **unik** (exclude soft-deleted) | `sari@gmail.com` | Input email + cek duplikat |
| `password` | string | min 8 | `password123` | Input password + indikator kekuatan |
| `specialization` | string | max 255 | `Dermatologi Klinik` | Input text / dropdown |
| `documents` | file[] | min 1 file, mimes `jpeg,jpg,png,pdf`, max **5 MB/file** | `str.pdf`, `sertifikat.jpg` | File upload multi |
| `privacy_consent` | boolean | harus `true` (`accepted`) | `true` | Checkbox persetujuan |

### Optional (nullable, boleh dikosongkan)

| Field | Tipe | Validasi | Contoh | UI |
|---|---|---|---|---|
| `str_number` | string | max 50 | `STR-123456` | Input text |
| `title` | string | max 100 | `dr. Sp.D` | Input text (gelar) |
| `sub_specialization` | string | max 255 | `Aesthetics & Laser` | Input text |
| `experience_years` | integer | 0–100 | `8` | Number input |
| `alma_mater` | string | max 255 | `Universitas Indonesia` | Input text |
| `practice_locations` | string[] | max 20 item, tiap item max 255 | `["RS Pondok Indah", "Klinik Sehat"]` | Tag input / multi chip |
| `professional_organizations` | string[] | max 20 item, tiap item max 100 | `["PERDOSKI"]` | Tag input / multi chip |

### Contoh Request

```
POST /api/v1/register-doctor
Content-Type: multipart/form-data

full_name: dr. Sari Dewi
email: sari@gmail.com
password: password123
specialization: Dermatologi Klinik
str_number: STR-123456
title: dr. Sp.D
sub_specialization: Aesthetics & Laser
experience_years: 8
alma_mater: Universitas Indonesia
practice_locations[]: RS Pondok Indah
practice_locations[]: Klinik Kulit Sehat
professional_organizations[]: PERDOSKI
documents[]: [FILE str.pdf]
documents[]: [FILE sertifikat.jpg]
privacy_consent: true
```

### Response Sukses (201)

```json
{
  "data": {
    "user": {
      "uuid": "doctor-uuid",
      "full_name": "dr. Sari Dewi",
      "email": "sari@gmail.com",
      "avatar_url": null,
      "google_avatar_url": null,
      "profile_completed": false,
      "email_verified": false
    },
    "token": "1|abcdef..."
  },
  "meta": { "message": "Registrasi dokter berhasil diajukan." }
}
```

> **Token langsung aktif** — user sudah login setelah register. Simpan token seperti login biasa.

### Error Validation (422)

```json
{
  "message": "The email has already been taken. (and 2 more errors)",
  "errors": {
    "email": ["The email has already been taken."],
    "documents": ["The documents field is required."],
    "privacy_consent": ["The privacy consent field must be accepted."]
  }
}
```

---

## 2. Yang Terjadi Setelah Submit

Backend membuat **2 hal sekaligus** dalam 1 transaction:

1. **User** — role `doctor`, `privacy_consent_at` terisi, langsung aktif
2. **DoctorVerification** — status `pending`, berisi semua data profesional + dokumen

**Status dokter baru = `pending`:**

| Bisa | Tidak Bisa |
|---|---|
| ✅ Login (token aktif) | ❌ Muncul di list dokter (`GET /doctors`) |
| ✅ Lihat profil sendiri | ❌ Chat konsultasi dengan user |
| ✅ Upload skincare products / recommendations | ❌ Dicontact user |
| ✅ Lihat status verifikasi sendiri | |

> Dokter baru **belum muncul di list dokter publik** sampai admin approve. `GET /doctors` hanya menampilkan yang `approved`.

---

## 3. Verifikasi Email (Wajib Setelah Register)

Sama seperti user biasa — wajib verifikasi email sebelum pakai fitur lain (scan tidak relevan, tapi chat & produk bisa diblokir):

1. `POST /api/v1/email/verify/send` — kirim OTP 6 digit ke email (berlaku 10 menit)
2. `POST /api/v1/email/verify` — `{"otp": "123456"}`

> Setelah verifikasi email, dokter bisa upload produk & rekomendasi, tapi **chat konsultasi tetap terblokir** sampai verifikasi dokter di-approve admin.

---

## 4. Menunggu Review Admin

Dokter bisa pantau status verifikasi sendiri:

**GET /api/v1/doctor-verifications** (role doctor)

```json
{
  "data": {
    "uuid": "verification-uuid",
    "str_number": "STR-123456",
    "specialization": "Dermatologi Klinik",
    "verification_status": "pending",
    "rejection_reason": null,
    "revision_note": null,
    "documents": [
      { "uuid": "doc-uuid", "url": "https://signed-url", "file_name": "str.pdf" }
    ],
    "reviewed_at": null,
    "created_at": "2026-08-30T10:00:00.000000Z"
  }
}
```

**404** jika belum pernah submit (tidak mungkin untuk dokter yang register via endpoint ini).

### Kemungkinan Status Setelah Review Admin

| Status | Arti | Notifikasi | Aksi Dokter |
|---|---|---|---|
| `approved` | Verifikasi disetujui | Toast success "Verifikasi Dokter" | Dokter aktif penuh — muncul di list, bisa chat |
| `rejected` | Ditolak (ada `rejection_reason`) | Toast error | Bisa ajukan ulang lewat `POST /doctor-verifications` |
| `needs_revision` | Perlu revisi (ada `revision_note`) | Toast warning | Perbaiki lewat `POST /doctor-verifications/{uuid}/resubmit` |

### Resubmit Jika Revisi/Ditolak

**POST /api/v1/doctor-verifications/{uuid}/resubmit** (hanya jika `needs_revision`)

Format sama dengan registrasi awal, semua field `sometimes` (yang dikirim saja yang diupdate):

```
Content-Type: multipart/form-data

specialization: Dermatologi Klinik
title: dr. Sp.D
documents[]: [FILE str_baru.pdf]
```

Jika `rejected` → pakai `POST /api/v1/doctor-verifications` lagi (submit baru, mengganti yang lama).

---

## 5. UI Flow Suggestion

### Form Registrasi (Multi-step, disarankan)

```
Step 1: Data Akun
├── full_name
├── email
└── password (+ konfirmasi password client-side)

Step 2: Data Profesional
├── specialization * (required)
├── sub_specialization
├── title
├── str_number
├── experience_years
└── alma_mater

Step 3: Praktik & Organisasi
├── practice_locations (tag input)
└── professional_organizations (tag input)

Step 4: Upload Dokumen & Consent
├── documents (multi file, drag & drop)
│   └── Validasi client-side: jpeg/jpg/png/pdf, max 5MB/file
└── ☐ Saya menyetujui kebijakan privasi SkinCek (UU PDP)
     [ Button: Daftar Sebagai Dokter ]
```

### Setelah Register Sukses

```
┌─────────────────────────────────────────┐
│ ✅ Registrasi Berhasil!                 │
│                                         │
│ Akun dokter kamu sudah dibuat dan       │
│ SEDANG DIVERIFIKASI oleh admin.         │
│                                         │
│ Langkah selanjutnya:                    │
│ 1. ✅ Cek email untuk verifikasi OTP    │
│ 2. ⏳ Tunggu review admin (1-3 hari)    │
│                                         │
│ Kamu sudah bisa:                        │
│ • Upload produk skincare                │
│ • Upload rekomendasi perawatan          │
│                                         │
│ Belum bisa sampai verified:             │
│ • Chat konsultasi dengan user           │
│ • Muncul di daftar dokter               │
│                                         │
│ [ Verifikasi Email Sekarang ]  [ Nanti ] │
└─────────────────────────────────────────┘
```

---

## 6. Perbedaan Register User vs Register Doctor

| Aspek | Register User (`POST /register`) | Register Doctor (`POST /register-doctor`) |
|---|---|---|
| Endpoint | `POST /api/v1/register` | `POST /api/v1/register-doctor` |
| Field akun | full_name, email, password, privacy_consent | sama |
| Data profesional | ❌ Tidak ada | ✅ specialization dll |
| Upload dokumen | ❌ Tidak ada | ✅ `documents` required |
| DoctorVerification | Tidak dibuat | ✅ Dibuat, status `pending` |
| Langsung aktif penuh | ✅ Ya (setelah verifikasi email) | ❌ Harus tunggu admin approve |
| Muncul di list dokter | — | Setelah `approved` saja |
| Bisa chat konsultasi | ✅ (setelah email verified) | ❌ Setelah verifikasi `approved` |

---

## 7. Error Handling

| Skenario | Status | Pesan | Aksi UI |
|---|---|---|---|
| Email sudah dipakai | `422` | `"The email has already been taken."` | Inline error + saran login |
| Dokumen kosong | `422` | `"The documents field is required."` | Highlight upload area |
| Dokumen > 5MB / format salah | `422` | `"The documents.0 failed to upload..."` | Cek client-side sebelum submit |
| `privacy_consent` false | `422` | `"The privacy consent field must be accepted."` | Disable submit button |
| Terlalu banyak percobaan | `429` | Throttle 5/menit | Tunggu + tampilkan countdown |

---

## 8. Checklist Testing Frontend

### Form
- [ ] Semua field required tampil dengan tanda *
- [ ] Multi-step form: data tersimpan di state antar step
- [ ] Tag input `practice_locations` + `professional_organizations` jalan (add/remove)
- [ ] File upload validasi client-side: mimes + size sebelum submit
- [ ] Checkbox consent → submit button disabled jika belum centang
- [ ] Password < 8 char → inline error

### Submit
- [ ] Sukses 201 → simpan token → redirect halaman sukses (bagian 5)
- [ ] Email duplikat → 422 inline error
- [ ] Dokumen kosong → 422
- [ ] Loading state selama request (upload bisa lambat)

### Setelah Register
- [ ] Status verifikasi di profil = `pending` (badge kuning)
- [ ] Verifikasi email OTP jalan (kirim → masuk → submit OTP)
- [ ] `GET /doctor-verifications` menampilkan data yang baru saja di-submit
- [ ] Dokumen ter-upload (lihat `documents` array di response)
- [ ] Dokter baru TIDAK muncul di `GET /doctors` (belum approved)

### Admin Approve
- [ ] Setelah admin approve → toast realtime "Verifikasi Dokter" (success)
- [ ] Badge status di profil berubah jadi hijau "Terverifikasi"
- [ ] Dokter baru MUNCUL di `GET /doctors`
- [ ] Chat konsultasi dengan user bisa dilakukan
