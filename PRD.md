# Product Requirements Document (PRD)

## Face Skin Predict — Migrasi Backend ke Laravel API

|                  |                                                                                                         |
| ---------------- | ------------------------------------------------------------------------------------------------------- |
| **Versi**        | 2.0 (Final)                                                                                             |
| **Tanggal**      | 3 September 2026                                                                                        |
| **Disusun oleh** | Akazell                                                                                                 |
| **Status**       | Final — dokumen sinkron dengan implementasi backend (be-skincek) yang sudah berjalan                     |

> **Changelog v2.0:** penyelarasan penuh PRD dengan implementasi aktual backend: (1) fitur **AI Chat "Aura Skin"** — bot dokter AI berbasis Gemini dengan consent terversi, kuota harian, dan safety filter; (2) **Verifikasi email berbasis OTP** menjadi prasyarat scan, chat, dan checkout; (3) **Notification Center in-app** (CRUD notifikasi, unread count, mark read) di atas Reverb + FCM; (4) **Rating & review dokter** oleh user yang pernah chat; (5) **Feedback akurasi hasil scan**; (6) **Dashboard admin & dokter** dengan statistik; (7) **CRUD user oleh admin** (create/update/soft-delete) melengkapi assign role & suspend; (8) **Export data akun** (JSON, signed URL, hak UU PDP); (9) **Emergency hotline** publik; (10) **moderasi konten chat** (bad-word filter); (11) **pesan chat kaya** — image/video/attachment hasil scan; (12) **profil lengkap user** (DOB, gender) sebagai prasyarat scan pertama; (13) **ganti avatar dengan rate-limit 1x/24 jam** + strip EXIF; (14) paket Pro berubah dari lifetime menjadi **monthly** (lihat bagian 7.10, 8.10, 13); (15) **scheduled jobs** (backup, purge foto scan, prune user terhapus, expire subscription, cleanup notifikasi); (16) **IP geolocation** untuk Login Activity. Kontrak API bagian 9 diperbarui lengkap sesuai `routes/api.php`.
> **Changelog v1.4:** response `GET /api/v1/profile` (role user) kini menampilkan `user_messages_count` (total pesan gratis yang sudah dipakai, dihitung global lintas semua dokter) dan `remaining_free_messages` (sisa kuota 3 pesan gratis) agar frontend bisa menampilkan/menghambat chat sebelum API menolak 402. Lihat bagian 7.8, 8.3, 9.
> **Changelog v1.3:** pagination list dapat dikontrol via `?per_page=10/20/50` (default 10) pada semua endpoint list publik: riwayat scan, daftar dokter, skin recommendations, dan skincare products. Lihat bagian 8.8, 9.
> **Changelog v1.2:** perombakan konsep chat — kuota 3 pesan gratis menjadi **global lintas semua dokter** (bukan per percakapan), ditambahkan **daftar dokter** & **profil dokter lengkap** (gelar, spesialisasi & subspesialisasi, STR, pengalaman praktik, almamater, lokasi praktik offline, organisasi profesi) yang diisi dokter lewat form verifikasi dan hanya tampil setelah disetujui admin. Lihat bagian 7.1, 7.2, 7.8, 8.3, 8.8, 9, 13.
> **Changelog v1.1:** fitur profil lanjutan — status langganan (Free/Pro), riwayat langganan (append-only log), receipt pembayaran, dan Login Activity (extend `personal_access_tokens`). Paket Pro ditetapkan lifetime, one-time payment Rp15.000. Lihat bagian 7.10, 7.12, 8.10, 9, 13.
> **Changelog v1.0:** menutup seluruh gap enterprise-readiness: API versioning (`/api/v1`), error tracking production (Sentry), konsistensi format response (API Resource), rate limiting menyeluruh, idempotency webhook Midtrans, strategi testing & CI, backup & disaster recovery, kepatuhan awal UU PDP (consent + right to erasure), dan endpoint profile resmi. Lihat bagian 3, 7.1, 8.1, 8.6, 8.10, 9, 11, 12, 13.
> **Changelog v0.9:** token Sanctum tidak pakai auto-expiry (sesi aktif sampai logout manual) — ditambahkan endpoint logout & logout-all sebagai jaring pengaman. Lihat bagian 8.1, 9, 11.
> **Changelog v0.8:** ditambahkan fitur chat user-dokter dengan model freemium + Midtrans (tanpa E2EE — disederhanakan jadi enkripsi at-rest), dan arsitektur ML service dibuat adaptif (adapter pattern) untuk kesiapan ganti model ke depan — lihat bagian 3, 5, 6, 7.8-7.10, 8.8-8.9, 9, 12, 13.
> **Changelog v0.7:** ditambahkan fitur lupa password berbasis OTP (email) — lihat bagian 8.7, 9, 12, 13.
> **Changelog v0.6:** ditambahkan Google Sign-In sebagai metode autentikasi tambahan (verifikasi ID token, bukan redirect OAuth penuh) — lihat bagian 3, 7.1, 8.1, 9, 12, 13.
> **Changelog v0.5:** ditetapkan strategi primary key hybrid (`id` bigint internal + `uuid` publik) untuk kesiapan skala enterprise — lihat bagian 7 & 13.
> **Changelog v0.4:** _(dilewati — bagian implementasi skeleton dihapus dari dokumen ini, PRD fokus sebagai dokumen requirement)_
> **Changelog v0.3:** seluruh item di "Open Questions" v0.2 sudah diputuskan dan dipindahkan jadi keputusan resmi — lihat bagian 13.
> **Changelog v0.2:** keputusan database ditetapkan MySQL 8+ (bukan lanjut PostgreSQL).

---

## 1. Ringkasan

Face Skin Predict (SkinCek) adalah aplikasi web yang memungkinkan pengguna memindai foto wajah untuk mendapatkan analisis kondisi kulit (jerawat, kemerahan, flek hitam, pigmentasi, pori-pori, kerutan, dll) menggunakan model machine learning, lengkap dengan riwayat pemindaian, rekomendasi skincare dari dokter terverifikasi, konsultasi chat dengan dokter, asisten AI "Aura Skin", serta paket berlangganan Pro via Midtrans.

Dokumen ini mendefinisikan requirement untuk fase migrasi backend: dari Supabase (BaaS) ke backend kustom berbasis **Laravel API-only** (tanpa Blade view), sementara frontend Next.js dan model ML yang sudah di-hosting di HuggingFace Space tetap dipertahankan. Backend dirancang dengan asumsi project ini akan dikembangkan ke **skala enterprise**, sehingga beberapa keputusan teknis (lihat bagian 13) diambil dengan pertimbangan skalabilitas jangka panjang, bukan hanya kebutuhan jangka pendek.

## 2. Latar Belakang

Sebelumnya, aplikasi menggunakan Supabase sebagai backend (database Postgres + auth + storage), dengan skema yang mencakup manajemen profil pengguna, verifikasi dokter, katalog kondisi & tipe kulit, produk skincare, rekomendasi, dan riwayat prediksi. Model ML untuk analisis kulit sudah berjalan independen sebagai layanan terpisah di HuggingFace Space.

Tim memutuskan berhenti menggunakan Supabase dan membangun backend kustom dengan Laravel agar punya kontrol penuh atas business logic, autentikasi, role/permission, dan orkestrasi pemanggilan ke layanan ML eksternal.

## 3. Tujuan

- Mengganti seluruh akses data yang sebelumnya lewat Supabase client dengan REST API Laravel, tanpa mengubah pengalaman pengguna di sisi frontend.
- Menyediakan autentikasi berbasis token (Sanctum) untuk semua endpoint.
- Menerapkan role & permission yang lebih granular (Spatie Permission) untuk tiga peran: user, doctor, admin.
- Mengelola seluruh file (foto scan, avatar, dokumen verifikasi dokter) lewat satu sistem media terstruktur (Spatie Media Library).
- Menyediakan notifikasi real-time (Laravel Reverb) untuk event penting, mis. status verifikasi dokter.
- Menjaga jejak audit untuk aksi-aksi sensitif (Spatie Activity Log).
- Memudahkan debugging selama development lewat Laravel Telescope.
- Merancang skema data & identifier yang siap menampung pertumbuhan skala enterprise (lihat bagian 13).
- Mendukung Google Sign-In sebagai metode autentikasi tambahan di samping email/password.
- Menyediakan alur reset password berbasis OTP yang aman terhadap brute-force & email enumeration.
- Menyediakan fitur chat user-dokter dengan model freemium (3 pesan gratis untuk semua dokter / kuota global, lanjut butuh subscription via Midtrans) lengkap dengan daftar dokter & profil dokter lengkap, plus asisten AI "Aura Skin" (konsultasi edukasi skincare berbasis Gemini, dengan consent & kuota harian).
- Menyediakan verifikasi email berbasis OTP sebagai prasyarat fitur sensitif (scan, chat, checkout) untuk menjaga kualitas akun & mitigasi penyalahgunaan.
- Menyediakan Notification Center in-app (notifikasi tersimpan di database, dibaca/tandai/hapus) di atas real-time broadcast Reverb dan push FCM.
- Memungkinkan user memberi rating & review dokter yang pernah diajak chat, dan feedback akurasi hasil scan — sebagai masukan kualitas layanan & model.
- Menyediakan dashboard berisi statistik untuk admin (pengguna, scan, revenue, verifikasi) dan dokter (pasien, chat, produk, rating).
- Memunginkan admin mengelola user secara penuh (CRUD + assign role + suspend) dengan jejak audit Activity Log.
- Memenuhi hak data-subjek UU PDP: consent eksplisit, export data akun (JSON), dan penghapusan akun dengan masa tenggang.
- Merancang integrasi ML service yang mudah diganti/di-upgrade (mis. ke model deep learning) tanpa mengubah kontrak API.
- Memenuhi praktik enterprise-readiness: API versioning, observability production, konsistensi response, rate limiting, idempotency, strategi backup, dan kepatuhan awal terhadap UU PDP.

## 4. Target Pengguna / Role

| Role       | Deskripsi                                                                                                           |
| ---------- | ------------------------------------------------------------------------------------------------------------------- |
| **User**   | Pengguna umum — scan wajah, lihat riwayat prediksi, lihat rekomendasi skincare                                      |
| **Doctor** | Dokter yang sudah lolos verifikasi — membuat produk skincare & rekomendasi berdasarkan kondisi kulit                |
| **Admin**  | Mereview & memverifikasi pengajuan dokter, mengelola katalog referensi (skin concerns, skin types), moderasi konten |

## 5. Ruang Lingkup

### 5.1 Termasuk (in scope)

- Autentikasi & manajemen role (Sanctum + Spatie Permission)
- Endpoint scan kulit yang meneruskan gambar ke ML service dan menyimpan hasilnya
- Riwayat prediksi per user, dengan filter & sorting
- Alur pengajuan, review, dan pengajuan ulang verifikasi dokter
- Manajemen katalog skin concerns & skin types
- Manajemen skincare products & skin recommendations oleh dokter terverifikasi
- Notifikasi real-time untuk event terkait verifikasi (dan opsional, hasil scan)
- Audit trail untuk aksi review/moderasi
- Chat antara user & dokter terverifikasi, dengan model freemium (3 pesan pertama gratis lintas semua dokter), pesan kaya (teks, foto, video, lampiran hasil scan), dan moderasi konten (bad-word filter)
- Asisten AI "Aura Skin" — chat edukasi skincare berbasis Gemini dengan consent terversi, kuota harian gratis, dan safety layer (filter kata kunci medis darurat)
- Rating & review dokter oleh user yang pernah diajak chat
- Feedback akurasi hasil scan oleh user
- Notification Center in-app (list, unread count, mark read, hapus) + broadcast Reverb + push FCM
- Verifikasi email berbasis OTP (prasyarat scan, chat, checkout)
- Dashboard statistik untuk admin & dokter
- Manajemen user oleh admin (CRUD, assign role, suspend/aktifkan) dengan Activity Log
- Daftar dokter terverifikasi beserta profil lengkap yang bisa dilihat user sebelum memulai chat
- Checkout & aktivasi subscription lewat Midtrans (mode sandbox untuk testing) — paket Pro monthly
- Export data akun (JSON, signed URL) & emergency hotline publik

### 5.2 Belum termasuk (out of scope — fase ini)

- Migrasi data historis dari Supabase ke database baru (perlu skrip terpisah)
- Retraining atau re-hosting model ML saat ini (tetap memakai HuggingFace Space yang sudah berjalan — lihat bagian 8.9 untuk kesiapan ganti model di masa depan)

## 6. Arsitektur Sistem (high-level)

- **Frontend** — Next.js (web) dan Flutter (mobile), dua-duanya konsumen dari kontrak API yang sama; autentikasi token Sanctum sudah native mobile-friendly tanpa perubahan.
- **Backend** — Laravel 13+, API-only (Sanctum untuk auth, Reverb untuk broadcasting, Spatie stack untuk permission/media/log/query, Telescope untuk debugging).
- **ML Service** — saat ini FastAPI di HuggingFace Space (Docker), memakai YOLOv8n untuk deteksi & crop wajah dan pipeline SVM (ekstraksi fitur luminance/HOG/LBP/GLCM/Gabor → StandardScaler → PCA → SelectKBest → SVM RBF). Dipanggil dari Laravel lewat interface adapter (`SkinPredictionServiceContract`), bukan langsung dari controller — lihat bagian 8.9 & 13 untuk strategi ganti model di masa depan.
- **AI Service** — Google Gemini untuk asisten chat "Aura Skin", dipanggil lewat adapter (`AiChatServiceContract`) dengan safety layer filter kata kunci + fallback template; balasan dibuat async via queue job (lihat bagian 8.11).
- **Storage** — Spatie Media Library; development memakai disk lokal, production memakai **Cloudflare R2** (S3-compatible, driver `s3`) dengan private bucket + signed/temporary URL, karena foto wajah & hasil analisis kulit tergolong data personal.
- **Database** — MySQL 8+ (menggantikan Postgres yang sebelumnya dipakai Supabase), dipilih karena ketersediaan hosting yang lebih luas & murah, ekosistem dokumentasi Laravel yang lebih besar, dan fitur Postgres di skema lama (UUID, JSON) punya padanan yang cukup di MySQL 8+.

## 7. Model Data

Diturunkan dari skema Supabase sebelumnya, dipetakan ulang ke konvensi Eloquent/Laravel dengan target MySQL 8+. Kolom status/level memakai `VARCHAR` + PHP backed enum (lihat bagian 13), bukan tipe `ENUM` MySQL.

**Strategi primary key (hybrid, lihat bagian 13):** setiap tabel punya `id` (`BIGINT` auto-increment) sebagai PK asli yang dipakai untuk foreign key & clustered index, dan `uuid` (`CHAR(36)`, unique) sebagai satu-satunya identifier yang di-expose ke API/URL. Kolom `id` **tidak pernah** dikembalikan di response JSON.

### 7.1 users _(sebelumnya: profiles)_

| Field              | Tipe (MySQL)              | Keterangan                                                             |
| ------------------ | ------------------------- | ---------------------------------------------------------------------- |
| id                 | bigint unsigned           | PK internal — disembunyikan dari API                                   |
| uuid               | char(36), unique          | identifier publik                                                      |
| full_name          | varchar                   |                                                                        |
| email              | varchar, unique           |                                                                        |
| password           | varchar, nullable         | kosong untuk user yang hanya pernah login via Google                   |
| google_id          | varchar, unique, nullable | `sub` claim dari ID token Google, diisi saat register/login via Google |
| privacy_consent_at | timestamp, nullable       | waktu user menyetujui pemrosesan data wajah/kesehatan (FR-1d, UU PDP)  |
| deleted_at         | timestamp, nullable       | soft delete — dipakai untuk alur penghapusan akun (FR-33)              |
| date_of_birth      | date, nullable            | prasyarat scan pertama (FR-45, profil lengkap)                          |
| gender             | varchar, nullable         | `laki_laki` / `perempuan` — prasyarat scan pertama (FR-45)             |
| ai_bot             | boolean, default false     | menandai akun bot AI "Aura Skin" (role doctor + flag khusus, FR-41)    |
| avatar_updated_at  | timestamp, nullable       | rate-limit ganti avatar 1x/24 jam (FR-46)                              |
| google_avatar_url  | varchar, nullable         | avatar dari akun Google (fallback kalau tidak upload avatar sendiri)    |
| role               | —                         | digantikan assignment role Spatie Permission (user/doctor/admin)       |
| avatar_url         | —                         | digantikan relasi Media Library (collection `avatar`)                  |
| is_active          | boolean                   | default true                                                           |
| user_messages_count | unsigned int, default 0  | total pesan yang pernah dikirim user ke semua dokter — dipakai untuk cek paywall global (FR-24, FR-39) |
| timestamps         |                           |                                                                        |

### 7.2 doctor_verifications

| Field               | Tipe (MySQL)                   | Keterangan                                                    |
| ------------------- | ------------------------------ | ------------------------------------------------------------- |
| id                  | bigint unsigned                | PK internal                                                   |
| uuid                | char(36), unique               | identifier publik                                             |
| doctor_id           | bigint unsigned, FK → users.id | unique, satu pengajuan aktif per dokter                       |
| str_number          | varchar, nullable              | nomor STR                                                     |
| title               | varchar, nullable              | gelar medis, mis. `dr.`, `dr. Sp.PD`, `dr. Sp.KK`             |
| specialization      | varchar                        | spesialisasi utama (wajib)                                    |
| sub_specialization  | varchar, nullable              | subspesialisasi / fokus layanan                               |
| experience_years    | smallint unsigned, nullable    | tahun pengalaman praktik                                      |
| alma_mater          | varchar, nullable              | institusi/universitas pendidikan                              |
| practice_locations  | json, nullable                 | array rumah sakit/klinik praktik offline                      |
| professional_organizations | json, nullable           | array organisasi profesi (IDI, PERDOSKI, IDAI, dsb.)          |
| document_url        | —                              | digantikan Media Library (collection `verification-document`) |
| verification_status | varchar (PHP enum)             | `pending` / `approved` / `rejected` / `needs_revision`        |
| rejection_reason    | text, nullable                 | diisi saat status `rejected`                                  |
| revision_note       | text, nullable                 | diisi saat status `needs_revision`                            |
| reviewed_by         | bigint unsigned, FK → users.id | admin yang mereview                                           |
| reviewed_at         | timestamp, nullable            |                                                               |
| timestamps          |                                |                                                               |

> Alur status: `pending` → `approved` / `rejected` / `needs_revision`. Saat `needs_revision`, dokter dapat mengajukan ulang dokumen dan status kembali ke `pending`. Transisi divalidasi lewat satu Action class (`ReviewDoctorVerification`).

### 7.3 skin_concerns

| Field                  | Tipe (MySQL)                     | Keterangan                                                                                                                                  |
| ---------------------- | -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| id                     | bigint unsigned                  | PK internal                                                                                                                                 |
| uuid                   | char(36), unique                 | identifier publik                                                                                                                           |
| name                   | varchar, unique                  | label tampilan ke user, mis. "Jerawat Meradang" — bisa diubah admin kapan saja                                                              |
| ml_label               | varchar, unique                  | label **persis** sesuai output ML service (mis. `inflammatory acne`) — dipakai untuk mapping otomatis di FR-15, di-seed saat migration awal |
| description            | text, nullable                   |                                                                                                                                             |
| default_severity_score | tinyint unsigned 0–100, nullable |                                                                                                                                             |
| is_active              | boolean                          | default true                                                                                                                                |
| timestamps             |                                  |                                                                                                                                             |

### 7.4 skin_types

| Field       | Tipe (MySQL)     | Keterangan                        |
| ----------- | ---------------- | --------------------------------- |
| id          | bigint unsigned  | PK internal                       |
| uuid        | char(36), unique | identifier publik                 |
| name        | varchar, unique  | mis. "Oily", "Dry", "Combination" |
| description | text, nullable   |                                   |
| timestamps  |                  |                                   |

### 7.5 skincare_products

| Field             | Tipe (MySQL)                                  | Keterangan        |
| ----------------- | --------------------------------------------- | ----------------- |
| id                | bigint unsigned                               | PK internal       |
| uuid              | char(36), unique                              | identifier publik |
| doctor_id         | bigint unsigned, FK → users.id                | pembuat produk    |
| concern_id        | bigint unsigned, FK → skin_concerns.id        |                   |
| skin_type_id      | bigint unsigned, FK → skin_types.id, nullable |                   |
| name              | varchar                                       |                   |
| category          | varchar                                       |                   |
| key_ingredients   | text, nullable                                |                   |
| usage_instruction | text                                          |                   |
| warning           | text, nullable                                |                   |
| is_active         | boolean                                       | default true      |
| timestamps        |                                               |                   |

### 7.6 skin_recommendations

| Field               | Tipe (MySQL)                                         | Keterangan                                  |
| ------------------- | ---------------------------------------------------- | ------------------------------------------- |
| id                  | bigint unsigned                                      | PK internal                                 |
| uuid                | char(36), unique                                     | identifier publik                           |
| concern_id          | bigint unsigned, FK → skin_concerns.id               |                                             |
| product_id          | bigint unsigned, FK → skincare_products.id, nullable |                                             |
| doctor_id           | bigint unsigned, FK → users.id                       |                                             |
| title               | varchar                                              |                                             |
| recommendation_text | text                                                 |                                             |
| priority_level      | varchar (PHP enum)                                   | `low` / `medium` / `high`, default `medium` |
| is_active           | boolean                                              | default true                                |
| timestamps          |                                                      |                                             |

### 7.7 prediction_histories

| Field             | Tipe (MySQL)                   | Keterangan                                                            |
| ----------------- | ------------------------------ | --------------------------------------------------------------------- |
| id                | bigint unsigned                | PK internal                                                           |
| uuid              | char(36), unique               | identifier publik                                                     |
| user_id           | bigint unsigned, FK → users.id |                                                                       |
| scan_mode         | varchar (PHP enum)             | `upload` (foto penuh) / `livecam` (sudah ter-crop di FE)              |
| image_url         | —                              | digantikan Media Library (collection `scan-photo`)                    |
| cropped_image_url | —                              | digantikan Media Library (collection `scan-photo-cropped`)            |
| predicted_class   | varchar                        | label dari ML service                                                 |
| confidence        | decimal(5,4), 0–1              |                                                                       |
| probabilities     | json                           | breakdown probabilitas semua kelas                                    |
| severity_score    | tinyint unsigned, 0–100        | dikonversi dari skala ML (0–1 × 100) saat ingest di Laravel           |
| severity_level    | varchar (PHP enum)             | `low` / `medium` / `high`                                             |
| model_used        | varchar                        | mis. "SVM_RBF_v4"                                                     |
| raw_response      | json, nullable                 | response mentah dari ML service apa adanya, untuk keperluan debugging |
| created_at        | timestamp                      |                                                                       |

### 7.8 conversations

| Field         | Tipe (MySQL)                   | Keterangan                                                             |
| ------------- | ------------------------------ | ---------------------------------------------------------------------- |
| id            | bigint unsigned                | PK internal                                                            |
| uuid          | char(36), unique               | identifier publik                                                      |
| user_id       | bigint unsigned, FK → users.id |                                                                        |
| doctor_id     | bigint unsigned, FK → users.id |                                                                        |
| message_count | unsigned int, default 0        | jumlah pesan dari user (bukan total) — **tampilan saja**, tidak lagi dipakai untuk cek paywall (cek paywall global memakai `users.user_messages_count`, FR-24) |
| timestamps    |                                |                                                                        |

> Satu percakapan per pasangan `user_id` + `doctor_id` (unique constraint gabungan). Kuota pesan gratis dihitung **global** di `users.user_messages_count`, bukan per percakapan.

### 7.9 messages

| Field                 | Tipe (MySQL)                           | Keterangan                                                                                                                               |
| --------------------- | -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| id                    | bigint unsigned                        | PK internal                                                                                                                              |
| uuid                  | char(36), unique                       | identifier publik                                                                                                                        |
| conversation_id      | bigint unsigned, FK → conversations.id |                                                                                                                                          |
| sender_id             | bigint unsigned, FK → users.id         |                                                                                                                                          |
| type                  | varchar (PHP enum), default `text`     | `text` / `image` / `video` / `scan_result` — pesan kaya (FR-23a)                                                                        |
| prediction_history_id | bigint unsigned, FK, nullable          | terisi saat user melampirkan hasil scan (type `scan_result`); `nullOnDelete` (FR-23a)                                                    |
| content               | text, nullable                         | dienkripsi at-rest lewat Eloquent `encrypted` cast (AES-256 via `APP_KEY`) — bukan E2EE, lihat bagian 13; nullable karena pesan bisa berupa media |
| created_at            | timestamp                              | pesan bersifat immutable, tidak ada `updated_at`                                                                                          |

> Media (foto/video) chat disimpan lewat Media Library collection `chat-media`. Foto di-strip metadata EXIF sebelum disimpan (privasi).

### 7.10 subscriptions

| Field             | Tipe (MySQL)                   | Keterangan                                                                                                                                                              |
| ----------------- | ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| id                | bigint unsigned                | PK internal                                                                                                                                                             |
| uuid              | char(36), unique               | identifier publik                                                                                                                                                       |
| user_id           | bigint unsigned, FK → users.id |                                                                                                                                                                         |
| plan_code         | varchar                        | saat ini `pro_monthly` (harga & durasi didefinisikan di `config/plans.php`, bukan tabel)                                                |
| period            | varchar, default `monthly`      | `monthly` — v2.0: paket Pro berubah dari lifetime menjadi monthly (lihat bagian 13)                                                    |
| status            | varchar (PHP enum)             | `pending` / `active` / `expired` / `cancelled`                                                                                          |
| amount            | unsigned int                   | nominal dibayar dalam IDR (mis. `15000`) — harga tetap dipatok IDR, bukan hasil konversi live dari USD                                  |
| currency          | varchar, default `IDR`         | disiapkan kalau nanti nambah metode pembayaran lain                                                                                     |
| midtrans_order_id | varchar, unique, nullable      | referensi transaksi di sisi kita                                                                                                        |
| transaction_id    | varchar, nullable              | ID transaksi dari sisi Midtrans                                                                                                         |
| payment_method    | varchar, nullable              | channel pembayaran apa adanya dari Midtrans (mis. `gopay`, `bank_transfer`) — sengaja tidak dipaksa jadi PHP enum tertutup karena mengikuti vocabulary sistem eksternal |
| starts_at         | timestamp, nullable            |                                                                                                                                         |
| ends_at           | timestamp, nullable            | diisi `starts_at + 30 hari` saat pembayaran sukses; subscription expired otomatis oleh scheduled job (FR-52)                            |
| paid_at           | timestamp, nullable            | waktu pembayaran dikonfirmasi Midtrans                                                                                                  |
| timestamps        |                                |                                                                                                                                         |

> **Append-only log:** setiap pembelian/perpanjangan paket menghasilkan baris baru, bukan meng-update baris yang sudah ada — riwayat langganan (FR-35) otomatis tersedia tanpa tabel terpisah. Status langganan aktif (Free/Pro) di profil (FR-34) dihitung dari baris `status = active` dengan `ends_at` masih di masa depan (atau `period = lifetime`), bukan disimpan sebagai field terpisah di `users`. User dapat membatalkan langganan aktif sendiri (`POST /subscriptions/{id}/cancel`).

### 7.11 device_tokens

| Field      | Tipe (MySQL)                   | Keterangan                        |
| ---------- | ------------------------------ | --------------------------------- |
| id         | bigint unsigned                | PK internal                       |
| uuid       | char(36), unique               | identifier publik                 |
| user_id    | bigint unsigned, FK → users.id |                                   |
| fcm_token  | varchar                        | token push notification perangkat |
| platform   | varchar (PHP enum)             | `ios` / `android`                 |
| timestamps |                                |                                   |

### 7.12 personal_access_tokens (ekstensi Sanctum)

| Field tambahan | Tipe (MySQL)      | Keterangan                                                           |
| -------------- | ----------------- | -------------------------------------------------------------------- |
| uuid           | char(36), unique  | identifier publik untuk Login Activity (FR-37, FR-38)                |
| ip_address     | varchar, nullable | dicatat otomatis saat token dibuat (login/register/Google)           |
| user_agent     | varchar, nullable | diparse jadi info device yang manusiawi untuk Login Activity (FR-37) |

> Login Activity (FR-37, FR-38) sengaja tidak dibuatkan tabel baru — token Sanctum memang representasi satu sesi login, jadi cukup migration tambahan untuk menambah kolom di atas ke tabel bawaan Sanctum. Lokasi ditampilkan ke user berdasarkan resolusi `ip_address` lewat layanan ip-api.com (perkiraan kota/negara, **bukan** lokasi presisi — bisa meleset kalau user memakai VPN), hasil resolusi di-cache 24 jam dengan rate-limit 45 req/menit.

### 7.13 notifications

| Field      | Tipe (MySQL)                   | Keterangan                                                        |
| ---------- | ------------------------------ | ----------------------------------------------------------------- |
| id         | uuid, PK                       | identifier publik (tabel ini memakai UUID langsung sebagai PK)    |
| user_id    | bigint unsigned, FK → users.id | cascade delete                                                     |
| type       | varchar(20)                    | `success` / `warning` / `error` / `info` — menentukan warna & icon di FE |
| category   | varchar(50)                    | `welcome` / `scan_complete` / `chat_message` / `verification_*` / `subscription_active` / `logout`, dll |
| title      | varchar                        | judul notifikasi                                                  |
| message    | text, nullable                 | isi notifikasi                                                    |
| action_url | varchar(500), nullable         | deep-link ke halaman terkait (mis. `/scan/history/{uuid}`)        |
| read_at    | timestamp, nullable           | null = belum dibaca                                               |
| timestamps |                                |                                                                   |

> Notifikasi dibuat terpusat lewat `NotificationService` (factory method per use case), lalu dibroadcast real-time via Reverb event `NotificationSent` (`ShouldBroadcastNow` — synchronous, tanpa queue). Notifikasi lama dibersihkan oleh scheduled job `notifications:clean` (FR-52).

### 7.14 prediction_feedbacks

| Field                 | Tipe (MySQL)                                | Keterangan                                                       |
| --------------------- | ------------------------------------------- | ---------------------------------------------------------------- |
| id                    | bigint unsigned                             | PK internal                                                       |
| prediction_history_id | bigint unsigned, FK → prediction_histories.id | unique per user per scan — satu feedback per hasil scan (FR-48)  |
| user_id               | bigint unsigned, FK → users.id              | cascade delete                                                    |
| is_accurate           | boolean                                     | apakah user merasa prediksi akurat                                |
| timestamps            |                                             |                                                                   |

### 7.15 doctor_ratings

| Field      | Tipe (MySQL)                   | Keterangan                                                              |
| ---------- | ------------------------------ | ----------------------------------------------------------------------- |
| id         | bigint unsigned                | PK internal                                                              |
| user_id    | bigint unsigned, FK → users.id | pemberi rating — unique per pasangan user-dokter (satu rating, bisa direvisi) |
| doctor_id  | bigint unsigned, FK → users.id | dokter yang dinilai (harus approved & pernah diajak chat, FR-49)         |
| rating     | unsigned tinyint               | 1–5                                                                      |
| review     | varchar, nullable              | teks ulasan opsional (max 1000 karakter)                                 |
| timestamps |                                |                                                                         |

> Rating dihitung rata-rata & total di endpoint publik dokter (`GET /doctors`, `GET /doctors/{id}`) lewat agregat `withAvg`/`withCount`, juga tampil di dashboard dokter.

### 7.16 ai_chat_consents

| Field           | Tipe (MySQL)                   | Keterangan                                                       |
| --------------- | ------------------------------ | ---------------------------------------------------------------- |
| id              | bigint unsigned                | PK internal                                                       |
| user_id         | bigint unsigned, FK → users.id | unique per pasangan user + consent_version                        |
| consent_version | varchar                        | versi teks consent yang disetujui user (default `v1`)              |
| ip_address      | varchar(45), nullable         | dicatat saat consent diberikan (jejak audit UU PDP)               |
| accepted_at     | timestamp, nullable            | waktu persetujuan; semua baris dihapus saat consent dicabut (FR-41) |
| timestamps      |                                |                                                                   |

> Catatan migrasi umum: skema lama memakai fitur native Postgres (uuid type, RLS). RLS tidak punya padanan otomatis di Laravel — akses per-role perlu diimplementasikan manual lewat Policy/Middleware.

## 8. Requirement Fungsional

### 8.1 Autentikasi & Role Management

- **FR-1** — User dapat register/login dan menerima token Sanctum.
- **FR-2** — Admin dapat menetapkan role (user/doctor/admin) via Spatie Permission.
- **FR-3** — Endpoint sensitif diproteksi middleware role/permission.
- **FR-1a** — User dapat register/login via Google Sign-In: frontend memakai Google Identity Services untuk mendapatkan ID token, backend memverifikasi token tersebut (bukan flow redirect OAuth penuh), mencari/membuat user berdasarkan email, assign role `user` kalau baru, lalu menerbitkan token Sanctum.
- **FR-1b** — Token Sanctum tidak memiliki masa berlaku otomatis (`config('sanctum.expiration') = null`); sesi tetap aktif sampai user logout manual.
- **FR-1c** — User dapat logout dari device yang sedang dipakai (`POST /logout`, revoke token aktif) atau logout dari seluruh device sekaligus (`POST /logout-all`, revoke semua token — untuk kondisi akun dicurigai diakses pihak lain).
- **FR-1d** — User memberikan persetujuan eksplisit (consent) atas pemrosesan data wajah & kondisi kulit saat registrasi; waktu persetujuan dicatat di `privacy_consent_at`.

### 8.2 Skin Scan & Prediction

- **FR-4** — User upload foto penuh → backend meneruskan ke `POST /predict` di ML service → hasil disimpan ke `prediction_histories`, foto disimpan lewat Media Library (metadata EXIF di-strip untuk privasi).
- **FR-5** — User scan via livecam (gambar sudah ter-crop di FE) → backend meneruskan ke `POST /predict-crop`.
- **FR-6** — Response ML (`predicted_class`, `confidence`, `probabilities`, `severity_score`, `severity_level`, `model_used`) disimpan sesuai skema; `severity_score` dikonversi dari 0–1 ke 0–100, dan response mentah disimpan di `raw_response`.
- **FR-7** — User dapat melihat riwayat scan (list & detail), difilter berdasarkan `scan_mode`/`predicted_class`, diurutkan berdasarkan `created_at` (Spatie Query Builder), diakses lewat `uuid` publik.
- **FR-44** — Scan hanya bisa dilakukan user dengan email terverifikasi (403 jika belum); scan pertama membutuhkan profil lengkap (tanggal lahir & jenis kelamin, 422 jika belum).
- **FR-45** — User gratis dibatasi **3 scan per hari** (429 jika melebihi, dihitung per tanggal); user dengan subscription Pro aktif tidak dibatasi. Setelah scan selesai, user menerima notifikasi in-app + email berisi ringkasan hasil.
- **FR-48** — User dapat memberi feedback apakah hasil scan akurat atau tidak (`POST /scans/{id}/feedback`); satu feedback per hasil scan, dipakai untuk evaluasi kualitas model ML.

### 8.3 Verifikasi Dokter

- **FR-8** — Dokter mengajukan verifikasi (`str_number`, `specialization`, upload dokumen) sekaligus data profil (opsional): `title`, `sub_specialization`, `experience_years`, `alma_mater`, `practice_locations`, `professional_organizations` — data profil hanya tampil ke publik setelah status `approved` (FR-40).
- **FR-9** — Admin mereview pengajuan: approve, reject (dengan `rejection_reason`), atau minta revisi (dengan `revision_note`); tercatat di `reviewed_by` & `reviewed_at`.
- **FR-9a** — Jika status `needs_revision`, dokter dapat mengajukan ulang dokumen; status kembali ke `pending` untuk direview lagi.
- **FR-10** — Perubahan status verifikasi memicu notifikasi real-time ke dokter terkait (Reverb).
- **FR-11** — Setiap aksi review tercatat di Activity Log.

### 8.4 Katalog & Rekomendasi Skincare

- **FR-12** — Admin mengelola `skin_concerns` (termasuk `ml_label`) & `skin_types` (CRUD, toggle `is_active`).
- **FR-13** — Dokter terverifikasi membuat `skincare_products` terkait concern & skin type.
- **FR-14** — Dokter membuat `skin_recommendations` terkait concern, produk (opsional), dan `priority_level`.
- **FR-15** — User melihat rekomendasi relevan berdasarkan `predicted_class` hasil scan-nya, dicocokkan ke `skin_concerns.ml_label` (bukan `name`, yang bisa berubah sewaktu-waktu).

### 8.5 Notifikasi

- **FR-16** — Broadcast event via Reverb untuk event penting: status verifikasi dokter selesai direview, hasil scan selesai, pesan chat baru, langganan aktif.
- **FR-16a** — Notifikasi tersimpan di database (Notification Center in-app): user dapat melihat list notifikasi (`GET /notifications`), jumlah belum dibaca (`GET /notifications/unread-count`), menandai satu/semua terbaca, dan menghapus notifikasi. Setiap notifikasi punya `type` (warna/icon), `category`, dan `action_url` (deep-link). Semua notifikasi dibuat terpusat lewat satu service layer.
- **FR-29** — Device mobile mendaftarkan token FCM (`POST /device-tokens`) untuk menerima push notification saat aplikasi di-background, terpisah dari koneksi real-time Reverb yang hanya aktif saat aplikasi foreground.
- **FR-29a** — User dapat melihat dan menghapus token perangkat terdaftar (`GET`/`DELETE /device-tokens/{id}`).

### 8.6 Observability & Admin

- **FR-17** — Telescope aktif di environment local/staging untuk debugging request, query, dan job.
- **FR-30** — Error & exception di production ditangkap otomatis lewat Sentry, terpisah dari Telescope yang khusus dipakai untuk debugging di local/staging.
- **FR-18** — Admin dapat mengakses Activity Log untuk audit.

### 8.7 Lupa Password (OTP)

- **FR-19** — User dapat meminta kode OTP reset password (`POST /forgot-password`); OTP 6 digit dikirim ke email, disimpan di cache (Redis, di-hash) dengan TTL 10 menit.
- **FR-20** — User memverifikasi OTP sekaligus mengganti password dalam satu request (`POST /reset-password`); OTP bersifat one-time-use, langsung invalid setelah dipakai atau setelah 5 kali percobaan salah.
- **FR-21** — Setelah reset password berhasil, seluruh token Sanctum aktif milik user di-revoke.

### 8.8 Chat dengan Dokter

- **FR-22** — User dapat memulai percakapan dengan dokter terverifikasi (`POST /conversations`); satu thread per pasangan user-dokter.
- **FR-22a** — User dapat melihat daftar dokter terverifikasi (`GET /doctors`, info dasar: nama + gelar, spesialisasi) untuk memilih dokter yang akan di-chat.
- **FR-23** — User & dokter dapat mengirim pesan dalam percakapan (`POST /conversations/{id}/messages`); isi pesan dienkripsi at-rest, bukan end-to-end (server tetap bisa memproses/memoderasi konten kalau diperlukan).
- **FR-23a** — Pesan chat bersifat kaya: teks (max 5000 karakter), foto, video, atau **lampiran hasil scan** (user memilih riwayat scan miliknya; isi pesan otomatis dirangkum dari hasil prediksi). Media di-strip EXIF sebelum disimpan.
- **FR-23b** — Konten pesan difilter kata-kata tidak pantas (bad-word list di config); pesan yang mengandung kata terlarang ditolak (422) — moderasi konten platform kesehatan.
- **FR-24** — User mendapat **3 pesan gratis lintas semua dokter** (kuota global di `users.user_messages_count`, bukan per percakapan); pesan berikutnya dari user diblokir (`402 Payment Required`) sampai user punya subscription aktif; balasan dokter tidak dibatasi. Kirim pesan chat juga membutuhkan email terverifikasi (403).
- **FR-25** — User dapat checkout paket Pro (monthly, Rp15.000/bulan) lewat Midtrans Snap (`POST /subscriptions/checkout`); status subscription diperbarui otomatis lewat webhook Midtrans (`POST /webhooks/midtrans`), `ends_at` diset `starts_at + 30 hari` begitu pembayaran sukses; checkout membutuhkan email terverifikasi.
- **FR-25a** — Webhook Midtrans bersifat idempotent: notifikasi yang sama (ditandai `midtrans_order_id` + status) yang diterima berkali-kali (retry dari Midtrans) tidak mengubah state subscription lebih dari sekali.
- **FR-26** — Riwayat pesan yang sudah terkirim tetap bisa dibaca meski subscription habis; paywall hanya menahan pesan baru. User yang sedang offline (tidak aktif >5 menit) menerima email notifikasi pesan baru.
- **FR-39** — Profil dokter lengkap hanya tampil untuk dokter berstatus `approved`: Identitas & Gelar (`full_name` + `title`), Spesialisasi & Subspesialisasi, Nomor STR, Pengalaman Praktik, Asal Institusi/Almamater, Rumah Sakit/Lokasi Praktik offline, dan Organisasi Profesi (`GET /doctors/{id}`); dokter yang belum approved tidak muncul di daftar maupun profil.
- **FR-49** — User dapat memberi rating (1–5) & review opsional kepada dokter yang pernah diajak chat (`POST /doctors/{id}/ratings`); satu rating per dokter per user, bisa diperbarui (`updateOrCreate`); rating & jumlah rata-rata tampil publik di daftar & profil dokter, dan di dashboard dokter.

### 8.11 AI Chat "Aura Skin"

- **FR-41** — User dapat mengobrol dengan **Aura Skin**, asisten AI edukasi skincare berbasis Google Gemini. Bot diimplementasikan sebagai akun user role `doctor` dengan flag `ai_bot = true` + verifikasi approved otomatis (di-seed), tampil **pin teratas** di daftar dokter. Obrolan berjalan di thread conversation yang sama dengan chat dokter.
- **FR-41a** — Sebelum dapat memakai AI chat, user wajib menyetujui consent penggunaan AI (tersimpan terversi: `consent_version`, `accepted_at`, `ip_address` di `ai_chat_consents`); consent dapat ditarik kapan saja (semua riwayat consent dihapus), namun riwayat percakapan tetap tersimpan.
- **FR-41b** — User gratis dibatasi **10 pesan AI per hari** (429, dihitung per tanggal via cache); user Pro tidak dibatasi. Balasan AI dibuat lewat queue job (asynchronous) setelah pesan user tersimpan.
- **FR-41c** — Safety layer: pertanyaan yang mengandung kata kunci berisiko (kondisi medis darurat, dsb. — `unsafe_keywords` di config) tidak diteruskan ke Gemini dan dijawab dengan template eskalasi ke dokter manusia; error dari provider AI di-catch dan dijawab dengan template fallback agar UX tidak putus.

### 8.12 Email Verification

- **FR-42** — User dapat meminta kode OTP verifikasi email 6 digit (`POST /email/verify/send`); OTP di-hash, disimpan di cache dengan TTL 10 menit, dan maksimal 5 percobaan salah sebelum kode invalid.
- **FR-42a** — User memverifikasi email dengan OTP (`POST /email/verify`); setelah sukses, `email_verified_at` dicatat dan semua prasyarat fitur (scan FR-44, chat FR-24/23, checkout FR-25) terpenuhi.

### 8.13 Dashboard & Statistik

- **FR-50** — Admin dapat melihat dashboard statistik (`GET /admin/dashboard`): total user, total dokter, user baru minggu ini, total scan, scan hari ini, subscription Pro aktif, revenue bulanan, chart scan & registrasi 14 hari terakhir, jumlah verifikasi pending, dan 5 verifikasi terbaru.
- **FR-50a** — Dokter dapat melihat dashboard statistik (`GET /doctor/dashboard`): status verifikasi, total pasien unik, percakapan menunggu balasan, jumlah produk & rekomendasi, rating rata-rata & total, dan 5 percakapan terbaru.

### 8.14 Manajemen User oleh Admin

- **FR-2a** — Admin dapat membuat user baru (`POST /admin/users`) lengkap dengan role & status aktif; email harus unik (termasuk terhadap soft-deleted).
- **FR-2b** — Admin dapat memperbarui data user apa pun (`PATCH /admin/users/{id}`): nama, email, password, status aktif, DOB, gender.
- **FR-2c** — Admin dapat menghapus user (`DELETE /admin/users/{id}`) — soft delete + revoke seluruh token sesi; tidak dapat menghapus akun sendiri (422). Semua aksi manajemen user tercatat di Activity Log.

### 8.15 Export Data & Emergency

- **FR-51** — User dapat meminta export seluruh data akunnya (`POST /profile/export`) — profil, langganan, riwayat scan, verifikasi dokter, consent AI, jumlah pesan — sebagai file JSON dengan download URL bersigned (kedaluwarsa 30 menit), file lama otomatis dihapus (hak akses data UU PDP).
- **FR-53** — Publik dapat melihat daftar hotline darurat kesehatan kulit (`GET /emergency`) tanpa login — konten dari config, untuk kasus kondisi kulit yang membutuhkan penanganan segera.

### 8.9 Kesiapan Ganti Model ML

- **FR-27** — Pemanggilan ke layanan ML diabstraksi lewat interface (`SkinPredictionServiceContract`), bukan dipanggil langsung dari controller — memudahkan penggantian model (mis. ke deep learning) tanpa mengubah `ScanController`.
- **FR-28** — Model aktif dipilih lewat konfigurasi (`ML_DRIVER` di `.env`), bukan hardcode; response mentah tiap model tetap disimpan di `raw_response` supaya skema tidak terikat ke satu model tertentu.

### 8.10 Profil User

- **FR-31** — User dapat melihat profilnya sendiri (`GET /profile`); field yang dikembalikan menyesuaikan role — user (ringkasan riwayat scan, status langganan, sisa kuota chat), doctor (status verifikasi & jumlah produk/rekomendasi), admin (jumlah pengajuan dokter yang pending).
- **FR-32** — User dapat memperbarui data profil dasar: nama, avatar, password, tanggal lahir, jenis kelamin (`PATCH /profile`); ganti password sendiri butuh password lama (`POST /profile/change-password`).
- **FR-33** — User dapat mengajukan penghapusan akun (`DELETE /profile`) sesuai hak _right to erasure_ — akun & data terkait di-soft-delete lebih dulu dengan masa tenggang, baru dihapus permanen setelahnya (lihat bagian 13).
- **FR-46** — User dapat mengganti foto profil maksimal **1x per 24 jam** (`avatar_updated_at` sebagai penanda rate-limit); foto di-strip metadata EXIF; avatar dapat dihapus kapan saja (kembali ke default/Google avatar).
- **FR-34** — Profil user (role `user`) menampilkan status langganan (Free/Pro), dihitung dari baris `subscriptions` dengan `status = active` & belum kedaluwarsa — bukan disimpan sebagai field terpisah.
- **FR-35** — User dapat melihat riwayat langganan (`GET /subscriptions`); tiap pembelian tercatat sebagai baris baru (append-only log), lengkap dengan paket, masa berlaku, dan tanggal mulai.
- **FR-36** — User dapat melihat bukti pembayaran / receipt satu transaksi langganan (`GET /subscriptions/{id}/receipt`), hanya untuk transaksi berstatus `active`.
- **FR-52** — User dapat membatalkan langganan aktifnya sendiri (`POST /subscriptions/{id}/cancel`) — status berubah `cancelled`; scheduled job harian `subscriptions:expire` menandai subscription lewat `ends_at` sebagai `expired`.
- **FR-37** — User dapat melihat daftar sesi login aktif ("Login Activity") beserta perkiraan device & lokasi (resolusi IP via ip-api.com, di-cache), dan mana yang merupakan sesi saat ini (`GET /login-activity`).
- **FR-38** — User dapat logout dari satu sesi spesifik (`DELETE /login-activity/{id}`), terpisah dari `logout-all` (FR-1c) yang me-revoke seluruh sesi sekaligus.

## 9. Kontrak API (ringkasan)

Seluruh endpoint diprefix `/api/v1/` (URI versioning) — lihat bagian 13 untuk alasannya. Seluruh path parameter `{id}` di bawah ini merujuk ke kolom `uuid` publik, bukan `id` internal. Rate limiter: `api` (umum), `auth` (login/register), `scans`, `forgot-password`, `reset-password`, `google-auth`.

### 9.1 Publik (tanpa auth)

| Method | Endpoint | Deskripsi |
| ------ | -------- | --------- |
| GET | `/api/v1` | Info API (nama, versi, status) |
| POST | `/api/v1/register` | Registrasi user (FR-1, wajib `privacy_consent`) |
| POST | `/api/v1/register-doctor` | Registrasi dokter + pengajuan verifikasi + upload dokumen (FR-8) |
| POST | `/api/v1/login` | Login, mengembalikan token Sanctum (FR-1) |
| POST | `/api/v1/auth/google` | Login/register via Google ID token (FR-1a) |
| POST | `/api/v1/forgot-password` | Minta kode OTP reset password (FR-19) |
| POST | `/api/v1/reset-password` | Verifikasi OTP & ganti password sekaligus (FR-20) |
| GET | `/api/v1/emergency` | Hotline darurat (FR-53) |
| GET/POST | `/api/v1/webhooks/midtrans` | Callback status pembayaran Midtrans (FR-25, verifikasi signature) |
| GET | `/api/v1/skincare-products` | Katalog produk (filter skin type/concern/gender, sort, pagination) |
| GET | `/api/v1/skincare-products/{id}` | Detail produk |
| GET | `/api/v1/skin-recommendations` | List rekomendasi skincare |
| GET | `/api/v1/skin-recommendations/{id}` | Detail rekomendasi |
| GET | `/api/v1/skin-concerns` | List skin concerns |
| GET | `/api/v1/skin-concerns/{id}` | Detail skin concern |
| GET | `/api/v1/skin-types` | List skin types |
| GET | `/api/v1/skin-types/{id}` | Detail skin type |

### 9.2 Authenticated (semua role, `auth:sanctum`)

| Method | Endpoint | Deskripsi |
| ------ | -------- | --------- |
| POST | `/api/v1/logout` | Revoke token device yang aktif (FR-1c) |
| POST | `/api/v1/logout-all` | Revoke seluruh token aktif milik user (FR-1c) |
| POST | `/api/v1/email/verify/send` | Kirim OTP verifikasi email (FR-42) |
| POST | `/api/v1/email/verify` | Verifikasi email dengan OTP (FR-42a) |
| GET | `/api/v1/profile` | Lihat profil sendiri, field menyesuaikan role (FR-31) |
| PATCH | `/api/v1/profile` | Update nama/avatar/password/DOB/gender (FR-32, FR-46) |
| DELETE | `/api/v1/profile` | Ajukan penghapusan akun (FR-33) |
| POST | `/api/v1/profile/change-password` | Ganti password (dengan password lama) |
| DELETE | `/api/v1/profile/avatar` | Hapus foto profil (FR-46) |
| POST | `/api/v1/profile/export` | Minta export data akun → signed download URL (FR-51) |
| GET | `/api/v1/profile/exports/download` | Download file export (signed URL, 30 menit) |
| GET | `/api/v1/notifications` | List notifikasi in-app (FR-16a) |
| GET | `/api/v1/notifications/unread-count` | Jumlah notifikasi belum dibaca (FR-16a) |
| POST | `/api/v1/notifications/read-all` | Tandai semua notifikasi terbaca (FR-16a) |
| POST | `/api/v1/notifications/{id}/read` | Tandai satu notifikasi terbaca (FR-16a) |
| DELETE | `/api/v1/notifications/{id}` | Hapus notifikasi (FR-16a) |
| GET | `/api/v1/ai-chat/consent` | Status consent AI + teks consent (FR-41a) |
| POST | `/api/v1/ai-chat/consent` | Setuju/cabut consent AI (FR-41a) |
| POST | `/api/v1/ai-chat/conversations` | Mulai percakapan dengan Aura Skin (FR-41) |
| DELETE | `/api/v1/ai-chat/conversations/{id}` | Hapus riwayat percakapan AI (FR-41) |
| GET | `/api/v1/login-activity` | List sesi login aktif (FR-37) |
| DELETE | `/api/v1/login-activity/{id}` | Logout satu sesi spesifik (FR-38) |
| GET | `/api/v1/doctors` | List dokter terverifikasi, Aura Skin pin teratas (FR-22a) |
| GET | `/api/v1/doctors/{id}` | Profil lengkap dokter terverifikasi (FR-39) |
| POST | `/api/v1/doctors/{id}/ratings` | Beri/perbarui rating dokter (FR-49) |
| GET | `/api/v1/doctors/{id}/ratings` | List rating & review dokter (FR-49) |
| GET | `/api/v1/subscriptions` | Riwayat langganan, append-only log (FR-35) |
| POST | `/api/v1/subscriptions/checkout` | Checkout Pro monthly via Midtrans Snap, return `snap_token` (FR-25) |
| GET | `/api/v1/subscriptions/{id}/receipt` | Bukti pembayaran / receipt (FR-36) |
| POST | `/api/v1/subscriptions/{id}/cancel` | Batalkan langganan aktif (FR-52) |
| GET | `/api/v1/scans` | List riwayat scan milik user (filter/sort) (FR-7) |
| POST | `/api/v1/scans` | Upload foto penuh → forward ke `/predict` (FR-4, FR-44, FR-45) |
| POST | `/api/v1/scans/livecam` | Kirim gambar ter-crop → forward ke `/predict-crop` (FR-5) |
| GET | `/api/v1/scans/{id}` | Detail satu hasil scan |
| POST | `/api/v1/scans/{id}/feedback` | Feedback akurasi hasil scan (FR-48) |
| GET | `/api/v1/device-tokens` | List token perangkat (FR-29a) |
| POST | `/api/v1/device-tokens` | Daftarkan token FCM (FR-29) |
| DELETE | `/api/v1/device-tokens/{id}` | Hapus token perangkat (FR-29a) |
| POST | `/api/v1/conversations` | Mulai percakapan dengan dokter (FR-22) |
| GET | `/api/v1/conversations` | List percakapan milik user/dokter yang login |
| GET | `/api/v1/conversations/{id}/messages` | Riwayat pesan (paginated) |
| POST | `/api/v1/conversations/{id}/messages` | Kirim pesan (teks/media/hasil scan) — paywall untuk sender user (FR-23, FR-24) |

### 9.3 Role Admin (`role:admin`)

| Method | Endpoint | Deskripsi |
| ------ | -------- | --------- |
| GET | `/api/v1/admin/dashboard` | Statistik platform + chart 14 hari (FR-50) |
| GET | `/api/v1/admin/profile` | Profil admin + sesi + ringkasan |
| GET | `/api/v1/admin/users` | List semua user (filter `?role=`) |
| POST | `/api/v1/admin/users` | Buat user baru (FR-2a) |
| GET | `/api/v1/admin/users/{id}` | Detail user + verifikasi dokternya |
| PATCH | `/api/v1/admin/users/{id}` | Update data user (FR-2b) |
| DELETE | `/api/v1/admin/users/{id}` | Hapus user — soft delete + revoke token (FR-2c) |
| PATCH | `/api/v1/admin/users/{id}/role` | Ubah role user (FR-2) |
| PATCH | `/api/v1/admin/users/{id}/toggle-active` | Suspend / aktifkan user |
| GET | `/api/v1/admin/activity-log` | Audit log (filter `?log_name=`, `?causer_id=`) (FR-18) |
| GET | `/api/v1/admin/verifications` | List pengajuan verifikasi dokter (filter status) |
| GET | `/api/v1/admin/verifications/{id}` | Detail pengajuan verifikasi + dokumen |
| PATCH | `/api/v1/doctor-verifications/{id}/review` | Approve/reject/minta revisi (FR-9) |
| GET | `/api/v1/admin/skincare-products` | List semua produk (admin view) |

### 9.4 Role Doctor (`role:doctor`)

| Method | Endpoint | Deskripsi |
| ------ | -------- | --------- |
| GET | `/api/v1/doctor/dashboard` | Statistik dokter + recent chats (FR-50a) |
| GET | `/api/v1/doctor/products` | List produk milik sendiri |
| POST | `/api/v1/skincare-products` | Tambah produk (FR-13) |
| PATCH | `/api/v1/skincare-products/{id}` | Update produk sendiri |
| DELETE | `/api/v1/skincare-products/{id}` | Hapus produk sendiri |
| GET | `/api/v1/doctor/recommendations` | List rekomendasi milik sendiri |
| POST | `/api/v1/skin-recommendations` | Tambah rekomendasi (FR-14) |
| PATCH | `/api/v1/skin-recommendations/{id}` | Update rekomendasi sendiri |
| DELETE | `/api/v1/skin-recommendations/{id}` | Hapus rekomendasi sendiri |
| PATCH | `/api/v1/skin-concerns/{id}` | Update skin concern |
| POST | `/api/v1/skin-types` | Tambah skin type (FR-12) |
| PATCH | `/api/v1/skin-types/{id}` | Update skin type |
| DELETE | `/api/v1/skin-types/{id}` | Hapus skin type |
| GET | `/api/v1/doctor-verifications` | Status verifikasi sendiri |
| POST | `/api/v1/doctor-verifications` | Ajukan verifikasi (FR-8) |
| POST | `/api/v1/doctor-verifications/{id}/resubmit` | Ajukan ulang setelah `needs_revision` (FR-9a) |

## 10. Integrasi ML Service (HuggingFace Space)

Base URL: `https://akazelll-face-skin-predict.hf.space`

| Endpoint             | Dipanggil saat                                         | Catatan                                                                      |
| -------------------- | ------------------------------------------------------ | ---------------------------------------------------------------------------- |
| `POST /predict`      | User upload foto penuh                                 | Space melakukan deteksi & crop wajah otomatis pakai YOLOv8n sebelum prediksi |
| `POST /predict-crop` | User scan via livecam (sudah di-crop di FE)            | Langsung diproses tanpa YOLO                                                 |
| `GET /health`        | Health check (opsional, untuk monitoring dari Laravel) | Mengembalikan status model & daftar kelas                                    |

Response schema (sama untuk kedua endpoint prediksi):

```json
{
  "predicted_class": "string",
  "confidence": 0.0,
  "probabilities": { "nama_kelas": 0.0 },
  "severity_score": 0.0,
  "severity_level": "low|medium|high",
  "model_used": "SVM_RBF_v4"
}
```

Referensi kelas & severity dari ML service (dipakai untuk seed `skin_concerns.ml_label`):

| Kelas (ml_label)                  | Severity score (skala ML, 0–1) | Level  |
| --------------------------------- | ------------------------------ | ------ |
| inflammatory acne                 | 0.85                           | high   |
| non inflammatory acne black heads | 0.50                           | medium |
| non inflammatory acne white heads | 0.50                           | medium |
| Redness                           | 0.60                           | medium |
| dark spots                        | 0.40                           | low    |
| pigmentation                      | 0.40                           | low    |
| pores                             | 0.30                           | low    |
| wrinkles                          | 0.30                           | low    |

## 11. Non-Functional Requirements

- **Keamanan** — token Sanctum tanpa auto-expiry (sesi aktif sampai logout manual, keputusan produk — lihat FR-1b), diimbangi dengan endpoint `logout-all` sebagai jaring pengaman kalau akun dicurigai diakses pihak lain; validasi tipe & ukuran file upload; bucket storage private dengan signed URL untuk foto & dokumen; `id` internal tidak pernah di-expose ke API (lihat bagian 13).
- **Rate limiting** — throttle default (mis. 60 req/menit) berlaku untuk seluruh `/api/v1/*` lewat named rate limiter Laravel, ditambah limiter lebih ketat khusus untuk endpoint rawan abuse: `login`/`register` (mencegah brute-force/credential stuffing), `forgot-password` (3x/15 menit per email), percobaan `reset-password` (5x per kode OTP), dan `scans` (karena memanggil layanan ML eksternal yang berbiaya).
- **Quota fitur (freemium)** — di luar rate limiting per-request, ada quota per-fitur yang memisahkan user Free vs Pro: 3 scan/hari, 3 pesan chat ke dokter (global, sekali seumur akun), 10 pesan AI/hari. Penolakan quota memakai status 429/402 dengan pesan yang mengarahkan ke upgrade.
- **Gate email terverifikasi** — fitur berisiko (scan, kirim pesan chat, checkout Midtrans) menolak user yang belum verifikasi email (403) — mitigasi akun spam/abuse terhadap layanan eksternal berbiaya (ML service, payment gateway).
- **Privasi media** — semua foto yang diunggah user (scan, avatar, chat) di-strip metadata EXIF (GPS, info kamera) sebelum disimpan; foto scan otomatis dihapus dari storage setelah masa retenti (DB row dipertahankan).
- **Konsistensi response** — seluruh endpoint mengembalikan response lewat Laravel API Resource (bukan raw Model/Collection), dengan format konsisten (`{ "data": ..., "meta": ... }` untuk sukses); penting karena API ini dikonsumsi dua client berbeda (Next.js & Flutter) yang butuh kontrak yang predictable.
- **Performa** — HuggingFace Space free tier berpotensi cold-start lambat; perlu timeout yang wajar dan penanganan UI di FE, atau pertimbangkan queue job untuk pemrosesan async. FK berbasis `bigint` (bukan `uuid`) menjaga efisiensi join & ukuran index saat volume data bertambah besar.
- **Skalabilitas** — pisahkan pemanggilan ke ML service lewat queue job bila volume scan meningkat; strategi PK hybrid (bagian 13) dipilih spesifik untuk mengantisipasi pertumbuhan ke skala enterprise.
- **Observability** — Telescope untuk debugging di local/staging; Sentry untuk error tracking otomatis di production (FR-30); logging terstruktur.
- **Reliabilitas** — retry policy saat panggilan ke ML service gagal atau timeout; webhook Midtrans idempotent (FR-25a).
- **Testing & CI** — feature test wajib untuk jalur berisiko tinggi: autentikasi, logic paywall (FR-24), webhook payment, dan AI chat safety; dijalankan otomatis lewat CI (GitHub Actions) di setiap push.
- **Backup & disaster recovery** — backup terjadwal database MySQL (mis. `mysqldump` harian, disimpan ke Cloudflare R2) dengan retention yang jelas; proses restore diuji berkala, bukan cuma diasumsikan jalan. Media di R2 sudah punya redundansi bawaan dari provider.
- **Kepatuhan UU PDP** — consent eksplisit dicatat saat registrasi (FR-1d); user punya hak mengajukan penghapusan akun & datanya (FR-33, _right to erasure_). Ini langkah teknis awal, bukan pengganti kajian hukum penuh — rekomendasi konsultasi dengan pihak legal untuk kepatuhan menyeluruh (kewajiban notifikasi breach, dsb).

## 12. Tech Stack

| Layer                       | Teknologi                                                                                                                               |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Backend                     | Laravel 13+ (API-only), PHP 8.2+                                                                                                        |
| Auth                        | Laravel Sanctum                                                                                                                         |
| Google Sign-In              | `google/apiclient` (verifikasi ID token dari Google Identity Services)                                                                  |
| Email (production)          | Amazon SES (mail driver `ses`)                                                                                                          |
| Email (development)         | Mailtrap (sandbox SMTP)                                                                                                                 |
| Cache / OTP storage         | Redis                                                                                                                                   |
| Payment gateway             | Midtrans Snap (`midtrans/midtrans-php`), mode sandbox untuk testing                                                                     |
| ML integration              | Adapter pattern (`SkinPredictionServiceContract`), driver dipilih via `.env`                                                            |
| AI chat (Aura Skin)          | Google Gemini via adapter pattern (`AiChatServiceContract` → `AiChatService` safety layer → `GeminiAiProvider`) + queue job async reply |
| IP geolocation              | ip-api.com (adapter `IpLocationResolverContract`, di-cache 24 jam)                                                                       |
| Mobile client               | Flutter — Sanctum token auth, Reverb via `pusher_channels_flutter` (kompatibel protokol Pusher), FCM untuk push notification background |
| Error tracking (production) | Sentry (`sentry/sentry-laravel`)                                                                                                        |
| CI                          | GitHub Actions — `php artisan test` di setiap push                                                                                      |
| Backup                      | Scheduled `mysqldump` → Cloudflare R2                                                                                                   |
| Realtime                    | Laravel Reverb                                                                                                                          |
| Role & permission           | Spatie Laravel-Permission                                                                                                               |
| File & media                | Spatie Media Library                                                                                                                    |
| Audit trail                 | Spatie Activity Log                                                                                                                     |
| Filter/sort API             | Spatie Query Builder                                                                                                                    |
| Debugging                   | Laravel Telescope (local/staging)                                                                                                       |
| ML service                  | FastAPI (Python) di HuggingFace Spaces — YOLOv8n + SVM (scikit-learn)                                                                   |
| Frontend                    | Next.js (existing, TypeScript)                                                                                                          |
| Database                    | MySQL 8+                                                                                                                                |
| Storage (production)        | Cloudflare R2 (S3-compatible), private bucket + signed URL                                                                              |

## 13. Keputusan Teknis Tambahan

- **Strategi primary key: hybrid bigint + uuid publik.** Setiap tabel memakai `id` (`BIGINT` auto-increment) sebagai PK asli untuk foreign key & clustered index — hemat storage (8 byte vs 36 byte per referensi) dan performa join lebih baik saat volume data besar (pertimbangan utama untuk target skala enterprise). Kolom `uuid` terpisah (unique, indexed) menjadi satu-satunya identifier yang di-expose ke API/URL, dipakai sebagai route key, dan digenerate otomatis saat create — mencegah ID publik ditebak/di-enumerasi (mis. `/api/v1/scans/1`, `/api/v1/scans/2`, dst). Kolom `id` internal disembunyikan total dari response JSON.
- **Enum status/level** — tidak memakai tipe kolom `ENUM` MySQL (menambah value butuh `ALTER TABLE`). Semua kolom status/level (`verification_status`, `priority_level`, `severity_level`, `scan_mode`) disimpan sebagai `VARCHAR` dan divalidasi lewat PHP backed enum + Eloquent cast — type-safe di kode, fleksibel di database.
- **Konversi severity_score** — dilakukan di layer Laravel saat ingest response ML (0–1 × 100 → integer 0–100). Response mentah tetap disimpan di kolom `raw_response` (json) pada `prediction_histories` untuk keperluan debugging.
- **State doctor_verification_status** — ditambah state ke-4: `needs_revision`, terpisah dari `rejected`. Alur: `pending` → `approved` / `rejected` / `needs_revision`; dari `needs_revision` dokter bisa mengajukan ulang dan status kembali ke `pending`.
- **Mapping predicted_class ↔ skin_concerns** — ditambah kolom `ml_label` (varchar, unique) di `skin_concerns`, diisi persis sesuai label dari ML service, terpisah dari `name` yang ditampilkan ke user dan bisa diubah admin kapan saja. Di-seed saat migration awal.
- **Storage foto** — production memakai Cloudflare R2 (S3-compatible, driver `s3` di Spatie Media Library) dengan private bucket + signed/temporary URL (`Media::getTemporaryUrl()`), karena foto wajah & hasil analisis kulit tergolong data personal. Development tetap memakai disk lokal.

- **Autentikasi Google** — pakai verifikasi ID token langsung lewat package `google/apiclient` (`Google_Client::verifyIdToken()`), **bukan** flow OAuth redirect penuh (Laravel Socialite). Alasan: frontend Next.js sudah menangani UI & pertukaran token lewat Google Identity Services di sisi client, sehingga backend cukup memverifikasi token — bukan mengatur redirect lintas origin antara SPA dan API. Pola verifikasi token yang sama juga bisa dipakai ulang untuk client mobile (Android/iOS) yang menghasilkan ID token serupa.

- **Reset password via OTP** — kode 6 digit disimpan di Redis cache (bukan tabel DB) dengan TTL 10 menit dan di-hash sebelum disimpan. `forgot-password` dan `reset-password` masing-masing punya rate limit terpisah (request OTP vs percobaan verifikasi) untuk mencegah spam & brute-force. Response `forgot-password` selalu generic (tidak mengonfirmasi apakah email terdaftar) untuk mencegah email enumeration. OTP one-time-use dan langsung invalid setelah dipakai; seluruh token Sanctum di-revoke setelah reset berhasil, untuk memutus sesi yang mungkin sudah diambil alih pihak lain.

- **Chat dokter — tanpa E2EE (disederhanakan dari draft awal).** Isi pesan disimpan pakai Eloquent `encrypted` cast (AES-256 via `APP_KEY`) — melindungi data at-rest kalau database bocor, tapi server tetap bisa memproses/memoderasi konten kalau diperlukan (relevan untuk platform kesehatan). Trade-off yang disengaja: bukan E2EE murni, tapi jauh lebih simpel dari sisi key management & multi-device dibanding opsi E2EE yang sempat dipertimbangkan.
- **Paywall chat** — limit 3 pesan gratis dihitung **global** per user (`users.user_messages_count`, FR-24), hanya pesan dari user yang kehitung; balasan dokter tidak dibatasi. Riwayat pesan tetap bisa dibaca setelah subscription habis, hanya pesan baru yang ditahan. Pemeriksaan kuota dilakukan di dalam database transaction dengan row lock untuk mencegah race condition.
- **Moderasi konten chat** — filter kata-kata tidak pantas sederhana (list di `config/chat.php`) dilakukan sebelum pesan disimpan (422 jika terdeteksi). Server tetap dapat membaca isi pesan (bukan E2EE) — konsisten dengan kebutuhan moderasi platform kesehatan.
- **Payment gateway** — Midtrans Snap, mulai dari environment sandbox untuk testing sebelum go production.
- **Kesiapan ganti model ML** — pemanggilan ke layanan ML dibungkus lewat interface (Adapter/Strategy pattern), bukan dipanggil langsung dari controller. Implementasi model aktif (saat ini: FastAPI + YOLOv8 + SVM) dipilih lewat konfigurasi, bukan hardcode — kalau nanti pindah ke model deep learning atau layanan lain, cukup tambah adapter baru dan ganti config, tanpa mengubah controller/route. Kolom `raw_response` dipertahankan supaya skema data tidak terikat ke satu model tertentu.
- **Kesiapan mobile (Flutter)** — seluruh API dirancang API-only sejak awal sehingga otomatis mobile-ready (token Sanctum, tanpa CORS untuk native client). Reverb kompatibel protokol Pusher sehingga bisa dipakai langsung dari Flutter; push notification saat aplikasi di-background memakai FCM terpisah dari Reverb (yang hanya aktif saat koneksi WebSocket terbuka).

- **AI Chat "Aura Skin" — bot sebagai akun dokter, bukan service terpisah.** Bot AI diimplementasikan sebagai akun user role `doctor` dengan flag `ai_bot = true` + baris verifikasi approved otomatis (di-seed). Manfaat: AI otomatis muncul di daftar dokter (pin teratas via `orderByRaw('ai_bot DESC')`), memakai infrastruktur conversation/message yang sudah ada, dan dashboard/statistik tidak perlu dikhususkan. Balasan AI dibuat lewat queue job `GenerateAiReply` (asynchronous) agar request user tidak menunggu response Gemini.
- **AI provider diabstraksi lewat contract** (`AiChatServiceContract` → `AiChatService` safety layer → `GeminiAiProvider`), dipilih via config — konsisten dengan strategi adapter ML service; ganti provider AI (mis. OpenAI) cukup ganti binding di provider tanpa menyentuh controller.
- **Consent AI terversi & dapat dicabut** — jejak consent (`ai_chat_consents`) menyimpan versi teks + IP + waktu, sesuai praktik UU PDP untuk pemrosesan data kesehatan oleh AI. Cabut consent menghapus baris consent namun TIDAK menghapus riwayat percakapan (kewajiban penyimpanan vs hak hapus — percakapan bisa dihapus user lewat endpoint AI chat).
- **Notification Service terpusat** — seluruh notifikasi dibuat lewat satu factory service (bukan `Notification::create` tersebar di controller), menjamin format, kategori, dan broadcast event konsisten; broadcast event Reverb bersifat synchronous (`ShouldBroadcastNow`) agar notifikasi tiba seketika tanpa worker queue terpisah.
- **Scheduled jobs (harian, via scheduler Laravel)** — `backup:database` (02:00, dump MySQL → gzip → disk backup + retention), `users:prune` (03:00, hapus permanen user yang soft-delete melewati masa tenggang 30 hari + purge media/chat/subscription/export terkait — implementasi teknis FR-33), `scan-photos:purge` (04:00, hapus file foto scan melewati retensi `SCAN_PHOTO_RETENTION_DAYS`), `subscriptions:expire` (05:00, tandai subscription lewat `ends_at` sebagai `expired`), `notifications:clean` (06:00, hapus notifikasi lama). Semua `withoutOverlapping()`.
- **IP geolocation Login Activity** — resolusi kota/negara via ip-api.com (gratis, 45 req/menit), di-cache 24 jam per IP, IP privat/reserved (mis. localhost) tidak diresolusi. Driver resolver di-bind lewat contract agar mudah diganti (mis. MaxMind) tanpa menyentuh controller.
- **Export data akun sebagai JSON + signed URL** — file JSON di-generate on-demand ke disk local, download via temporary signed route (30 menit), file lama otomatis dihapus saat export baru dibuat, dan file dihapus setelah dikirim (`deleteFileAfterSend`) — memenuhi hak akses data UU PDP tanpa mengekspos storage path.
- **Strip EXIF metadata untuk semua foto user** (avatar, scan, chat) — metadata GPS/kamera dihapus sebelum file disimpan untuk mencegah kebocoran informasi lokasi pengguna.
- **Quota harian via cache, bukan kolom DB** — kuota AI chat (10/hari) dan kuota scan (3/hari) dihitung dari cache/prediction_histories dengan kunci per-tanggal, tanpa menambah kolom counter baru; user Pro melewati semua pemeriksaan kuota.

- **API versioning** — seluruh endpoint diprefix `/api/v1/` (URI versioning). Diputuskan sebelum development dimulai karena begitu aplikasi mobile Flutter sudah dipublish ke app store, path API praktis tidak bisa diganti tanpa breaking user yang belum update — jauh lebih murah diterapkan dari awal daripada disisipkan belakangan.
- **Observability: Telescope vs Sentry** — dua tool dengan peran beda, bukan saling gantikan. Telescope untuk debugging mendalam (query, job, request) di local/staging saja — tidak pernah aktif di production karena menampilkan data mentah termasuk yang sensitif. Sentry untuk menangkap error/exception otomatis di production plus alerting, tanpa expose detail internal ke user.
- **Konsistensi response API** — wajib pakai Laravel API Resource class di setiap endpoint, dengan format `{ "data": ..., "meta": ... }` untuk sukses dan format bawaan Laravel (`{ "message": ..., "errors": {...} }`) untuk validation error — krusial karena API ini dikonsumsi dua client berbeda platform (Next.js & Flutter) yang butuh kontrak yang predictable, bukan bentuk response yang beda-beda tiap controller.
- **Idempotency webhook Midtrans** — sebelum memproses notifikasi, cek status subscription saat ini terhadap `midtrans_order_id` di dalam database transaction dengan row lock (`lockForUpdate()`); kalau status yang diminta sudah tercapai, webhook di-skip (tetap dibalas 200 OK ke Midtrans) tanpa mengubah state lagi — mencegah subscription ke-aktivasi dobel akibat retry notifikasi dari Midtrans.
- **Strategi testing & CI** — tidak menargetkan coverage 100% (tidak realistis untuk skala tim saat ini), tapi feature test wajib untuk jalur berisiko tinggi: autentikasi (FR-1 s/d FR-1d), logic paywall chat (FR-24), dan webhook payment (FR-25a). Dijalankan otomatis lewat GitHub Actions di setiap push, sebagai jaring pengaman regresi minimal.
- **Backup & disaster recovery** — backup database MySQL terjadwal (`mysqldump` harian) disimpan ke Cloudflare R2 (provider yang sama dengan storage foto, tidak perlu integrasi baru), dengan retention yang didefinisikan (mis. harian 7-30 hari). Proses restore diuji berkala — backup yang belum pernah dicoba di-restore bukan backup yang bisa diandalkan.
- **Kepatuhan awal UU PDP** — foto wajah & hasil analisis kondisi kulit tergolong data pribadi spesifik (kesehatan/biometrik) di UU PDP. Langkah teknis yang diambil: consent eksplisit dicatat waktunya saat registrasi (`privacy_consent_at`, FR-1d), dan user punya hak mengajukan penghapusan akun & data terkait (`DELETE /profile`, FR-33) lewat soft-delete + masa tenggang sebelum dihapus permanen. Ini bukan pengganti kajian hukum menyeluruh — kewajiban seperti notifikasi breach ke otoritas & penunjukan penanggung jawab perlindungan data sebaiknya dikonsultasikan ke pihak legal.

- **Model paket: monthly, Rp15.000/bulan.** (Revisi v2.0 — sebelumnya lifetime one-time.) Harga dipatok tetap dalam IDR karena Midtrans native-nya transaksi Rupiah. Kolom `period` ditambahkan ke `subscriptions`; `ends_at` diisi `starts_at + 30 hari` saat pembayaran sukses, dan scheduled job harian `subscriptions:expire` menandai baris lewat masa berlaku sebagai `expired`. Tidak ada recurring billing otomatis — user membeli ulang manual via checkout (append-only log menjamin riwayat transaksi utuh).
- **Info benefit paket tidak disimpan di database** — karena baru ada satu varian paket (Pro monthly), teks benefit cukup didefinisikan di `config/plans.php`. Baru dijadikan tabel kalau nanti ada beberapa varian paket yang perlu dikelola dinamis.
- **Login Activity — extend `personal_access_tokens` Sanctum, bukan tabel baru.** Token Sanctum memang representasi satu sesi login, jadi menambah kolom `ip_address` & `user_agent` ke tabel yang sudah ada lebih tepat daripada duplikasi konsep lewat tabel `login_sessions` terpisah. Lokasi yang ditampilkan ke user adalah perkiraan (kota/negara) hasil resolusi IP, bukan lokasi presisi — perlu ditampilkan dengan framing yang jujur soal keterbatasan ini di UI.
- **Receipt sebagai data terstruktur (JSON), bukan PDF.** Generate tampilan/PDF didelegasikan ke client (Next.js pakai `jsPDF`, Flutter pakai package PDF) — backend cukup jadi sumber data akurat lewat `GET /subscriptions/{id}/receipt`, dibatasi hanya untuk transaksi `status = active` (transaksi `pending`/`cancelled` mengembalikan 404, bukan receipt kosong).

## 14. Lampiran

- ML service: https://huggingface.co/spaces/Akazelll/Face-Skin-Predict
- Repo frontend: https://github.com/RadityaRevanto/face-skin-detection

### 14.1 Daftar File Enum (`app/Enums/`)

| File                       | Dipakai di                                                                                                                                                         |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `VerificationStatus.php`   | `doctor_verifications.verification_status`                                                                                                                         |
| `PriorityLevel.php`        | `skin_recommendations.priority_level`                                                                                                                              |
| `SeverityLevel.php`        | `prediction_histories.severity_level`                                                                                                                              |
| `ScanMode.php`             | `prediction_histories.scan_mode`                                                                                                                                   |
| `SubscriptionStatus.php`   | `subscriptions.status`                                                                                                                                             |
| `DevicePlatform.php`       | `device_tokens.platform`                                                                                                                                           |
| `Gender.php`              | `users.gender` (`laki_laki` / `perempuan`) — prasyarat profil lengkap (FR-44)                                                                                      |
| `ProductGender.php`        | `skincare_products` — target gender produk (unisex/laki_laki/perempuan)                                                                                            |
| `NotificationType.php`     | warna & icon notifikasi/broadcast Reverb (FR-16, FR-29): `success` / `warning` / `error` / `info`                                                                  |
| `NotificationCategory.php` | pengelompokan notifikasi: `welcome`, `scan_complete`, `chat_message`, `verification_approved/rejected/revision`, `subscription_active`, `logout`, dll — dipakai `NotificationService` |

### 14.2 Scheduled Jobs (ringkasan operasional)

| Jadwal | Command | Fungsi |
| ------ | ------- | ------ |
| 02:00 | `backup:database` | Backup MySQL → gzip → simpan ke disk backup, pruned sesuai retention |
| 03:00 | `users:prune` | Hapus permanen user soft-delete > 30 hari + purge media, chat, subscription, export |
| 04:00 | `scan-photos:purge` | Hapus file foto scan > retensi hari (DB row dipertahankan, hanya file foto) |
| 05:00 | `subscriptions:expire` | Tandai subscription lewat `ends_at` sebagai `expired` |
| 06:00 | `notifications:clean` | Hapus notifikasi lama sesuai retention |
