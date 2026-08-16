# Product Requirements Document (PRD)

## Face Skin Predict — Migrasi Backend ke Laravel API

|                  |                                                                                                         |
| ---------------- | ------------------------------------------------------------------------------------------------------- |
| **Versi**        | 1.2 (Draft)                                                                                             |
| **Tanggal**      | 29 Juli 2026                                                                                            |
| **Disusun oleh** | Akazell                                                                                                 |
| **Status**       | Draft final — seluruh gap enterprise-readiness sudah ditutup, siap direview sebelum development dimulai |

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

Face Skin Predict adalah aplikasi web yang memungkinkan pengguna memindai foto wajah untuk mendapatkan analisis kondisi kulit (jerawat, kemerahan, flek hitam, pigmentasi, pori-pori, kerutan, dll) menggunakan model machine learning, lengkap dengan riwayat pemindaian dan rekomendasi skincare dari dokter terverifikasi.

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
- Menyediakan fitur chat user-dokter dengan model freemium (3 pesan gratis untuk semua dokter / kuota global, lanjut butuh subscription via Midtrans) lengkap dengan daftar dokter & profil dokter lengkap.
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
- Chat antara user & dokter terverifikasi, dengan model freemium (3 pesan pertama gratis lintas semua dokter)
- Daftar dokter terverifikasi beserta profil lengkap yang bisa dilihat user sebelum memulai chat
- Checkout & aktivasi subscription lewat Midtrans (mode sandbox untuk testing)

### 5.2 Belum termasuk (out of scope — fase ini)

- Migrasi data historis dari Supabase ke database baru (perlu skrip terpisah)
- Retraining atau re-hosting model ML saat ini (tetap memakai HuggingFace Space yang sudah berjalan — lihat bagian 8.9 untuk kesiapan ganti model di masa depan)

## 6. Arsitektur Sistem (high-level)

- **Frontend** — Next.js (web) dan Flutter (mobile), dua-duanya konsumen dari kontrak API yang sama; autentikasi token Sanctum sudah native mobile-friendly tanpa perubahan.
- **Backend** — Laravel 13+, API-only (Sanctum untuk auth, Reverb untuk broadcasting, Spatie stack untuk permission/media/log/query, Telescope untuk debugging).
- **ML Service** — saat ini FastAPI di HuggingFace Space (Docker), memakai YOLOv8n untuk deteksi & crop wajah dan pipeline SVM (ekstraksi fitur luminance/HOG/LBP/GLCM/Gabor → StandardScaler → PCA → SelectKBest → SVM RBF). Dipanggil dari Laravel lewat interface adapter (`SkinPredictionServiceContract`), bukan langsung dari controller — lihat bagian 8.9 & 13 untuk strategi ganti model ke depan.
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

| Field           | Tipe (MySQL)                           | Keterangan                                                                                               |
| --------------- | -------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| id              | bigint unsigned                        | PK internal                                                                                              |
| uuid            | char(36), unique                       | identifier publik                                                                                        |
| conversation_id | bigint unsigned, FK → conversations.id |                                                                                                          |
| sender_id       | bigint unsigned, FK → users.id         |                                                                                                          |
| content         | text                                   | dienkripsi at-rest lewat Eloquent `encrypted` cast (AES-256 via `APP_KEY`) — bukan E2EE, lihat bagian 13 |
| created_at      | timestamp                              | pesan bersifat immutable, tidak ada `updated_at`                                                         |

### 7.10 subscriptions

| Field             | Tipe (MySQL)                   | Keterangan                                                                                                                                                              |
| ----------------- | ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| id                | bigint unsigned                | PK internal                                                                                                                                                             |
| uuid              | char(36), unique               | identifier publik                                                                                                                                                       |
| user_id           | bigint unsigned, FK → users.id |                                                                                                                                                                         |
| plan_code         | varchar                        | saat ini hanya `pro_lifetime` (harga tetap didefinisikan di `config/plans.php`, bukan tabel)                                                                            |
| status            | varchar (PHP enum)             | `pending` / `active` / `expired` / `cancelled`                                                                                                                          |
| amount            | unsigned int                   | nominal dibayar dalam IDR (mis. `15000`) — harga tetap dipatok IDR, bukan hasil konversi live dari USD                                                                  |
| currency          | varchar, default `IDR`         | disiapkan kalau nanti nambah metode pembayaran lain                                                                                                                     |
| midtrans_order_id | varchar, unique, nullable      | referensi transaksi di sisi kita                                                                                                                                        |
| transaction_id    | varchar, nullable              | ID transaksi dari sisi Midtrans                                                                                                                                         |
| payment_method    | varchar, nullable              | channel pembayaran apa adanya dari Midtrans (mis. `gopay`, `bank_transfer`) — sengaja tidak dipaksa jadi PHP enum tertutup karena mengikuti vocabulary sistem eksternal |
| starts_at         | timestamp, nullable            |                                                                                                                                                                         |
| ends_at           | timestamp, nullable            | `null` = lifetime (tidak pernah expired)                                                                                                                                |
| paid_at           | timestamp, nullable            | waktu pembayaran dikonfirmasi Midtrans                                                                                                                                  |
| timestamps        |                                |                                                                                                                                                                         |

> **Append-only log:** setiap pembelian/perpanjangan paket menghasilkan baris baru, bukan meng-update baris yang sudah ada — riwayat langganan (FR-35) otomatis tersedia tanpa tabel terpisah. Status langganan aktif (Free/Pro) di profil (FR-34) dihitung dari baris `status = active` terbaru, bukan disimpan sebagai field terpisah di `users`.

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
| ip_address     | varchar, nullable | dicatat otomatis saat token dibuat (login/register/Google)           |
| user_agent     | varchar, nullable | diparse jadi info device yang manusiawi untuk Login Activity (FR-37) |

> Login Activity (FR-37, FR-38) sengaja tidak dibuatkan tabel baru — token Sanctum memang representasi satu sesi login, jadi cukup migration tambahan untuk menambah 2 kolom di atas ke tabel bawaan Sanctum. Lokasi ditampilkan ke user berdasarkan resolusi `ip_address` (perkiraan kota/negara, **bukan** lokasi presisi — bisa meleset kalau user memakai VPN).

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

- **FR-4** — User upload foto penuh → backend meneruskan ke `POST /predict` di ML service → hasil disimpan ke `prediction_histories`, foto disimpan lewat Media Library.
- **FR-5** — User scan via livecam (gambar sudah ter-crop di FE) → backend meneruskan ke `POST /predict-crop`.
- **FR-6** — Response ML (`predicted_class`, `confidence`, `probabilities`, `severity_score`, `severity_level`, `model_used`) disimpan sesuai skema; `severity_score` dikonversi dari 0–1 ke 0–100, dan response mentah disimpan di `raw_response`.
- **FR-7** — User dapat melihat riwayat scan (list & detail), difilter berdasarkan `scan_mode`/`predicted_class`, diurutkan berdasarkan `created_at` (Spatie Query Builder), diakses lewat `uuid` publik.

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

- **FR-16** — Broadcast event via Reverb untuk status verifikasi dokter selesai direview (dan opsional: hasil scan selesai, jika pemrosesan dibuat async lewat queue).
- **FR-29** — Device mobile mendaftarkan token FCM (`POST /device-tokens`) untuk menerima push notification saat aplikasi di-background, terpisah dari koneksi real-time Reverb yang hanya aktif saat aplikasi foreground.

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
- **FR-24** — User mendapat **3 pesan gratis lintas semua dokter** (kuota global di `users.user_messages_count`, bukan per percakapan); pesan berikutnya dari user diblokir (`402 Payment Required`) sampai user punya subscription aktif; balasan dokter tidak dibatasi.
- **FR-25** — User dapat checkout paket Pro (lifetime, one-time payment Rp15.000) lewat Midtrans Snap (`POST /subscriptions/checkout`); status subscription diperbarui otomatis lewat webhook Midtrans (`POST /webhooks/midtrans`), `ends_at` diset `null` (selamanya) begitu pembayaran sukses.
- **FR-25a** — Webhook Midtrans bersifat idempotent: notifikasi yang sama (ditandai `midtrans_order_id` + status) yang diterima berkali-kali (retry dari Midtrans) tidak mengubah state subscription lebih dari sekali.
- **FR-26** — Riwayat pesan yang sudah terkirim tetap bisa dibaca meski subscription habis; paywall hanya menahan pesan baru.
- **FR-39** — Profil dokter lengkap hanya tampil untuk dokter berstatus `approved`: Identitas & Gelar (`full_name` + `title`), Spesialisasi & Subspesialisasi, Nomor STR, Pengalaman Praktik, Asal Institusi/Almamater, Rumah Sakit/Lokasi Praktik offline, dan Organisasi Profesi (`GET /doctors/{id}`); dokter yang belum approved tidak muncul di daftar maupun profil.

### 8.9 Kesiapan Ganti Model ML

- **FR-27** — Pemanggilan ke layanan ML diabstraksi lewat interface (`SkinPredictionServiceContract`), bukan dipanggil langsung dari controller — memudahkan penggantian model (mis. ke deep learning) tanpa mengubah `ScanController`.
- **FR-28** — Model aktif dipilih lewat konfigurasi (`ML_DRIVER` di `.env`), bukan hardcode; response mentah tiap model tetap disimpan di `raw_response` supaya skema tidak terikat ke satu model tertentu.

### 8.10 Profil User

- **FR-31** — User dapat melihat profilnya sendiri (`GET /profile`); field yang dikembalikan menyesuaikan role — user (ringkasan riwayat scan), doctor (status verifikasi & jumlah produk/rekomendasi), admin (jumlah pengajuan dokter yang pending).
- **FR-32** — User dapat memperbarui data profil dasar: nama, avatar, password (`PATCH /profile`).
- **FR-33** — User dapat mengajukan penghapusan akun (`DELETE /profile`) sesuai hak _right to erasure_ — akun & data terkait di-soft-delete lebih dulu dengan masa tenggang, baru dihapus permanen setelahnya (lihat bagian 13).
- **FR-34** — Profil user (role `user`) menampilkan status langganan (Free/Pro), dihitung dari baris `subscriptions` dengan `status = active` terbaru — bukan disimpan sebagai field terpisah.
- **FR-35** — User dapat melihat riwayat langganan (`GET /subscriptions`); tiap pembelian tercatat sebagai baris baru (append-only log), lengkap dengan paket, masa berlaku, dan tanggal mulai.
- **FR-36** — User dapat melihat bukti pembayaran / receipt satu transaksi langganan (`GET /subscriptions/{id}/receipt`), hanya untuk transaksi berstatus `active`.
- **FR-37** — User dapat melihat daftar sesi login aktif ("Login Activity") beserta perkiraan device & lokasi, dan mana yang merupakan sesi saat ini (`GET /login-activity`).
- **FR-38** — User dapat logout dari satu sesi spesifik (`DELETE /login-activity/{id}`), terpisah dari `logout-all` (FR-1c) yang me-revoke seluruh sesi sekaligus.

## 9. Kontrak API (ringkasan)

Seluruh endpoint diprefix `/api/v1/` (URI versioning) — lihat bagian 13 untuk alasannya. Seluruh path parameter `{id}` di bawah ini merujuk ke kolom `uuid` publik, bukan `id` internal.

| Method    | Endpoint                                     | Auth                     | Deskripsi                                                                          |
| --------- | -------------------------------------------- | ------------------------ | ---------------------------------------------------------------------------------- |
| POST      | `/api/v1/register`                           | –                        | Registrasi user                                                                    |
| POST      | `/api/v1/login`                              | –                        | Login, mengembalikan token Sanctum                                                 |
| POST      | `/api/v1/auth/google`                        | –                        | Login/register via Google ID token (FR-1a)                                         |
| POST      | `/api/v1/logout`                             | user, doctor, admin      | Revoke token device yang aktif (FR-1c)                                             |
| POST      | `/api/v1/logout-all`                         | user, doctor, admin      | Revoke seluruh token aktif milik user (FR-1c)                                      |
| GET       | `/api/v1/profile`                            | user, doctor, admin      | Lihat profil sendiri, field menyesuaikan role (FR-31)                              |
| PATCH     | `/api/v1/profile`                            | user, doctor, admin      | Update nama/avatar/password (FR-32)                                                |
| DELETE    | `/api/v1/profile`                            | user, doctor, admin      | Ajukan penghapusan akun (FR-33)                                                    |
| GET       | `/api/v1/subscriptions`                      | user                     | Riwayat langganan, append-only log (FR-35)                                         |
| GET       | `/api/v1/subscriptions/{id}/receipt`         | user                     | Bukti pembayaran / receipt (FR-36)                                                 |
| GET       | `/api/v1/login-activity`                     | user, doctor, admin      | List sesi login aktif (FR-37)                                                      |
| DELETE    | `/api/v1/login-activity/{id}`                | user, doctor, admin      | Logout satu sesi spesifik (FR-38)                                                  |
| POST      | `/api/v1/forgot-password`                    | –                        | Minta kode OTP reset password (FR-19)                                              |
| POST      | `/api/v1/reset-password`                     | –                        | Verifikasi OTP & ganti password sekaligus (FR-20)                                  |
| POST      | `/api/v1/scans`                              | user                     | Upload foto penuh, forward ke `/predict`                                           |
| POST      | `/api/v1/scans/livecam`                      | user                     | Kirim gambar ter-crop, forward ke `/predict-crop`                                  |
| GET       | `/api/v1/scans`                              | user                     | List riwayat scan milik user (filter/sort)                                         |
| GET       | `/api/v1/scans/{id}`                         | user                     | Detail satu hasil scan                                                             |
| POST      | `/api/v1/conversations`                      | user                     | Mulai percakapan dengan dokter (FR-22)                                             |
| GET       | `/api/v1/doctors`                            | user, doctor, admin      | List dokter terverifikasi — nama + gelar + spesialisasi (FR-22a)                   |
| GET       | `/api/v1/doctors/{id}`                       | user, doctor, admin      | Profil lengkap dokter terverifikasi (FR-39)                                        |
| GET       | `/api/v1/conversations`                      | user, doctor             | List percakapan milik user/dokter yang login                                       |
| GET       | `/api/v1/conversations/{id}/messages`        | user, doctor             | Riwayat pesan (paginated)                                                          |
| POST      | `/api/v1/conversations/{id}/messages`        | user, doctor             | Kirim pesan — kena paywall kalau sender user (FR-23, FR-24)                        |
| POST      | `/api/v1/subscriptions/checkout`             | user                     | Buat transaksi Midtrans Snap untuk paket Pro lifetime, return `snap_token` (FR-25) |
| POST      | `/api/v1/webhooks/midtrans`                  | – (verifikasi signature) | Callback status pembayaran dari Midtrans                                           |
| POST      | `/api/v1/device-tokens`                      | user, doctor             | Daftarkan token FCM perangkat mobile (FR-29)                                       |
| POST      | `/api/v1/doctor-verifications`               | doctor                   | Ajukan verifikasi                                                                  |
| PATCH     | `/api/v1/doctor-verifications/{id}/review`   | admin                    | Approve/reject/minta revisi                                                        |
| POST      | `/api/v1/doctor-verifications/{id}/resubmit` | doctor                   | Ajukan ulang setelah `needs_revision`                                              |
| GET, POST | `/api/v1/skin-concerns`                      | public / admin           | List & kelola concern                                                              |
| GET, POST | `/api/v1/skin-types`                         | public / admin           | List & kelola skin type                                                            |
| GET, POST | `/api/v1/skincare-products`                  | public / doctor          | List & buat produk                                                                 |
| GET, POST | `/api/v1/skin-recommendations`               | public / doctor          | List & buat rekomendasi                                                            |

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
- **Konsistensi response** — seluruh endpoint mengembalikan response lewat Laravel API Resource (bukan raw Model/Collection), dengan format konsisten (`{ "data": ..., "meta": ... }` untuk sukses); penting karena API ini dikonsumsi dua client berbeda (Next.js & Flutter) yang butuh kontrak yang predictable.
- **Performa** — HuggingFace Space free tier berpotensi cold-start lambat; perlu timeout yang wajar dan penanganan UI di FE, atau pertimbangkan queue job untuk pemrosesan async. FK berbasis `bigint` (bukan `uuid`) menjaga efisiensi join & ukuran index saat volume data bertambah besar.
- **Skalabilitas** — pisahkan pemanggilan ke ML service lewat queue job bila volume scan meningkat; strategi PK hybrid (bagian 13) dipilih spesifik untuk mengantisipasi pertumbuhan ke skala enterprise.
- **Observability** — Telescope untuk debugging di local/staging; Sentry untuk error tracking otomatis di production (FR-30); logging terstruktur.
- **Reliabilitas** — retry policy saat panggilan ke ML service gagal atau timeout; webhook Midtrans idempotent (FR-25a).
- **Testing & CI** — feature test wajib untuk jalur berisiko tinggi: autentikasi, logic paywall (FR-24), dan webhook payment; dijalankan otomatis lewat CI (GitHub Actions) di setiap push.
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
- **Paywall chat** — limit 3 pesan gratis dihitung per pasangan user-dokter, hanya pesan dari user yang kehitung (`conversations.message_count`); balasan dokter tidak dibatasi. Riwayat pesan tetap bisa dibaca setelah subscription habis, hanya pesan baru yang ditahan.
- **Payment gateway** — Midtrans Snap, mulai dari environment sandbox untuk testing sebelum go production.
- **Kesiapan ganti model ML** — pemanggilan ke layanan ML dibungkus lewat interface (Adapter/Strategy pattern), bukan dipanggil langsung dari controller. Implementasi model aktif (saat ini: FastAPI + YOLOv8 + SVM) dipilih lewat konfigurasi, bukan hardcode — kalau nanti pindah ke model deep learning atau layanan lain, cukup tambah adapter baru dan ganti config, tanpa mengubah controller/route. Kolom `raw_response` dipertahankan supaya skema data tidak terikat ke satu model tertentu.
- **Kesiapan mobile (Flutter)** — seluruh API dirancang API-only sejak awal sehingga otomatis mobile-ready (token Sanctum, tanpa CORS untuk native client). Reverb kompatibel protokol Pusher sehingga bisa dipakai langsung dari Flutter; push notification saat aplikasi di-background memakai FCM terpisah dari Reverb (yang hanya aktif saat koneksi WebSocket terbuka).

- **API versioning** — seluruh endpoint diprefix `/api/v1/` (URI versioning). Diputuskan sebelum development dimulai karena begitu aplikasi mobile Flutter sudah dipublish ke app store, path API praktis tidak bisa diganti tanpa breaking user yang belum update — jauh lebih murah diterapkan dari awal daripada disisipkan belakangan.
- **Observability: Telescope vs Sentry** — dua tool dengan peran beda, bukan saling gantikan. Telescope untuk debugging mendalam (query, job, request) di local/staging saja — tidak pernah aktif di production karena menampilkan data mentah termasuk yang sensitif. Sentry untuk menangkap error/exception otomatis di production plus alerting, tanpa expose detail internal ke user.
- **Konsistensi response API** — wajib pakai Laravel API Resource class di setiap endpoint, dengan format `{ "data": ..., "meta": ... }` untuk sukses dan format bawaan Laravel (`{ "message": ..., "errors": {...} }`) untuk validation error — krusial karena API ini dikonsumsi dua client berbeda platform (Next.js & Flutter) yang butuh kontrak yang predictable, bukan bentuk response yang beda-beda tiap controller.
- **Idempotency webhook Midtrans** — sebelum memproses notifikasi, cek status subscription saat ini terhadap `midtrans_order_id` di dalam database transaction dengan row lock (`lockForUpdate()`); kalau status yang diminta sudah tercapai, webhook di-skip (tetap dibalas 200 OK ke Midtrans) tanpa mengubah state lagi — mencegah subscription ke-aktivasi dobel akibat retry notifikasi dari Midtrans.
- **Strategi testing & CI** — tidak menargetkan coverage 100% (tidak realistis untuk skala tim saat ini), tapi feature test wajib untuk jalur berisiko tinggi: autentikasi (FR-1 s/d FR-1d), logic paywall chat (FR-24), dan webhook payment (FR-25a). Dijalankan otomatis lewat GitHub Actions di setiap push, sebagai jaring pengaman regresi minimal.
- **Backup & disaster recovery** — backup database MySQL terjadwal (`mysqldump` harian) disimpan ke Cloudflare R2 (provider yang sama dengan storage foto, tidak perlu integrasi baru), dengan retention yang didefinisikan (mis. harian 7-30 hari). Proses restore diuji berkala — backup yang belum pernah dicoba di-restore bukan backup yang bisa diandalkan.
- **Kepatuhan awal UU PDP** — foto wajah & hasil analisis kondisi kulit tergolong data pribadi spesifik (kesehatan/biometrik) di UU PDP. Langkah teknis yang diambil: consent eksplisit dicatat waktunya saat registrasi (`privacy_consent_at`, FR-1d), dan user punya hak mengajukan penghapusan akun & data terkait (`DELETE /profile`, FR-33) lewat soft-delete + masa tenggang sebelum dihapus permanen. Ini bukan pengganti kajian hukum menyeluruh — kewajiban seperti notifikasi breach ke otoritas & penunjukan penanggung jawab perlindungan data sebaiknya dikonsultasikan ke pihak legal.

- **Model paket: lifetime, one-time payment.** Harga dipatok Rp15.000 tetap dalam IDR (bukan hasil konversi live dari "$1", yang cuma teks marketing di UI) karena Midtrans native-nya transaksi Rupiah — nominal berubah-ubah ngikutin kurs bakal nyusahin audit ("kenapa user A bayar beda dari user B buat paket yang sama"). Karena lifetime, tidak ada alur recurring billing / auto-renewal sama sekali.
- **Info benefit paket tidak disimpan di database** — karena baru ada satu varian paket (Pro lifetime), teks benefit cukup didefinisikan di `config/plans.php`. Baru dijadikan tabel kalau nanti ada beberapa varian paket yang perlu dikelola dinamis.
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
| `NotificationType.php`     | tipe payload notifikasi/broadcast Reverb (FR-16, FR-29): `chat_message_received`, `doctor_verification_reviewed`, `scan_result_ready`, `daily_care_recommendation` |
| `NotificationCategory.php` | pengelompokan notifikasi: `transactional` (dipicu event) vs `reminder` (terjadwal) — dipakai `NotificationType::category()`                                        |
