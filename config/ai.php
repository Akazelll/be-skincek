<?php

return [

    'bot_name' => 'Aura Skin',
    'bot_email' => env('AI_BOT_EMAIL', 'aura@skincek.com'),

    /*
    |--------------------------------------------------------------------------
    | Persetujuan Penggunaan AI (UU PDP — data kesehatan = data sensitif)
    |--------------------------------------------------------------------------
    | `consent_version` diubah bila teks berubah; user wajib menyetujui ulang.
    */
    'consent_version' => 'v1',
    'consent_text' => 'Dengan menyetujui, kamu mengizinkan SkinCek membagikan isi pesan teks chat-mu ke penyedia kecerdasan buatan (Google Gemini) agar Aura Skin dapat menjawab pertanyaanmu. Pesan tidak digunakan untuk melatih model dan foto tidak pernah dibagikan. Kamu dapat mencabut persetujuan ini kapan saja dan tetap bisa menggunakan chat dokter.',

    /*
    |--------------------------------------------------------------------------
    | Kuota chat AI
    |--------------------------------------------------------------------------
    | User gratis dibatasi per hari; pengguna Pro tanpa batas.
    */
    'free_daily_limit' => (int) env('AI_CHAT_FREE_DAILY_LIMIT', 10),
    'max_history_messages' => (int) env('AI_CHAT_MAX_HISTORY_MESSAGES', 8),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.4),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batasan keamanan jawaban
    |--------------------------------------------------------------------------
    | Pertanyaan yang memicu kata kunci berbahaya tidak dikirim ke LLM dan
    | langsung diarahkan ke dokter.
    */
    'system_prompt' => 'Kamu adalah Aura Skin, asisten edukasi perawatan kulit (skincare) dari aplikasi SkinCek. Aturan: 1) Jawab hanya seputar edukasi skincare umum dalam Bahasa Indonesia, singkat (maksimal 300 kata). 2) DILARANG memberikan diagnosis penyakit, resep atau dosis obat, atau menangani kondisi darurat. 3) Untuk pertanyaan di luar cakupan atau kondisi spesifik, sarankan berkonsultasi dengan dokter kulit dan jangan menjawab secara medis. 4) Selalu akhiri dengan pengingat: "Untuk kondisi spesifik, sebaiknya konsultasikan dengan dokter kulit ya." 5) Bila pengguna mengirim pesan yang dimulai dengan "Hasil scan:" (hasil prediksi AI SkinCek berisi kelas kondisi, tingkat keyakinan, dan tingkat keparahan), analisis dengan struktur: (a) jelaskan makna umum kondisi terdeteksi secara edukatif, (b) jelaskan arti severity untuk perawatan sehari-hari, (c) berikan 2-3 saran perawatan umum, (d) ingatkan bahwa hasil scan bukan diagnosis medis. Jangan menyalin ulang angka-angka secara mentah, dan tetap patuhi batasan maksimal 300 kata.',

    'unsafe_keywords' => [
        'dosis', 'resep', 'obat apa', 'berapa mg', 'suntik', 'darurat', 'berdarah',
        'muntah', 'pingsan', 'sulit bernapas', 'menyebar cepat', 'gejala berat',
    ],

    'escalation_reply' => 'Maaf, pertanyaan ini di luar cakupan Aura Skin (Aku hanya membahas edukasi skincare umum). Untuk kondisi yang kamu tanyakan, sebaiknya segera berkonsultasi dengan dokter kulit atau layanan kesehatan terdekat ya.',

    'error_reply' => 'Maaf, layanan Aura Skin sedang tidak tersedia. Silakan coba lagi sebentar lagi, atau konsultasikan dengan dokter kulit untuk pertanyaan kamu.',

];
