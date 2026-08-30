# Alur Scan (Pemeriksaan Kulit) — Panduan Frontend

> Dokumen ini menjelaskan alur lengkap fitur scan/pemeriksaan kulit:
> dari upload foto → prediksi ML → hasil + **rekomendasi perawatan (tips)** + **rekomendasi skincare (produk)** → riwayat → feedback.
>
> **PENTING — Bedakan 2 istilah ini:**
>
> | Istilah | Arti | Isi |
> |---|---|---|
> | **Perawatan** (`treatment_recommendations`) | **TIPS** | Kebiasaan/habit: "Bersihkan wajah 2x sehari", "Jangan memencet jerawat", "Konsultasi bila memburuk" |
> | **Skincare** (`skincare_recommendations`) | **PRODUK** | Produk riil: "Cetaphil Gentle Skin Cleanser", "Wardah UV Shield SPF 50", lengkap dengan ingredients, cara pakai, warning |

---

## Gambaran Alur

```
┌──────────────────────────────────────────────────────────────────┐
│ ALUR SCAN                                                        │
│                                                                  │
│  1. PERSIAPAN (gate)                                             │
│     ├── Email terverifikasi?          → 403 jika belum          │
│     ├── Profil lengkap? (scan pertama) → 422 jika belum         │
│     └── Kuota harian? (free 3/hari)    → 429 jika habis         │
│                         │                                       │
│                         ▼                                       │
│  2. AMBIL FOTO / UPLOAD                                          │
│     ├── Mode Upload  → POST /scans        (foto penuh)          │
│     └── Mode Livecam → POST /scans/livecam (crop wajah)          │
│                         │                                       │
│                         ▼                                       │
│  3. BACKEND → ML SERVICE (HuggingFace)                           │
│     └── Prediksi: class, confidence, probabilities, severity     │
│                         │                                       │
│                         ▼                                       │
│  4. RESPONSE 201 — HASIL SCAN                                    │
│     ├── predicted_class + confidence + severity                 │
│     ├── skin_concern (nama + deskripsi bahasa Indonesia)         │
│     ├── other_concerns (concern lain > 10% confidence)           │
│     ├── treatment_recommendations  ← TIPS PERAWATAN              │
│     ├── skincare_recommendations   ← PRODUK SKINCARE            │
│     └── notice (jika confidence < 50%)                           │
│                         │                                       │
│                         ▼                                       │
│  5. NOTIFIKASI (background, setelah response)                    │
│     ├── In-app notifikasi realtime (toast "Hasil scan ready!")   │
│     └── Email (max 1x/hari/user)                                 │
│                         │                                       │
│                         ▼                                       │
│  6. RIWAYAT & DETAIL                                             │
│     ├── GET /scans (list + filter)                               │
│     └── GET /scans/{uuid} (detail, format sama)                  │
│                         │                                       │
│                         ▼                                       │
│  7. FEEDBACK                                                     │
│     └── POST /scans/{uuid}/feedback {is_accurate: true|false}    │
└──────────────────────────────────────────────────────────────────┘
```

---

## 1. Gate Sebelum Scan (Pemeriksaan Berurutan)

Backend mengecek 3 hal **sebelum** gambar dikirim ke ML. Urutan penting untuk handling error di UI:

| # | Gate | Error | Pesan Response | Aksi UI |
|---|---|---|---|---|
| 1 | Email terverifikasi? | `403` | `"Verifikasi email terlebih dahulu sebelum menggunakan fitur ini."` | Redirect ke halaman verifikasi email (OTP) |
| 2 | Profil lengkap? (hanya scan PERTAMA) | `422` | `"Lengkapi profil kamu terlebih dahulu (tanggal lahir dan jenis kelamin) sebelum melakukan prediksi pertama."` | Redirect ke form profil (DOB + gender) |
| 3 | Kuota harian? | `429` | `"Kamu sudah mencapai 3 scan gratis hari ini. Upgrade ke SkinCek Pro untuk scan tanpa batas."` | Tampilkan upsell Pro / tunggu besok |

**Kuota:**
- **Free**: 3 scan/hari (reset tengah malam WIB)
- **Pro** (subscription active): unlimited
- Throttle tambahan: 30 scan/menit per user

> Gate #2 hanya berlaku jika user **belum pernah scan sama sekali**. Setelah scan pertama sukses, profil boleh tidak lengkap untuk scan berikutnya.

---

## 2. Upload Foto

Dua mode, endpoint berbeda:

| Mode | Endpoint | Kegunaan |
|---|---|---|
| Upload | `POST /api/v1/scans` | Foto galeri (wajah penuh) |
| Livecam | `POST /api/v1/scans/livecam` | Foto langsung kamera (crop wajah dari livecam) |

**Request** (keduanya sama, `multipart/form-data`):

```
Authorization: Bearer {token}
Content-Type: multipart/form-data

image: [file]   ← required
```

**Validasi image:**
- Mimes: `jpeg, jpg, png, webp`
- Max: **5 MB**
- Wajah harus terdeteksi oleh ML → kalau tidak, 422 (lihat Error ML)

**UI Suggestion:**
- Kamera livecam: tampilkan guide oval posisi wajah
- EXIF otomatis di-strip di backend (lokasi GPS tidak tersimpan — privasi)
- Loading state: "Menganalisis kulitmu..." (ML butuh beberapa detik)

---

## 3. Response Hasil Scan (201)

```json
{
  "data": {
    "uuid": "prediction-uuid",
    "scan_mode": "upload",
    "predicted_class": "inflammatory acne",
    "confidence": 0.8731,
    "probabilities": {
      "inflammatory acne": 0.87,
      "dark spots": 0.12,
      "pores": 0.01
    },
    "severity_score": 42,
    "severity_level": "medium",
    "model_used": "skin-model-v1",
    "image_url": "https://signed-url...",
    "disclaimer": "Hasil scan hanya sebagai referensi awal dan bukan diagnosis medis. Konsultasikan dengan dokter kulit untuk penanganan yang tepat.",

    "skin_concern": {
      "name": "Jerawat Meradang",
      "description": "Jerawat yang tampak merah, bengkak, dan terasa nyeri saat disentuh. Kondisi ini terjadi ketika pori tersumbat dan terinfeksi bakteri..."
    },

    "other_concerns": [
      {
        "ml_label": "dark spots",
        "name": "Flek Hitam",
        "description": "Bercak gelap...",
        "confidence": 0.12
      }
    ],

    "treatment_recommendations": [
      {
        "uuid": "rec-uuid-1",
        "title": "Konsultasikan Bila Peradangan Memburuk",
        "recommendation_text": "Jerawat meradang yang besar, nyeri, atau terus bertambah dalam jumlah bisa memerlukan penanganan medis seperti benzoyl peroxide, adapalene, atau antibiotik topikal dari dokter kulit...",
        "priority_level": "high"
      },
      {
        "uuid": "rec-uuid-2",
        "title": "Rutinitas Pembersihan Ganda yang Lembut",
        "recommendation_text": "Bersihkan wajah dua kali sehari dengan pembersih berbahan dasar air...",
        "priority_level": "medium"
      }
    ],

    "skincare_recommendations": [
      {
        "uuid": "prod-uuid-1",
        "name": "Cetaphil Gentle Skin Cleanser",
        "category": "cleanser",
        "gender": "unisex",
        "key_ingredients": "Water, Cetyl Alcohol, Propylene Glycol, Sodium Lauryl Sulfate, Stearyl Alcohol",
        "usage_instruction": "Basahi wajah dengan air suhu ruang, tuang sedikit cleanser ke telapak tangan...",
        "warning": "Hindari kontak langsung dengan mata. Bila iritasi terjadi, hentikan penggunaan...",
        "skin_type": "Sensitif",
        "doctor": "dr. Sari Dewi"
      },
      {
        "uuid": "prod-uuid-2",
        "name": "Evyaphea Acnederm Gel 10g (Adapalene 0.1%)",
        "category": "treatment",
        "gender": "unisex",
        "key_ingredients": "Adapalene 0.1%",
        "usage_instruction": "Aplikasikan lapisan tipis ke area jerawat sekali sehari...",
        "warning": "Ibu hamil dan menyusui wajib konsultasi dokter terlebih dahulu...",
        "skin_type": "Berminyak",
        "doctor": "dr. Sari Dewi"
      }
    ],

    "notice": null,
    "created_at": "2026-08-30T10:00:00.000000Z"
  }
}
```

### Penjelasan Field

| Field | Keterangan | UI |
|---|---|---|
| `predicted_class` | Label ML (bahasa Inggris, mis. `inflammatory acne`) | Jangan tampilkan mentah — pakai `skin_concern.name` |
| `confidence` | 0–1 | Tampilkan "Tingkat keyakinan: 87%" |
| `severity_score` | 0–100 | Gauge/meter |
| `severity_level` | `low` / `medium` / `high` | Badge warna (hijau/kuning/merah) |
| `skin_concern` | Nama + deskripsi **Bahasa Indonesia** | Judul utama hasil |
| `other_concerns` | Concern lain dengan confidence > 10% | Section "Kondisi lain terdeteksi" |
| `treatment_recommendations` | **TIPS PERAWATAN** — urut high→medium→low | Section "Rekomendasi Perawatan" (ikon tips 💡) |
| `skincare_recommendations` | **PRODUK SKINCARE** — urut sesuai seeder | Section "Rekomendasi Skincare" (ikon produk 🧴) |
| `notice` | Terisi jika confidence < 50% | Warning banner kuning |
| `disclaimer` | Disclaimer medis | Footer hasil scan (WAJIB tampil) |

### 8 Kemungkinan predicted_class

`Redness`, `dark spots`, `inflammatory acne`, `non inflammatory acne black heads`, `non inflammatory acne white heads`, `pigmentation`, `pores`, `wrinkles` — semuanya punya `skin_concern.name` Bahasa Indonesia (dari seeder).

Jika class tidak cocok concern manapun: `skin_concern`, `treatment_recommendations`, `skincare_recommendations` = kosong/null.

### UI Layout Hasil (Suggestion)

```
┌─────────────────────────────────────────┐
│ [Foto scan]              [Gauge 42/100] │
│                                         │
│ Jerawat Meradang          ⚠ Medium     │
│ Tingkat keyakinan: 87%                 │
│                                         │
│ ── Rekomendasi Perawatan (Tips) 💡 ──   │
│ ⚠ [HIGH] Konsultasikan Bila Memburuk   │
│    Jerawat meradang yang besar...       │
│ • [MEDIUM] Pembersihan Ganda Lembut    │
│    Bersihkan wajah dua kali sehari...   │
│                                         │
│ ── Rekomendasi Skincare (Produk) 🧴 ──  │
│ ┌───────────────────────────────────┐   │
│ │ Cetaphil Gentle Skin Cleanser     │   │
│ │ Cleanser • Sensitif • Unisex      │   │
│ │ Ingredients: Water, Cetyl...      │   │
│ │ Cara pakai: Basahi wajah...       │   │
│ │ ⚠ Hindari kontak mata...          │   │
│ │ — direkomendasikan dr. Sari Dewi │   │
│ └───────────────────────────────────┘   │
│ ┌───────────────────────────────────┐   │
│ │ Evyaphea Acnederm Gel (Adapalene) │   │
│ │ ...                               │   │
│ └───────────────────────────────────┘   │
│                                         │
│ ⚠ Disclaimer: Hasil scan hanya...      │
│                                         │
│ [ Apakah hasil ini akurat? ]            │
│   [ Ya, akurat ]  [ Tidak akurat ]      │
└─────────────────────────────────────────┘
```

---

## 4. Notifikasi Setelah Scan

Backend otomatis mengirim 2 notifikasi **setelah** response 201 (user sudah melihat hasil):

1. **In-app realtime** (setiap scan) — via WebSocket:
   - Toast: "Hasil scan kamu sudah ready!" (type: success)
   - Channel: `private-user.{uuid}`, event: `.notification.new`
   - `action_url`: `/scan/history/{prediction_uuid}`
   - Lihat FRONTEND_NOTIFICATION_GUIDE.md untuk implementasi

2. **Email** (max **1x/hari** per user, dedup):
   - Subject: hasil scan + ringkasan prediksi

> UI tidak perlu menampilkan notifikasi di halaman hasil scan itu sendiri (user sudah di sana), tapi bell count harus bertambah.

---

## 5. Riwayat & Detail Scan

### GET /api/v1/scans

List riwayat scan milik user (paginated).

**Query params:**
- `per_page` (default 5)
- `filter[scan_mode]` — `upload` | `livecam`
- `filter[predicted_class]` — mis. `inflammatory acne`
- `sort` — hanya `created_at`, default `-created_at`

**Response:** array item dengan **format sama persis** dengan response scan (termasuk `treatment_recommendations` + `skincare_recommendations`).

### GET /api/v1/scans/{uuid}

Detail satu scan. Scan milik user lain → `404`.

> `image_url` adalah signed URL — bisa expired. Re-fetch detail saat menampilkan ulang.

### Retensi Foto

Foto scan otomatis dihapus setelah **90 hari** (scheduled `scan-photos:purge` daily). Riwayat (tanpa foto) tetap ada.

---

## 6. Feedback

**POST /api/v1/scans/{uuid}/feedback**

```json
{ "is_accurate": true }
```

**Response:**
```json
{ "data": { "is_accurate": true }, "meta": { "message": "Terima kasih atas masukan Anda" } }
```

- Idempoten — feedback terakhir menimpa feedback lama
- Tampilkan di halaman hasil: "Apakah hasil ini akurat?" → 2 button
- Setelah submit → hide form / tampilkan "Terima kasih"

---

## 7. Error Handling

| Skenario | Status | Pesan | Aksi UI |
|---|---|---|---|
| Email belum verified | `403` | "Verifikasi email terlebih dahulu..." | Redirect verifikasi OTP |
| Profil belum lengkap (scan pertama) | `422` | "Lengkapi profil kamu..." | Redirect form profil |
| Kuota habis (free) | `429` | "Kamu sudah mencapai 3 scan gratis hari ini..." | Upsell Pro |
| Wajah tidak terdeteksi | `422` | "Wajah tidak terdeteksi. Silakan upload foto wajah yang jelas dan menghadap kamera." | Retry dengan foto lebih jelas |
| ML service down/sibuk | `502` | "Layanan prediksi sedang sibuk, coba lagi nanti" | Retry button |
| Image > 5MB / format salah | `422` | Validation error | Tampilkan sebelum submit (client-side check) |
| Scan milik user lain | `404` | — | Not found page |

---

## 8. Checklist Testing Frontend

### Gate
- [ ] User belum verifikasi email → scan ditolak 403 → redirect ke OTP
- [ ] User baru tanpa profil → scan ditolak 422 → redirect form profil → isi → scan sukses
- [ ] Free user scan ke-4 hari itu → 429 + upsell Pro
- [ ] Pro user → tidak pernah kena 429

### Upload & Livecam
- [ ] Upload mode tersimpan dengan `scan_mode: "upload"`
- [ ] Livecam mode tersimpan dengan `scan_mode: "livecam"`
- [ ] Image > 5MB ditolak client-side sebelum hit API
- [ ] EXIF/GPS tidak tersimpan di foto (opsional verifikasi)

### Hasil
- [ ] `skin_concern.name` Bahasa Indonesia tampil sebagai judul (bukan `predicted_class` mentah)
- [ ] `treatment_recommendations` urut high → medium → low
- [ ] `skincare_recommendations` tampil sebagai kartu produk (nama, kategori, ingredients, cara pakai, warning, skin_type, nama dokter)
- [ ] `other_concerns` tampil jika ada (confidence > 10%)
- [ ] Confidence < 50% → warning banner muncul
- [ ] Disclaimer selalu tampil di footer hasil

### Notifikasi
- [ ] Toast realtime "Hasil scan kamu sudah ready!" muncul (di halaman lain)
- [ ] Bell count bertambah
- [ ] Klik toast → navigasi ke detail scan
- [ ] Email max 1x/hari walau scan berkali-kali

### Riwayat & Feedback
- [ ] Riwayat terurut terbaru duliu
- [ ] Filter scan_mode + predicted_class jalan
- [ ] Detail scan = format identik hasil scan awal
- [ ] Submit feedback → form ter-replace "Terima kasih"
- [ ] Submit feedback 2x → tidak error (idempotent)
