# SkinCek API Documentation

Dokumentasi lengkap REST API backend SkinCek untuk tim frontend **Next.js (web)** dan **Flutter (mobile)**.

---

## 1. Ringkasan

| Item | Keterangan |
|---|---|
| Base URL (lokal) | `http://be-skincek.test/api/v1` |
| Base URL (produksi) | `<belum ditentukan — tanyakan tim backend>` |
| Format request | `application/json` (kecuali upload file: `multipart/form-data`) |
| Format response | JSON |
| Versi API | `v1` (prefix `/api/v1`) |
| Autentikasi | Bearer Token (Sanctum), token **tidak pernah kedaluwarsa** |

---

## 2. Konvensi Umum

### 2.1 Envelope Respons

Semua endpoint sukses mengembalikan:

```json
{
  "data": { },
  "meta": { }
}
```

- `meta` berisi pesan sukses berbahasa Indonesia pada sebagian endpoint, misal `{"message": "..."}`.
- Endpoint yang **tidak** mengembalikan `meta` → `meta` berupa objek kosong `{}`.
- Endpoint tanpa data → `"data": null`.

Error selalu berbentuk:

```json
{
  "message": "Kredensial tidak valid"
}
```

Error validasi (422) menambahkan `errors`:

```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

### 2.2 Autentikasi

- Semua endpoint kecuali yang bertanda **Public** butuh header:

```
Authorization: Bearer <token>
```

- Token diambil dari respons `login` / `register` / `auth/google`: `data.token` (plain text, simpan aman).
- Tidak terautentikasi → `401` `{"message":"Unauthenticated"}`.

### 2.3 ID & UUID

Semua resource publik memakai **UUID string** (bukan integer). Route parameter model (misal `{conversation}`, `{predictionHistory}`, `{subscription}`) di-bind via UUID.

### 2.4 Pagination

- Query param `per_page`, hanya menerima `5`, `10`, `20`, `50`. Nilai lain / kosong → default `10`.
- Collection resource standar (Laravel) mengembalikan:

```json
{
  "data": [...],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": {
    "current_page": 1, "last_page": 1, "per_page": 10, "total": 2,
    "from": 1, "to": 2, "path": "...", "first_page_url": "...",
    "last_page_url": "...", "prev_page_url": null, "next_page_url": null
  }
}
```

- Pengecualian: `GET /notifications` dan `GET /doctors/{doctor}/ratings` memakai envelope custom tanpa `links` (lihat masing-masing).

### 2.5 URL Media (penting!)

URL file media (`avatar`, `image_url`, `media_url`, dll.) bisa berupa **signed URL sementara yang berlaku 60 menit**. Frontend **harus mengambil ulang data** (refresh list/detail) jika URL sudah kedaluwarsa — jangan cache URL-nya lebih dari 60 menit.

### 2.6 Rate Limit (429)

Jika terlampaui → `429` `{"message":"Too Many Attempts."}` + header `Retry-After`.

| Limiter | Batas | Scope |
|---|---|---|
| API global | 60/menit | per user (fallback IP) |
| Auth (login/register) | 5/menit | per email+IP |
| Google auth | 10/menit | per IP |
| Forgot password | 3 per 15 menit | per email+IP |
| Reset password | 10/menit | per email+IP |
| Scan | 30/menit | per user |

### 2.7 Kuota Fitur (gratis vs Pro)

| Fitur | Gratis | Pro |
|---|---|---|
| Scan kulit | **3/hari** → 429 di pesan ke-4 | Tanpa batas |
| Chat dokter | **3 pesan seumur hidup** (counter `user_messages_count`) → 402 | Tanpa batas |
| Chat Aura Skin (AI) | **10/hari** → 429 | Tanpa batas |
| Prioritas verifikasi dokter | — | ✅ |

Pro = ada subscription dengan `status = active` dan `ends_at` belum lewat (lihat bagian 12).

---

## 3. Autentikasi & Akun

### 3.1 Register User — `POST /register` — Public

Throttle: 5/menit.

Body (`application/json`):

| Field | Wajib | Validasi |
|---|---|---|
| `full_name` | ✅ | string, max 255 |
| `email` | ✅ | email, max 255, unik |
| `password` | ✅ | min 8 |
| `privacy_consent` | ✅ | `accepted` (harus `1`/`true`/`on`) |

Respons `201`:

```json
{
  "data": {
    "user": {
      "uuid": "...", "full_name": "Budi", "email": "budi@mail.com",
      "email_verified_at": null, "google_avatar_url": null,
      "privacy_consent_at": "2026-08-20T10:00:00Z", "is_active": true,
      "ai_bot": false, "user_messages_count": 0, "date_of_birth": null,
      "gender": null, "avatar_updated_at": null,
      "created_at": "...", "updated_at": "...", "deleted_at": null,
      "avatar_url": null
    },
    "token": "1|xxxxxxxxxxxxxxxx"
  },
  "meta": {}
}
```

### 3.2 Register Dokter — `POST /register-doctor` — Public

Throttle: 5/menit. Body `multipart/form-data` (ada upload file):

| Field | Wajib | Validasi |
|---|---|---|
| `full_name` | ✅ | string, max 255 |
| `email` | ✅ | email, unik |
| `password` | ✅ | min 8 |
| `specialization` | ✅ | string, max 255 |
| `str_number` | — | nullable, max 50 |
| `title` | — | nullable, max 100 |
| `sub_specialization` | — | nullable, max 255 |
| `experience_years` | — | integer 0–100 |
| `alma_mater` | — | nullable, max 255 |
| `practice_locations` | — | array max 20 item, tiap item string max 255 |
| `professional_organizations` | — | array max 20 item, tiap item string max 100 |
| `documents` | ✅ | 1+ file, mimes `jpeg,jpg,png,pdf`, max **5 MB/file** |
| `privacy_consent` | ✅ | `accepted` |

Akun dibuat dengan role `doctor` + pengajuan verifikasi status `pending`. Respons `201` seperti register user + `meta.message` = `"Registrasi dokter berhasil diajukan."` Dokter baru **belum muncul di list dokter** sampai diverifikasi admin.

### 3.3 Login — `POST /login` — Public

Throttle: 5/menit.

Body: `email` (string), `password` (string).

Sukses `200`: sama seperti register (`data.user` + `data.token`).

Gagal → `401` `{"message":"Kredensial tidak valid"}` (dipakai juga untuk akun nonaktif / akun Google-only tanpa password).

### 3.4 Login Google — `POST /auth/google` — Public

Throttle: 10/menit.

Body:

| Field | Wajib | Keterangan |
|---|---|---|
| `id_token` | ✅ | ID token Google (dari Google Sign-In, harus dari client ID terdaftar) |
| `privacy_consent` | hanya untuk user baru | `accepted` |

Alur: token diverifikasi → user baru dibuat (atau user lama dengan email sama di-link ke Google) → `200` dengan `data.user` + `data.token`.

Error:

- `401` `{"message":"Token Google tidak valid"}` — token tidak valid / email tidak diverifikasi Google / aud mismatch
- `401` `{"message":"Akun Google tidak dapat digunakan"}` — email sudah terhubung akun Google lain
- `422` `{"message":"Persetujuan privasi wajib untuk pengguna baru"}` — user baru tanpa consent

### 3.5 Logout — `POST /logout` — Auth

Mencabut token sesi ini. `200` `{"data":null,"meta":{"message":"Berhasil logout dari sesi ini"}}`.

### 3.6 Logout Semua — `POST /logout-all` — Auth

Mencabut semua token. `200` `{"data":null,"meta":{"message":"Berhasil logout dari semua perangkat"}}`.

---

## 4. Verifikasi Email (OTP)

Semua endpoint di bagian ini: Auth. OTP = 6 digit, berlaku **10 menit**, maksimal **5 percobaan salah** (setelah itu kode hangus).

### 4.1 Kirim OTP — `POST /email/verify/send`

Body: kosong.

- Sudah terverifikasi → `422` `{"message":"Email sudah terverifikasi"}`
- Sukses `200` `{"data":null,"meta":{"message":"Kode OTP verifikasi telah dikirim ke email Anda"}}`

### 4.2 Verifikasi — `POST /email/verify`

Body: `otp` (required, digits:6).

- Salah/kadaluwarsa → `422` `{"message":"Kode OTP tidak valid atau kedaluwarsa"}`
- Sukses `200` `{"data":null,"meta":{"message":"Email berhasil diverifikasi"}}`

> ⚠️ Verifikasi email **wajib** sebelum: scan, chat (sebagai user), berlangganan. Pesan 403-nya: `"Verifikasi email terlebih dahulu sebelum menggunakan fitur ini."`

---

## 5. Reset Password

### 5.1 Lupa Password — `POST /forgot-password` — Public

Throttle: 3 per 15 menit. Body: `email` (required, email).

Selalu `200` `{"data":null,"meta":{"message":"Jika email terdaftar, kode OTP telah dikirim"}}` (tidak membocorkan apakah email terdaftar).

### 5.2 Reset Password — `POST /reset-password` — Public

Throttle: 10/menit. Body:

| Field | Wajib |
|---|---|
| `email` | ✅ |
| `otp` | ✅ (6 digit) |
| `password` | ✅ min 8 + harus `confirmed` (kirim `password_confirmation`) |

Sukses `200` `{"data":null,"meta":{"message":"Password berhasil diperbarui"}}` — **semua sesi dicabut** (user harus login ulang). Error OTP: `422` seperti di atas.

---

## 6. Profil

### 6.1 Lihat Profil — `GET /profile` — Auth

Respons `200` — `data`:

```json
{
  "uuid": "...", "full_name": "Budi", "email": "budi@mail.com",
  "role": "user",
  "avatar_url": "https://...", "google_avatar_url": null,
  "date_of_birth": "1995-01-01", "gender": "laki_laki",
  "profile_completed": true, "email_verified": true
}
```

Role-specific keys:

- **user**: `subscription_status` (`"Pro"`|`"Free"`), `scan_count`, `user_messages_count`, `remaining_free_messages` (sisa chat gratis, min 0).
- **doctor**: `verification_status` (`pending|approved|rejected|needs_revision|unverified`), `product_count`, `recommendation_count`.
- **admin**: `pending_doctor_verifications` (jumlah verifikasi pending).

### 6.2 Update Profil — `PATCH /profile` — Auth

Body `multipart/form-data` (ada upload avatar). Semua opsional:

| Field | Validasi |
|---|---|
| `full_name` | string, max 255 |
| `password` | min 8 |
| `avatar` | image, max **2 MB** |
| `date_of_birth` | date, sebelum hari ini (`before:today`) |
| `gender` | `laki_laki` \| `perempuan` |

⚠️ **Avatar dibatasi 1x ganti per 24 jam** → `422` `{"message":"Kamu hanya dapat mengganti foto profil maksimal 1x dalam 24 jam"}`.

Respons `200`:

```json
{
  "data": {
    "uuid": "...", "full_name": "...", "avatar_url": "...",
    "google_avatar_url": null, "date_of_birth": "1995-01-01",
    "gender": "laki_laki", "profile_completed": true
  },
  "meta": { "message": "Profil berhasil diperbarui" }
}
```

### 6.3 Hapus Avatar — `DELETE /profile/avatar` — Auth

`200` `{"data":{"avatar_url":null,"google_avatar_url":null},"meta":{"message":"Foto profil berhasil dihapus"}}`

### 6.4 Hapus Akun — `DELETE /profile` — Auth

Soft delete + semua sesi dicabut. `200` `{"data":null,"meta":{"message":"Akun berhasil dihapus"}}`

### 6.5 Export Data (UU PDP) — `POST /profile/export` — Auth

Membuat file JSON data pribadi user (profil, subscription, riwayat scan, verifikasi dokter, jumlah pesan, jumlah device). File lama dihapus.

`200`:

```json
{
  "data": {
    "download_url": "https://be-skincek.test/api/v1/profile/exports/download?expires=...&signature=...",
    "expires_in_minutes": 30
  },
  "meta": { "message": "Data akun siap diunduh" }
}
```

URL valid **30 menit**, file dihapus otomatis setelah diunduh. Invalid signature → `403`.

---

## 7. Notifikasi

Semua Auth.

### 7.1 List — `GET /notifications?per_page=10`

Envelope **custom** (tanpa `links`):

```json
{
  "data": [
    {
      "id": "...",
      "type": "App\\Notifications\\AppNotification",
      "title": "Pesan baru dari Dokter",
      "body": "Halo, hasil scan Anda...",
      "data": { "title": "...", "body": "...", "conversation_id": "..." },
      "read_at": null,
      "created_at": "2026-08-20T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 5, "unread_count": 3 }
}
```

`unread_count` = total belum dibaca (bukan hanya halaman ini). Note: `data` = data mentah notifikasi.

### 7.2 Tandai Dibaca — `POST /notifications/{notification}/read`

`200` `{"data":{"notification":{...}}}` (object notifikasi yang sudah `read_at` terisi).

### 7.3 Tandai Semua Dibaca — `POST /notifications/read-all`

`200` `{"data":null,"meta":{"message":"Semua notifikasi telah dibaca"}}`

### 7.4 Hapus — `DELETE /notifications/{notification}`

`200` `{"data":null,"meta":{"message":"Notifikasi dihapus"}}`

> Notifikasi dibuat backend saat: pesan chat baru, balasan Aura Skin, hasil verifikasi dokter, pembayaran sukses/gagal. Bukan milik user → `404`.

---

## 8. Chat dengan Dokter

Semua Auth.

### 8.1 Buat / Ambil Percakapan — `POST /conversations`

Body: `doctor_id` (required, UUID user dokter).

- Dokter tidak valid → `422` `{"message":"Dokter tidak ditemukan"}`
- Dokter belum terverifikasi → `422` `{"message":"Dokter belum terverifikasi"}`
- **Idempoten**: percakapan yang sudah ada dipakai ulang (tidak duplikat).

`201`:

```json
{
  "data": {
    "uuid": "01a01f41-b23a-73fa-88c6-00ce9408fde3",
    "user": { "uuid": "...", "full_name": "Budi", "email": "...", "role": "user", "avatar_url": "...", "is_active": true, "gender": "laki_laki", "date_of_birth": "1995-01-01", "age": 31, "doctor_verification": null, "created_at": "..." },
    "doctor": { "uuid": "...", "full_name": "dr. Sari", "email": "...", "role": "doctor", "...": "..." },
    "message_count": 0,
    "last_message": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### 8.2 List Percakapan — `GET /conversations?per_page=10`

Percakapan di mana user terlibat (sebagai user ATAU dokter). Urut terbaru dulu. Item sama seperti 8.1 (`last_message` terisi).

### 8.3 List Pesan — `GET /conversations/{conversation}/messages?per_page=10`

Urut terbaru dulu (`latest`). Item:

```json
{
  "uuid": "...",
  "sender": { "uuid": "...", "full_name": "User", "role": "user" },
  "content": "Halo dok",
  "type": "text",
  "media_url": null,
  "created_at": "2026-08-20T13:00:08.000000Z"
}
```

`type`: `text` | `image` | `video`. Bukan peserta → `404`.

### 8.4 Kirim Pesan — `POST /conversations/{conversation}/messages`

Body `multipart/form-data`:

| Field | Wajib | Validasi |
|---|---|---|
| `content` | ✅ (jika tanpa media) | nullable, string, **max 5000** |
| `media` | — | file, mimes `jpeg,jpg,png,gif,webp,mp4,mov,avi`, max **10 MB** |

Pesan berisi kata tidak pantas → `422` `{"message":"Pesan mengandung kata yang tidak pantas"}`.

Kuota & syarat (dicek hanya saat pengirim = sisi `user`, bukan dokter):

- Email belum diverifikasi → `403` `{"message":"Verifikasi email terlebih dahulu sebelum menggunakan fitur ini."}`
- Penerima = Aura Skin: tanpa consent → `403` `{"message":"Setujui penggunaan AI terlebih dahulu sebelum mengobrol dengan Aura Skin."}`; kuota AI habis → `429` `{"message":"Kamu sudah mencapai 10 pesan Aura Skin gratis hari ini. Upgrade ke SkinCek Pro untuk chat tanpa batas."}`
- Kuota 3 pesan gratis habis (non-bot) → **`402`** `{"message":"Kamu sudah melewati 3 pesan gratis. Upgrade ke SkinCek Pro untuk melanjutkan chat."}`

`201` → `MessageResource` tunggal (`data.message`). Balasan dokter datang asinkron (polling atau realtime, lihat bagian 15).

### 8.5 Alur Chat di Frontend (rekomendasi)

1. `GET /doctors` → pilih dokter → `POST /conversations` (dapatkan UUID).
2. `GET /conversations/{uuid}/messages` → tampilkan riwayat.
3. Kirim → `POST .../messages` → tambahkan pesan lokal.
4. Terima pesan baru: subscribe Reverb channel `conversation.{uuid}` **atau** polling `GET .../messages` (untuk MVP tanpa websocket, polling 3–5 detik).

---

## 9. AI Chat — Aura Skin

Bot AI "Aura Skin" (role `doctor`, `is_ai_bot: true`, ter-pin di atas list dokter). Jawaban dikirim **asinkron** via queue (~detik), pakai Gemini API. Bahasa Indonesia.

Semua Auth.

### 9.1 Cek Status Consent — `GET /ai-chat/consent`

`200`:

```json
{
  "data": {
    "accepted": false,
    "version": "v1",
    "text": "Dengan menyetujui, kamu mengizinkan SkinCek membagikan isi pesan teks chat-mu ke penyedia kecerdasan buatan (Google Gemini) agar Aura Skin dapat menjawab pertanyaanmu. Pesan tidak digunakan untuk melatih model dan foto tidak pernah dibagikan. Kamu dapat mencabut persetujuan ini kapan saja dan tetap bisa menggunakan chat dokter.",
    "accepted_at": null
  },
  "meta": {}
}
```

### 9.2 Setujui / Cabut Consent — `POST /ai-chat/consent`

Body: `accepted` (required boolean).

- `true` → `200` `{"data":{"accepted":true},"meta":{"message":"Persetujuan penggunaan AI disimpan"}}`
- `false` → `200` `{"data":{"accepted":false},"meta":{"message":"Persetujuan penggunaan AI telah dicabut"}}` (semua riwayat consent dihapus)

### 9.3 Mulai Percakapan Aura Skin — `POST /ai-chat/conversations`

- Tanpa consent → `403` `{"message":"Setujui penggunaan AI terlebih dahulu sebelum mengobrol dengan Aura Skin."}`
- Sukses `201` → `ConversationResource` (sama seperti 8.1, `doctor.full_name` = "Aura Skin").

### 9.4 Kirim Pesan ke Aura Skin

Pakai endpoint biasa `POST /conversations/{conversation}/messages` (lihat 8.4) — kuota AI otomatis dikenakan di backend.

### 9.5 Hapus Riwayat Chat Aura Skin — `DELETE /ai-chat/conversations/{conversation}`

Hanya untuk percakapan dengan bot. Percakapan non-bot → `422` `{"message":"Hanya riwayat chat Aura Skin yang dapat dihapus."}`. Sukses `200` `{"data":null,"meta":{"message":"Riwayat chat Aura Skin telah dihapus"}}`.

### 9.6 Keamanan AI

- Kata kunci berbahaya (dosis, resep, suntik, darurat, dll.) **tidak dikirim ke Gemini** — langsung dibalas eskalasi: `"Maaf, pertanyaan ini di luar cakupan Aura Skin (Aku hanya membahas edukasi skincare umum). Untuk kondisi yang kamu tanyakan, sebaiknya segera berkonsultasi dengan dokter kulit atau layanan kesehatan terdekat ya."`
- Layanan error → `"Maaf, layanan Aura Skin sedang tidak tersedia. Silakan coba lagi sebentar lagi, atau konsultasikan dengan dokter kulit untuk pertanyaan kamu."`
- Frontend tidak bisa membedakan sumber balasan — perlakukan semua balasan bot sama.

---

## 10. Scan Kulit (ML)

Semua Auth. Scan → image dikirim ke ML service (HuggingFace Space), hasil disimpan + email notifikasi (maks 1x/hari/user).

### 10.1 Scan Upload — `POST /scans`

### 10.2 Scan Livecam — `POST /scans/livecam`

Keduanya identik kecuali `scan_mode` (`upload` / `livecam`) dan media collection. Throttle tambahan: 30/menit.

Body `multipart/form-data`: `image` (required, mimes `jpeg,jpg,png,webp`, max **5 MB**).

Pemeriksaan berurutan (sebelum ML dipanggil):

1. Email belum terverifikasi → `403` `{"message":"Verifikasi email terlebih dahulu sebelum menggunakan fitur ini."}`
2. Profil belum lengkap (hanya jika belum pernah scan) → `422` `{"message":"Lengkapi profil kamu terlebih dahulu (tanggal lahir dan jenis kelamin) sebelum melakukan prediksi pertama."}`
3. Kuota 3 scan gratis hari ini habis (Pro skip) → `429` `{"message":"Kamu sudah mencapai 3 scan gratis hari ini. Upgrade ke SkinCek Pro untuk scan tanpa batas."}`

Sukses `201`:

```json
{
  "data": {
    "uuid": "...",
    "scan_mode": "upload",
    "predicted_class": "acne",
    "confidence": 0.8731,
    "probabilities": { "acne": 0.87, "normal": 0.1, "wrinkle": 0.03 },
    "severity_score": 42,
    "severity_level": "medium",
    "model_used": "skin-model-v1",
    "image_url": "https://...",
    "disclaimer": "Hasil scan hanya sebagai referensi awal dan bukan diagnosis medis. Konsultasikan dengan dokter kulit untuk penanganan yang tepat.",
    "skin_concern": {
      "name": "Jerawat Meradang",
      "description": "Jerawat yang tampak merah, bengkak, dan terasa nyeri..."
    },
    "other_concerns": [
      { "ml_label": "dark spots", "name": "Flek Hitam", "description": "...", "confidence": 0.12 }
    ],
    "treatment_recommendations": [
      {
        "uuid": "...",
        "title": "Konsultasikan Bila Peradangan Memburuk",
        "recommendation_text": "Jerawat meradang yang besar, nyeri, atau terus bertambah...",
        "priority_level": "high"
      },
      {
        "uuid": "...",
        "title": "Rutinitas Pembersihan Ganda yang Lembut",
        "recommendation_text": "Bersihkan wajah dua kali sehari dengan pembersih...",
        "priority_level": "medium"
      }
    ],
    "skincare_recommendations": [
      {
        "uuid": "...",
        "name": "Cetaphil Gentle Skin Cleanser",
        "category": "cleanser",
        "gender": "unisex",
        "key_ingredients": "Water, Cetyl Alcohol, Propylene Glycol...",
        "usage_instruction": "Basahi wajah dengan air suhu ruang...",
        "warning": "Hindari kontak langsung dengan mata...",
        "skin_type": "Sensitif",
        "doctor": "dr. Sari Dewi"
      }
    ],
    "notice": null,
    "created_at": "2026-08-20T10:00:00Z"
  }
}
```

- `severity_score`: integer 0–100. `severity_level`: `low`|`medium`|`high`.
- **`treatment_recommendations` = REKOMENDASI PERAWATAN (TIPS)** — dari tabel `skin_recommendations`, diurut `high` → `medium` → `low` priority. Tips perilaku/habit, BUKAN produk.
- **`skincare_recommendations` = REKOMENDASI SKINCARE (PRODUK)** — dari tabel `skincare_products` (dibuat dokter), sesuai concern hasil scan. Bukan tips.
- Keduanya kosong `[]` bila `predicted_class` tidak cocok dengan `ml_label` concern manapun.
- `notice` terisi jika `confidence < 0.50`: `"Hasil prediksi ini memiliki tingkat keyakinan rendah. Sebaiknya lakukan scan ulang dengan pencahayaan yang lebih baik atau konsultasikan langsung dengan dokter kulit."`
- ML service sibuk → `502` `{"message":"Layanan prediksi sedang sibuk, coba lagi nanti"}`.

### 10.3 Riwayat Scan — `GET /scans?per_page=10&filter[scan_mode]=upload&filter[predicted_class]=acne&sort=-created_at`

Filter: `filter[scan_mode]` (`upload|livecam`), `filter[predicted_class]`. Sort hanya `created_at` (default `-created_at`). Item sama seperti 10.1.

### 10.4 Detail Scan — `GET /scans/{predictionHistory}`

Milik user lain → `404`. `200` → resource tunggal.

### 10.5 Feedback — `POST /scans/{predictionHistory}/feedback`

Body: `is_accurate` (required boolean). Idempoten (feedback terakhir menimpa).

`200` `{"data":{"is_accurate":true},"meta":{"message":"Terima kasih atas masukan Anda"}}`

---

## 11. Dokter & Rating

### 11.1 List Dokter — `GET /doctors?per_page=10` — Auth

Hanya dokter **terverifikasi** + **Aura Skin** di-pin paling atas (`is_ai_bot: true`). Item:

```json
{
  "uuid": "...", "full_name": "Aura Skin", "title": null,
  "specialization": "Kecerdasan Buatan", "avatar": "https://...",
  "rating_avg": null, "rating_count": 0, "is_ai_bot": true
}
```

### 11.2 Detail Dokter — `GET /doctors/{doctor}` — Auth

Item list + field tambahan: `sub_specialization`, `str_number`, `experience_years`, `alma_mater`, `practice_locations` (array), `professional_organizations` (array). Belum terverifikasi → `404`.

### 11.3 Kirim Rating — `POST /doctors/{doctor}/ratings` — Auth

Hanya user yang **pernah chat** dengan dokter itu → `422` `{"message":"Kamu hanya dapat menilai dokter yang pernah kamu ajak chat"}`.

Body: `rating` (required, integer 1–5), `review` (nullable, max 1000).

Satu rating per user-dokter (re-rating menimpa). `201` `{"data":{"rating":5,"review":"..."},"meta":{"message":"Rating dokter berhasil disimpan"}}`.

### 11.4 List Rating — `GET /doctors/{doctor}/ratings?per_page=10` — Auth

Envelope custom:

```json
{
  "data": [
    { "rating": 5, "review": "Bagus", "user": { "uuid": "...", "full_name": "Budi" }, "created_at": "..." }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1 }
}
```

---

## 12. Langganan SkinCek Pro (Midtrans)

### 12.1 Alur Pembayaran (di frontend)

1. `GET /subscriptions` → tampilkan status.
2. `POST /subscriptions/checkout` → dapatkan `snap_token`.
3. Buka Snap Midtrans (`https://app.sandbox.midtrans.com/snap/v2/vtweb/{snap_token}` atau pakai library Snap JS/mobile) → user bayar.
4. Midtrans kirim webhook ke backend → subscription jadi `active` (otomatis, tanpa aksi frontend).
5. Frontend polling `GET /subscriptions` sampai `status = "active"` (biasanya < 1 menit).

### 12.2 Detail

Harga: **Rp15.000 / bulan** (`pro_monthly`), durasi 30 hari, renewal otomatis memperpanjang `ends_at` dari tanggal berakhir sebelumnya (bukan dari tanggal bayar).

### 12.3 Checkout — `POST /subscriptions/checkout` — Auth

- Email belum diverifikasi → `403` `{"message":"Verifikasi email terlebih dahulu sebelum berlangganan."}`
- Sudah Pro → `422` `{"message":"Kamu sudah berlangganan SkinCek Pro"}`
- Error Midtrans → `502` `{"message":"Gagal membuat transaksi pembayaran, coba lagi nanti"}`

`201`:

```json
{
  "data": {
    "snap_token": "xxx-yyy-zzz",
    "redirect_url": "https://app.sandbox.midtrans.com/snap/v2/vtweb/xxx-yyy-zzz",
    "subscription": {
      "uuid": "...", "plan_code": "pro_monthly", "period": "monthly",
      "status": "pending", "amount": 15000, "currency": "IDR",
      "payment_method": null, "midtrans_order_id": "SKINCEK-01A0...",
      "starts_at": null, "ends_at": null, "paid_at": null, "created_at": "..."
    }
  },
  "meta": { "message": "Transaksi pembayaran berhasil dibuat" }
}
```

### 12.4 List — `GET /subscriptions?per_page=10` — Auth

Collection `SubscriptionResource` (shape sama seperti di atas), urut terbaru.

### 12.5 Receipt (Bukti Bayar) — `GET /subscriptions/{subscription}/receipt` — Auth

Hanya pemilik & `status = active` (selain itu `404`). `200` → `SubscriptionResource`.

### 12.6 Batalkan — `POST /subscriptions/{subscription}/cancel` — Auth

Hanya jika `status = active` (lainnya `422` `{"message":"Langganan tidak dapat dibatalkan"}`). `200` → resource + `meta.message` `"Langganan berhasil dibatalkan"`. Pro langsung nonaktif (tidak menunggu `ends_at`).

### 12.7 Status Subscription

`pending` → `active` → (`expired` | `cancelled`). `expired` dipicu command harian (05:00) atau webhook Midtrans (`expire`). `hasActiveSubscription` = `active` DAN (`period = lifetime` OR `ends_at` null OR `ends_at >= sekarang`).

---

## 13. Katalog (Public)

Endpoint ini **tanpa autentikasi**.

### 13.1 Skincare Products

- `GET /skincare-products?per_page=10&concern={uuid}&skin_type={uuid}&gender=laki_laki` — hanya `is_active`. Filter: `concern` (uuid concern), `skin_type` (uuid), `gender` (`laki_laki|perempuan|unisex`).
- `GET /skincare-products/{skincareProduct}` — detail.

Item:

```json
{
  "uuid": "...", "name": "Sunscreen SPF 50", "category": "Sunscreen",
  "gender": "unisex", "key_ingredients": "Zinc Oxide",
  "usage_instruction": "Oles 15 menit sebelum beraktivitas",
  "warning": null, "is_active": true,
  "concern": { "uuid": "...", "name": "Jerawat", "ml_label": "acne", "description": "...", "default_severity_score": 50, "is_active": true },
  "skin_type": { "uuid": "...", "name": "Berminyak", "description": "...", "is_active": true },
  "doctor": { "uuid": "...", "full_name": "dr. Sari", "email": "...", "role": "doctor", "avatar_url": "...", "is_active": true, "gender": null, "date_of_birth": null, "age": null, "doctor_verification": null, "created_at": "..." }
}
```

### 13.2 Skin Recommendations

- `GET /skin-recommendations?per_page=10&ml_label=acne&concern_id={uuid}` — `ml_label` = label ML (diterjemahkan ke concern), `concern_id` = uuid concern. Urut: `high` → `medium` → `low` priority, lalu terbaru.
- `GET /skin-recommendations/{skinRecommendation}` — detail.

Item: `uuid, title, recommendation_text, priority_level (low|medium|high), is_active, concern {...}, product {...}, doctor {...}, created_at`.

### 13.3 Skin Concerns — `GET /skin-concerns`

**Tanpa pagination** — array langsung di `data`, urut abjad `name`. Item: `{uuid, name, ml_label, description, default_severity_score, is_active}`.

**Data seeded (8 concern):**

| Nama | `ml_label` | `default_severity_score` |
|---|---|---|
| Jerawat Meradang | `inflammatory acne` | 85 |
| Komedo Hitam | `non inflammatory acne black heads` | 50 |
| Komedo Putih | `non inflammatory acne white heads` | 50 |
| Kemerahan | `Redness` | 60 |
| Flek Hitam | `dark spots` | 40 |
| Pigmentasi | `pigmentation` | 40 |
| Pori-pori Besar | `pores` | 30 |
| Kerutan | `wrinkles` | 30 |

> `ml_label` dipakai sebagai jembatan ke `predicted_class` dari hasil scan — frontend bisa mapping `predicted_class` → nama concern via endpoint ini. Nilai `ml_label` harus cocok persis (case-sensitive) dengan output ML model.

### 13.4 Skin Types — `GET /skin-types`

Tanpa pagination, urut abjad. Item: `{uuid, name, description, is_active}`.

---

## 14. Verifikasi Dokter

Semua Auth.

### 14.1 Lihat Status Sendiri — `GET /doctor-verifications` — role: doctor

Belum ada pengajuan → `404` `{"message":"Belum ada pengajuan verifikasi"}`.

`200`:

```json
{
  "data": {
    "uuid": "...", "str_number": "12345", "title": "dr.",
    "specialization": "Dermatologi", "sub_specialization": null,
    "experience_years": 5, "alma_mater": "UI",
    "practice_locations": ["Jakarta"], "professional_organizations": ["PERDOSKI"],
    "verification_status": "pending", "rejection_reason": null, "revision_note": null,
    "doctor": { "...user resource..." },
    "documents": [ { "uuid": "...", "url": "https://...", "file_name": "str.pdf" } ],
    "reviewed_at": null, "created_at": "..."
  },
  "meta": {}
}
```

### 14.2 Ajukan — `POST /doctor-verifications` — role: doctor

Sudah ada pengajuan non-rejected → `422` `{"message":"Pengajuan verifikasi sudah ada dan masih diproses"}`.

Body `multipart/form-data` (sama dengan field register-doctor, lihat 3.2 — `documents` max 5 MB/file). `201` → resource.

### 14.3 Resubmit (perbaiki) — `POST /doctor-verifications/{doctorVerification}/resubmit` — role: doctor

Hanya jika status `needs_revision` (lainnya `422` `{"message":"Verifikasi tidak dapat diajukan ulang"}`). Semua field opsional (partial). Status kembali `pending`. `200` → resource.

### Status & Notifikasi

`pending → approved | rejected | needs_revision`. Saat direview admin, dokter dapat notifikasi + event realtime (bagian 15).

---

## 15. Realtime (WebSocket — Reverb)

WebSocket untuk notifikasi instan (pengganti polling).

- Server: Reverb (Laravel). URL lokal: `ws://localhost:8080` (app key = `REVERB_APP_KEY`). Di produksi: domain yang disediakan tim backend.
- Transport: Laravel Echo / Reverb protocol (perlu library `laravel-echo` + `pusher-js` dengan `wsHost`/`wsPort`; atau di Flutter: package Reverb-compatible pusher client).
- Channel bersifat **private** — butuh `auth` endpoint. Laravel Echo memanggil `POST /broadcasting/auth` secara otomatis (pakai token Bearer).

### 15.1 Channel

| Channel | Siapa yang boleh subscribe |
|---|---|
| `private-App.Models.User.{userId}` | user itu sendiri (id integer internal — tidak dipakai frontend, abaikan) |
| `private-user.{uuid}` | user dengan UUID tersebut (dipakai untuk notifikasi verifikasi dokter) |
| `private-conversation.{uuid}` | peserta percakapan (user ATAU dokter) |

### 15.2 Event

**`chat_message_received`** (channel `private-conversation.{uuid}`) — pesan chat baru (termasuk balasan Aura Skin):

```json
{
  "type": "chat_message_received",
  "category": "transactional",
  "conversation_uuid": "01a0...",
  "message": {
    "uuid": "...",
    "sender": { "uuid": "...", "full_name": "dr. Sari", "role": "doctor" },
    "content": "Halo, hasil scan Anda normal",
    "type": "text",
    "media_url": null,
    "created_at": "2026-08-20T13:00:08.000000Z"
  }
}
```

**`doctor_verification_reviewed`** (channel `private-user.{uuid}` dokter):

```json
{
  "type": "doctor_verification_reviewed",
  "category": "transactional",
  "verification_status": "approved",
  "rejection_reason": null,
  "revision_note": null,
  "reviewed_at": "2026-08-20T14:00:00.000000Z"
}
```

> Fallback (tanpa websocket): polling `GET /notifications` (untuk badge) dan `GET /conversations/{uuid}/messages` (untuk chat).

---

## 16. Device Token (Push Notification — FCM)

Semua Auth. `fcm` di sisi backend saat ini berstatus `enabled=false` (default) — **push FCM belum aktif**, notifikasi tersimpan di database (di-poll via `GET /notifications`). Frontend tetap wajib mengirim token agar siap saat diaktifkan.

### 16.1 Daftarkan — `POST /device-tokens`

Body: `fcm_token` (required, max 4096), `platform` (required: `ios`|`android`|`web`).

`201` `{"data":{"uuid":"...","fcm_token":"...","platform":"android","created_at":"..."},"meta":{}}`

### 16.2 Hapus — `DELETE /device-tokens/{deviceToken}`

`200` `{"data":null,"meta":{"message":"Perangkat berhasil dihapus"}}`

> Kirim token saat login/logout di tiap device. Token sama yang di-register ulang akan update platform-nya.

---

## 17. Aktivitas Login (Keamanan)

Semua Auth.

### 17.1 List — `GET /login-activity`

**Tanpa pagination** — array langsung:

```json
{
  "data": [
    {
      "uuid": "...",
      "device": "Chrome di Android",
      "ip_address": "114.122.10.5",
      "location": { "city": "Jakarta", "region": "DKI Jakarta", "country": "Indonesia" },
      "is_current": true,
      "last_used_at": "2026-08-20T10:00:00Z",
      "created_at": "2026-08-20T09:55:00Z"
    }
  ],
  "meta": {}
}
```

`location` bisa `null` (IP privat / gagal resolve). `is_current` = sesi yang sedang dipakai.

### 17.2 Cabut Sesi — `DELETE /login-activity/{personalAccessToken}`

Sesi orang lain → `404`. `200` `{"data":null,"meta":{"message":"Sesi berhasil dicabut"}}`

---

## 18. Emergency Hotlines — `GET /emergency` — Public

```json
{
  "data": [
    { "id": "emergency", "name": "Ambulans / Gawat Darurat", "phone": "118", "description": "Layanan kegawatdaruratan medis (hotline umum)." },
    { "id": "112", "name": "Nomor Darurat Nasional", "phone": "112", "description": "..." },
    { "id": "suicide_prevention", "name": "Layanan Bantuan Kesehatan Jiwa (Kemenkes)", "phone": "119 ext 8", "description": "..." },
    { "id": "poison", "name": "Pusat Informasi Keracunan", "phone": "021-4250767", "description": "..." }
  ],
  "meta": {}
}
```

Tampilkan prominent di menu bantuan/emergency.

---

## 19. Admin (role: admin)

Semua Auth + role admin (selain admin → `403`).

### 19.1 List User — `GET /admin/users?per_page=10&role=doctor`

Filter `role`: `admin`|`doctor`|`user`. Item = `UserResource` (lihat 8.1).

### 19.2 Detail User — `GET /admin/users/{user}`

`UserResource` + `doctor_verification`.

### 19.3 Ubah Role — `PATCH /admin/users/{user}/role`

Body: `role` (required, `admin`|`doctor`|`user`).

`200` `{"data":{...user...},"meta":{"message":"Role berhasil diubah menjadi doctor"}}`

### 19.4 Activity Log — `GET /admin/activity-log?per_page=10&log_name=role_management&causer_id={uuid}`

Item: `{id, log_name, description, event, properties, causer: {uuid, full_name, email}|null, created_at}`.

### 19.5 Verifikasi Dokter (admin)

- `GET /admin/verifications?per_page=10&status=pending` — list semua pengajuan.
- `GET /admin/verifications/{doctorVerification}` — detail (termasuk `documents`).
- `PATCH /doctor-verifications/{doctorVerification}/review` — review:

| Field | Wajib | Validasi |
|---|---|---|
| `status` | ✅ | `approved`\|`rejected`\|`needs_revision` |
| `rejection_reason` | jika `rejected` | string |
| `revision_note` | jika `needs_revision` | string |

Sudah diproses → `422` `{"message":"Verifikasi sudah diproses"}`. Dokter menerima notifikasi + realtime event.

### 19.6 Kelola Katalog (admin)

- `POST /skin-concerns` / `PATCH /skin-concerns/{skinConcern}` / `DELETE /skin-concerns/{skinConcern}` — body: `name` (unique), `ml_label` (unique), `description`, `default_severity_score` (0–100), `is_active`.
- `POST /skin-types` / `PATCH /skin-types/{skinType}` / `DELETE /skin-types/{skinType}` — body: `name` (unique), `description`, `is_active`.
- `GET /admin/skincare-products` — semua produk (termasuk nonaktif).

---

## 20. Kelola Katalog oleh Dokter (role: doctor)

Semua butuh dokter **terverifikasi** untuk create → `403` `{"message":"Dokter harus terverifikasi terlebih dahulu"}`.

### 20.1 Skincare Products

- `GET /doctor/products` — produk milik sendiri.
- `POST /skincare-products` — body: `concern_id` (required, exists), `skin_type_id` (nullable, exists), `name` (required, max 255), `category` (required, max 255), `gender` (`laki_laki|perempuan|unisex`), `key_ingredients`, `usage_instruction` (required), `warning`, `is_active` (boolean).
- `PATCH /skincare-products/{skincareProduct}` — update (pemilik/admin saja, selain itu `403`), field opsional (`sometimes`) + `is_active`.
- `DELETE /skincare-products/{skincareProduct}` — `200` `{"data":null,"meta":{"message":"Produk berhasil dihapus"}}`.

### 20.2 Skin Recommendations

- `GET /doctor/recommendations` — milik sendiri.
- `POST /skin-recommendations` — body: `concern_id` (required, exists), `product_id` (nullable, exists), `title` (required, max 255), `recommendation_text` (required), `priority_level` (`low|medium|high`), `is_active`.
- `PATCH /skin-recommendations/{skinRecommendation}` — update (pemilik/admin).
- `DELETE /skin-recommendations/{skinRecommendation}` — `200` `{"data":null,"meta":{"message":"Rekomendasi berhasil dihapus"}}`.

---

## 21. Webhook Midtrans (informasi untuk backend ops; jangan dipanggil frontend)

`POST /webhooks/midtrans` — public, signature `sha512(order_id.status_code.gross_amount.server_key)` di header `X-Signature-Key`. Midtrans memanggil ini saat status pembayaran berubah. Frontend cukup polling `GET /subscriptions`.

---

## 22. Ringkasan Status Code & Pesan Error Umum

| Status | Pesan | Kapan |
|---|---|---|
| 400 | (variatif) | request tidak valid |
| 401 | `Unauthenticated` | token tidak ada/rusak |
| 401 | `Kredensial tidak valid` | login gagal |
| 402 | `Kamu sudah melewati 3 pesan gratis. Upgrade ke SkinCek Pro untuk melanjutkan chat.` | kuota chat gratis habis |
| 403 | `Verifikasi email terlebih dahulu sebelum menggunakan fitur ini.` | email belum diverifikasi (scan/chat/subscription) |
| 403 | `Setujui penggunaan AI terlebih dahulu sebelum mengobrol dengan Aura Skin.` | chat AI tanpa consent |
| 403 | `Invalid signature` | webhook Midtrans signature salah |
| 404 | (variatif / `No query results...`) | resource tidak ada / bukan milik user |
| 422 | `The given data was invalid.` (+`errors`) | validasi gagal |
| 429 | `Too Many Attempts.` | rate limit / kuota scan 3/hari / kuota AI 10/hari |
| 502 | `Layanan prediksi sedang sibuk, coba lagi nanti` | ML service error |

---

## 23. Cheat Sheet Field & Enum

| Enum | Nilai |
|---|---|
| `gender` | `laki_laki` \| `perempuan` |
| `scan_mode` | `upload` \| `livecam` |
| `severity_level` | `low` \| `medium` \| `high` |
| `priority_level` | `low` \| `medium` \| `high` |
| `product gender` | `laki_laki` \| `perempuan` \| `unisex` |
| `subscription status` | `pending` \| `active` \| `expired` \| `cancelled` |
| `subscription period` | `monthly` (historikal: `lifetime`) |
| `verification status` | `pending` \| `approved` \| `rejected` \| `needs_revision` |
| `device platform` | `ios` \| `android` \| `web` |
| `message type` | `text` \| `image` \| `video` |
| `role` | `admin` \| `doctor` \| `user` |
| plan code | `pro_monthly` (Rp15.000/bulan) |

---

## 24. Tools Pendukung

- **Postman collection** lengkap (19 request termasuk alur AI chat & verifikasi email): `docs/postman/SkinCek-Ai-Chat-Test.postman_collection.json`. Collection utama API lama bisa diminta ke tim backend.
- Environment Postman: `base_url = http://be-skincek.test/api/v1`.
- WebSocket test: Reverb dashboard `http://localhost:8080/apps` (lokal).

---

## 25. Lampiran — Alur Kunci di Frontend

### Alur Onboarding User

1. `POST /register` → simpan token → `POST /email/verify/send` → tampilkan input OTP → `POST /email/verify`.
2. `GET /profile` → jika `profile_completed=false` → form tanggal lahir + gender → `PATCH /profile`.

### Alur Scan

1. Cek `GET /profile` → `subscription_status` & `email_verified`.
2. `POST /scans` (multipart, field `image`) → tampilkan loading → hasil `201`.
3. Dari `predicted_class` → cari nama concern via `GET /skin-concerns` (bandingkan `ml_label`).
4. Tampilkan rekomendasi: `GET /skin-recommendations?ml_label={predicted_class}`.
5. Tombol "Tanya Dokter": `POST /conversations` dengan dokter dari `GET /doctors`.

### Alur Chat + AI

1. Tab dokter: `GET /doctors` (Aura Skin otomatis teratas — bisa diberi badge "AI" via `is_ai_bot`).
2. Chat dokter biasa: `POST /conversations` (`doctor_id`).
3. Chat Aura Skin: `GET /ai-chat/consent` → jika belum `accepted`, tampilkan modal teks consent → `POST /ai-chat/consent` → `POST /ai-chat/conversations`.
4. Kirim & terima pesan seperti bagian 8.5; tampilkan error 429 (kuota AI) dengan CTA upgrade.

### Alur Upgrade Pro

1. `POST /subscriptions/checkout` → `snap_token` → buka Snap.
2. Setelah Snap selesai, polling `GET /subscriptions` hingga `status=active` (timeout ~60s).
3. Refresh `GET /profile` → `subscription_status = "Pro"`.

### Alur Embed Scan ke Chat

1. Dari hasil scan (`POST /scans`) → dapatkan `uuid` scan.
2. Buka percakapan dokter/AI → `POST /conversations/{conversation_id}/messages` dengan body `{"prediction_history_id": "<scan_uuid>"}`.
3. Frontend render card: `type = "scan_result"`, `prediction_history` object berisi `predicted_class`, `confidence`, `severity_level`, `scan_mode`, `created_at`.
4. Bisa juga embed + custom text sekaligus: `"prediction_history_id": "...", "content": "Dok, tolong analisis..."`.

---

## 26. Data Seeded (`php artisan migrate:fresh --seed`)

### Akun

| Email | Password | Role | Catatan |
|---|---|---|---|
| `admin@skincek.com` | `password123` | admin | |
| `doctor@skincek.com` | `password123` | doctor | Belum verifikasi |
| `user@skincek.com` | `password123` | user | Belum email verified |
| `adamxraga@gmail.com` | `developer123` | user | Developer account |
| `aura@skincek.com` | (random) | doctor | AI bot, terverifikasi |

### Skin Concerns (8)

| Nama | `ml_label` | Severity |
|---|---|---|
| Jerawat Meradang | `inflammatory acne` | 85 |
| Komedo Hitam | `non inflammatory acne black heads` | 50 |
| Komedo Putih | `non inflammatory acne white heads` | 50 |
| Kemerahan | `Redness` | 60 |
| Flek Hitam | `dark spots` | 40 |
| Pigmentasi | `pigmentation` | 40 |
| Pori-pori Besar | `pores` | 30 |
| Kerutan | `wrinkles` | 30 |

### Roles & Permissions

- **admin**: semua permission
- **doctor**: `submit doctor verification`, `manage products`, `manage recommendations`, `reply conversations`
- **user**: `scan skin`, `create conversations`, `send messages`, `checkout subscription`, `register device token`

---

_Generator: dokumentasi diambil langsung dari kode (routes, controllers, resources, config). Jika ada perbedaan dengan perilaku aktual, laporkan ke tim backend._