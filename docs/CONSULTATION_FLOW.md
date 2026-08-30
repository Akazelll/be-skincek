# Alur Konsultasi — Panduan Frontend (List Dokter → Profil → Konfirmasi → Chat)

> Dokumen ini menjelaskan alur lengkap konsultasi user dengan dokter:
> **Lihat daftar dokter → "Stalking" profil dokter → Konfirmasi → Mulai chat.**
>
> Termasuk alur khusus **Aura Skin** (AI Bot) yang harus selalu dipin paling atas.

---

## Gambaran Alur

```
┌─────────────────────────────────────────────────────────────┐
│ HALAMAN KONSULTASI (User)                                    │
│                                                              │
│  GET /api/v1/doctors                                         │
│  ┌────────────────────────────────────┐                      │
│  │ 📌 Aura Skin          [AI] ⭐ 4.9   │ ← PINNED TOP         │
│ ├────────────────────────────────────┤                      │
│  │ dr. Sari Dewi, Sp.D    ⭐ 4.8 (12) │                      │
│  │ dr. Budi, Sp.DV        ⭐ 4.5 (8)  │                      │
│  │ dr. Rina, Sp.D         ⭐ 4.7 (5)  │                      │
│  └────────────────────────────────────┘                      │
│         │ klik card                                        │
│         ▼                                                   │
│  HALAMAN PROFIL DOKTER ("Stalking Mode")                    │
│  GET /doctors/{uuid} + GET /doctors/{uuid}/ratings          │
│  ┌────────────────────────────────────┐                      │
│  │ [Avatar]  dr. Sari Dewi, Sp.D      │                      │
│  │ Spesialisasi: Dermatologi Klinik    │                      │
│  │ Pengalaman: 8 tahun                 │                      │
│  │ STR: STR-123456                     │                      │
│  │ Alumni: Universitas Indonesia       │                      │
│  │ Lokasi Praktik: RS Pondok Indah...  │                      │
│  │ ⭐ 4.8 dari 12 review               │                      │
│  │ [Daftar Review...]                  │                      │
│  │                                      │                      │
│  │  [ Button "Konsultasi Sekarang" ]    │                      │
│  └────────────────────────────────────┘                      │
│         │ klik button                                        │
│         ▼                                                   │
│  DIALOG KONFIRMASI                                           │
│  ┌────────────────────────────────────┐                      │
│  │ Lakukan konsultasi dengan           │                      │
│  │ dr. Sari Dewi, Sp.D ?               │                      │
│  │                                      │                      │
│  │   [ Batal ]      [ Ya, Konsultasi ] │                      │
│  └────────────────────────────────────┘                      │
│         │ "Ya, Konsultasi"                                  │
│         ▼                                                   │
│  POST /api/v1/conversations {doctor_id}                      │
│         ▼                                                   │
│  HALAMAN CHAT                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 1. Halaman Daftar Dokter

### GET /api/v1/doctors

Mengambil semua dokter **approved**. **Auth wajib** (Bearer token).

**Query params:**
- `page`, `per_page` — pagination (default per_page = 5)
- `search` (jika diimplementasi di backend) — search by nama

**Response:**
```json
{
  "data": [
    {
      "uuid": "aura-skin-uuid",
      "full_name": "Aura Skin",
      "title": null,
      "specialization": "Asisten Kecerdasan Buatan SkinCek",
      "avatar": "https://...",
      "rating_avg": 4.9,
      "rating_count": 25,
      "is_ai_bot": true
    },
    {
      "uuid": "doctor-uuid",
      "full_name": "dr. Sari Dewi",
      "title": "dr. Sp.D",
      "specialization": "Dermatologi Klinik",
      "avatar": "https://...",
      "rating_avg": 4.8,
      "rating_count": 12,
      "is_ai_bot": false
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 5, "total": 2 }
}
```

### Aturan Penting: Aura Skin Selalu di Atas

**Backend sudah menjamin ini:** `orderByRaw('ai_bot DESC')` di DoctorController.php:87 — Aura Skin (`is_ai_bot: true`) selalu muncul pertama di halaman 1, item pertama.

**Tetapi frontend WAJIB juga handle:**
- Card Aura Skin pakai desain berbeda (badge "AI", gradient border, icon sparkle)
- Label: "Asisten AI SkinCek" bukan "Dokter"
- Klik Aura Skin → **alur berbeda** (lihat bagian 4)

### UI Card Dokter (Suggestion)

```
┌──────────────────────────────────────┐
│ [Avatar]  dr. Sari Dewi, Sp.D        │
│           Dermatologi Klinik         │
│           ⭐ 4.8 (12 review)         │
└──────────────────────────────────────┘
```

- `rating_avg`: format 1 desimal (4.80 → 4.8)
- `rating_count`: "(12 review)"
- `avatar`: langsung URL (bukan signed, tidak expired)
- Jika `rating_count` = 0 → "Belum ada review" (jangan tampil ⭐ 0)

---

## 2. Halaman Profil Dokter ("Stalking Mode")

### GET /api/v1/doctors/{uuid}

Full detail dokter untuk halaman profil publik. Hanya dokter **approved** (selain itu 404).

**Response:**
```json
{
  "data": {
    "uuid": "doctor-uuid",
    "full_name": "dr. Sari Dewi",
    "title": "dr. Sp.D",
    "specialization": "Dermatologi Klinik",
    "sub_specialization": "Aesthetics & Laser",
    "str_number": "STR-123456",
    "experience_years": 8,
    "alma_mater": "Universitas Indonesia",
    "practice_locations": ["RS Pondok Indah, Jakarta", "Klinik Kulit Sehat, Bekasi"],
    "professional_organizations": ["PERDOSKI"],
    "avatar": "https://...",
    "rating_avg": 4.8,
    "rating_count": 12,
    "is_ai_bot": false
  }
}
```

**Layout profil dokter (suggestion):**

```
┌──────────────────────────────────────────┐
│ [Avatar Besar]                           │
│ dr. Sari Dewi, Sp.D                      │
│ Dermatologi Klinik — Aesthetics & Laser  │
│                                          │
│ ── Tentang Dokter ──                     │
│ Pengalaman    │ 8 tahun                  │
│ STR           │ STR-123456               │
│ Alma Mater    │ Universitas Indonesia    │
│ Organisasi    │ PERDOSKI                │
│                                          │
│ ── Lokasi Praktik ──                     │
│ • RS Pondok Indah, Jakarta               │
│ • Klinik Kulit Sehat, Bekasi              │
│                                          │
│ ── Review (12) ──  ⭐ 4.8                │
│ [daftar review, lihat bagian 2.1]         │
│                                          │
│ [ Button: Konsultasi Sekarang ]          │
└──────────────────────────────────────────┘
```

**Catatan privasi:** Dokumen verifikasi (file STR/pdf/gambar upload) **TIDAK** ada di profil publik — hanya field teks. Ini by design.

### 2.1 Review & Rating Dokter

**GET /api/v1/doctors/{uuid}/ratings**

**Response:**
```json
{
  "data": [
    {
      "rating": 5,
      "review": "Dokternya sangat sabar menjawab pertanyaan saya...",
      "user": { "uuid": "user-uuid", "full_name": "Budi S." },
      "created_at": "2026-08-28T10:00:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 5, "total": 12 }
}
```

- Review hanya bisa ditulis oleh user yang **pernah chat** dengan dokter itu
- 1 user = 1 review per dokter (updateOrCreate — kirim lagi = update)
- User belum pernah chat → POST rating ditolak 422: "Kamu hanya dapat menilai dokter yang pernah kamu ajak chat"

**POST /api/v1/doctors/{uuid}/ratings** (setelah konsultasi selesai):
```json
{ "rating": 5, "review": "Penjelasannya sangat jelas" }
```
Validasi: `rating` integer 1-5 (required), `review` string max 1000 (nullable).

---

## 3. Konfirmasi Sebelum Konsultasi (Dokter Manusia)

**WAJIB ada dialog konfirmasi** sebelum mulai chat dengan dokter (sesuai kebutuhan aplikasi).

### Dialog Konfirmasi

```
┌────────────────────────────────────────┐
│  Konsultasi Dokter                     │
│                                         │
│  Lakukan konsultasi dengan              │
│  dr. Sari Dewi, Sp.D ?                  │
│                                         │
│  Kamu akan masuk ke ruang chat pribadi  │
│  dengan dokter ini.                     │
│                                         │
│      [ Batal ]   [ Ya, Konsultasi ]     │
└────────────────────────────────────────┘
```

### POST /api/v1/conversations

Setelah user konfirmasi "Ya".

**Request:**
```json
{
  "doctor_id": "doctor-uuid"
}
```

**Response (201):**
```json
{
  "data": {
    "uuid": "conversation-uuid",
    "user": { "...": "user yang login" },
    "doctor": { "...": "dr. Sari Dewi" },
    "message_count": 0,
    "last_message": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

**Kemungkinan error:**

| Status | Pesan | Handling UI |
|---|---|---|
| 422 | "Dokter tidak ditemukan" | UUID invalid / bukan role doctor |
| 422 | "Dokter belum terverifikasi" | Tidak akan terjadi lewat list (sudah filter approved), tapi handle tetap |

**Catatan penting:**
- `firstOrCreate` — jika conversation user+doctor sudah ada, **tidak membuat baru**, return yang lama. Jadi aman klik "Konsultasi" berkali-kali.
- Setelah dapat response → navigasi ke halaman chat dengan `conversation.uuid`

### Halaman Chat

- **GET /api/v1/conversations/{uuid}/messages** — daftar pesan (paginated, urut terbaru duluan — frontend harus `reverse`)
- **POST /api/v1/conversations/{uuid}/messages** — kirim pesan
- Realtime: subscribe `private-conversation.{conversation_uuid}` → listen `.message.sent` (lihat FRONTEND_NOTIFICATION_GUIDE.md)

---

## 4. Alur Khusus: Aura Skin (AI Bot)

**Aura Skin pakai endpoint yang BERBEDA.** Jangan pakai `POST /conversations` untuk Aura Skin.

### Deteksi Aura Skin

Dari list: `is_ai_bot: true` → pakai alur AI chat.
Dari profile: `GET /doctors/{aura-uuid}` juga jalan, tapi button-nya beda ("Chat dengan Aura" bukan "Konsultasi").

### 4.1 Consent AI (Wajib — UU PDP)

Sebelum bisa chat dengan Aura Skin, user **wajib setuju** persetujuan AI.

**GET /api/v1/ai-chat/consent** — cek status:
```json
{
  "data": {
    "accepted": false,
    "version": "1.0",
    "text": "Dengan menyetujui, kamu...",
    "accepted_at": null
  }
}
```

**POST /api/v1/ai-chat/consent** — setujui:
```json
{ "accepted": true }
```

**Flow di frontend:**
```
Klik "Chat dengan Aura"
  → GET /ai-chat/consent
  ├── accepted: true  → langsung POST /ai-chat/conversations
  └── accepted: false → tampilkan consent modal (isi dari field "text")
        ├── User setuju → POST /ai-chat/consent {accepted: true}
        │                 → POST /ai-chat/conversations → masuk chat
        └── User tolak  → batal
```

### 4.2 Mulai Chat Aura Skin

**POST /api/v1/ai-chat/conversations**

Tidak perlu body. Response (201) sama format dengan `POST /conversations`.

**Error jika belum consent (403):**
```json
{ "message": "Setujui penggunaan AI terlebih dahulu sebelum mengobrol dengan Aura Skin." }
```

### 4.3 Batas Chat Gratis

- Free user: **10 pesan/hari** (config `ai.free_daily_limit`) — limit per hari, reset tengah malam
- Pro (subscription active): **unlimited**
- Kena limit → 429: "Kamu sudah mencapai 10 pesan Aura Skin gratis hari ini. Upgrade ke SkinCek Pro untuk chat tanpa batas."

**UI suggestion:** tampilkan counter sisa pesan gratis di halaman chat Aura (bisa hitung dari response atau cek `remaining_free_messages` di GET /profile).

---

## 5. Ringkasan Endpoint

| Aksi | Method & Endpoint | Auth |
|---|---|---|
| List semua dokter (Aura pinned top) | `GET /api/v1/doctors` | Bearer |
| Detail profil dokter | `GET /api/v1/doctors/{uuid}` | Bearer |
| Review dokter | `GET /api/v1/doctors/{uuid}/ratings` | Bearer |
| Tulis/update review | `POST /api/v1/doctors/{uuid}/ratings` | Bearer |
| Mulai konsultasi dokter | `POST /api/v1/conversations` | Bearer |
| Cek consent AI | `GET /api/v1/ai-chat/consent` | Bearer |
| Set/cabut consent AI | `POST /api/v1/ai-chat/consent` | Bearer |
| Mulai chat Aura Skin | `POST /api/v1/ai-chat/conversations` | Bearer |
| Ambil pesan chat | `GET /api/v1/conversations/{uuid}/messages` | Bearer (participant) |
| Kirim pesan | `POST /api/v1/conversations/{uuid}/messages` | Bearer (participant) |

---

## 6. Checklist Testing Frontend

### Daftar Dokter
- [ ] Aura Skin selalu item pertama halaman 1 (badge "AI" + desain beda)
- [ ] Dokter non-approved tidak muncul di list
- [ ] Pagination jalan (per_page default 5)
- [ ] Avatar tampil (URL raw, tidak expired)
- [ ] Rating 0 review → "Belum ada review" (tanpa bintang)
- [ ] Klik card → navigasi ke halaman profil dokter

### Profil Dokter
- [ ] Semua field tampil: title, specialization, sub_specialization, str_number, experience_years, alma_mater, practice_locations, professional_organizations
- [ ] `practice_locations` dan `professional_organizations` di-render sebagai list (array)
- [ ] Dokter random / unapproved UUID → 404 page
- [ ] Review list paginated
- [ ] Field nullable ditangani (tampil "-" atau hidden)

### Konfirmasi & Konsultasi
- [ ] Button "Konsultasi Sekarang" → dialog konfirmasi muncul
- [ ] "Batal" → tetap di profil
- [ ] "Ya, Konsultasi" → POST /conversations → navigasi ke chat
- [ ] Conversation sudah ada → return conversation lama (tidak duplikat), langsung masuk chat
- [ ] Klik konsultasi 2x cepat → tidak membuat 2 conversation

### Aura Skin Flow
- [ ] Klik Aura Skin → TIDAK pakai dialog konfirmasi dokter biasa
- [ ] Belum consent → modal consent AI (isi text dari GET consent) muncul
- [ ] Setuju consent → langsung masuk chat Aura
- [ ] Tolak consent → batal, tidak masuk chat
- [ ] Sudah consent → langsung masuk chat (tanpa modal lagi)
- [ ] Free user kena 10/hari → 429 dengan pesan upgrade Pro
- [ ] Pro user → unlimited

### Chat
- [ ] Pesan urut benar (API terbaru duluan → frontend reverse)
- [ ] Realtime `.message.sent` jalan (lihat FRONTEND_NOTIFICATION_GUIDE.md)
- [ ] Setelah konsultasi, user bisa tulis review dokter
- [ ] User yang belum pernah chat → POST rating 422
